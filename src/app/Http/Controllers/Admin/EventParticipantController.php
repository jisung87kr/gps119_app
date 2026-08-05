<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Http\Controllers\Controller;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use App\Services\EventParticipantService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * 관리자용 행사 참가자·역할 관리 (EventRole).
 *
 * 회원관리(시스템 역할: admin/rescuer/user)와는 별개인 "행사 내 역할"을 관리한다.
 * 실무 배정은 EventParticipantService::assignRole 로 위임(단일 writer).
 */
class EventParticipantController extends Controller
{
    public function __construct(private EventParticipantService $participants) {}

    public function index(Project $project)
    {
        // join_code 는 creating 훅에서 자동 발급되지만, 그 기능 이전에 만들어진
        // 행사에는 NULL 이 남아 있다. 이 화면은 입장 링크(route(events.join.code))를
        // 반드시 그리므로 NULL 이면 500 이 난다 — 진입 시 지연 백필한다.
        if (empty($project->join_code)) {
            $project->forceFill(['join_code' => Project::generateUniqueJoinCode()])->save();
        }

        $participants = $project->participants()
            ->with('user:id,name,phone')
            // status 우선순위(active→pending→left). FIELD()는 MySQL 전용이라 이식성 위해 CASE 사용.
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderBy('role')
            ->get();

        $joinedIds = $participants->pluck('user_id');

        // 아직 이 행사에 없는 회원 (추가용)
        $addableUsers = User::whereNotIn('id', $joinedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return view('admin.projects.participants', [
            'project' => $project,
            'participants' => $participants,
            'addableUsers' => $addableUsers,
            'roles' => EventRole::cases(),
            'statuses' => ParticipantStatus::cases(),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', new Enum(EventRole::class)],
        ]);

        $this->participants->assignRole(
            $project,
            User::findOrFail($validated['user_id']),
            EventRole::from($validated['role']),
            ParticipantStatus::ACTIVE,
        );

        return back()->with('success', '참가자를 추가했습니다.');
    }

    public function update(Request $request, Project $project, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', new Enum(EventRole::class)],
            'status' => ['required', new Enum(ParticipantStatus::class)],
        ]);

        $this->participants->assignRole(
            $project,
            $user,
            EventRole::from($validated['role']),
            ParticipantStatus::from($validated['status']),
        );

        return back()->with('success', "{$user->name}님의 역할을 변경했습니다.");
    }

    public function destroy(Project $project, User $user)
    {
        EventParticipant::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->delete();

        return back()->with('success', '참가자를 행사에서 제외했습니다.');
    }
}
