<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Http\Controllers\Controller;
use App\Models\EventParticipant;
use App\Models\EventRoster;
use App\Models\Project;
use App\Models\User;
use App\Services\EventParticipantService;
use App\Services\ParticipantImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use RuntimeException;

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

        // 명단에는 있는데 아직 입장하지 않은 사람.
        //
        // 🔑 이게 «전화번호 오타»를 잡는 유일한 장치다. 명단 매칭은 전화번호 기준이라,
        //    한 자리가 틀리면 그 운영진은 조용히 «참가자»로 입장하고 아무도 모른다.
        //    행사 시작 전에 이 목록이 비어 가는지 보면 발견할 수 있다.
        $rosterPending = EventRoster::forProject($project->id)
            ->unclaimed()
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return view('admin.projects.participants', [
            'project' => $project,
            'participants' => $participants,
            'addableUsers' => $addableUsers,
            'rosterPending' => $rosterPending,
            'rosterTotal' => EventRoster::forProject($project->id)->count(),
            'roles' => EventRole::cases(),
            'statuses' => ParticipantStatus::cases(),
        ]);
    }

    /**
     * 명단 1행 삭제 (아직 입장 안 한 줄의 오타 정정용).
     *
     * 이미 입장한 줄은 지우지 않는다 — 그건 «누가 명단에 있었는가»라는 행사 기록이고,
     * 지워도 그 사람의 참가(EventParticipant)는 남으므로 화면만 헷갈려진다.
     */
    public function rosterDestroy(Project $project, EventRoster $roster)
    {
        abort_unless($roster->project_id === $project->id, 404);

        if ($roster->isClaimed()) {
            return back()->withErrors(['roster' => '이미 입장한 명단은 삭제할 수 없습니다. 참가자 목록에서 역할을 바꾸거나 삭제해 주세요.']);
        }

        $roster->delete();

        return back()->with('success', '명단에서 삭제했습니다.');
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

    /**
     * 명단 CSV 일괄 등록.
     *
     * 파싱·행 처리는 ParticipantImportService 가 전부 한다(컨트롤러는 thin).
     * 결과는 «리포트»로 돌려준다 — 100행 올렸는데 "실패했습니다"만 뜨면 쓸모가 없다.
     */
    public function import(Request $request, Project $project, ParticipantImportService $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ], [
            'file.required' => 'CSV 파일을 선택해 주세요.',
            'file.mimes' => '엑셀 파일(.xlsx)은 직접 올릴 수 없습니다. 엑셀에서 「다른 이름으로 저장 → CSV」로 저장한 뒤 올려주세요.',
            'file.max' => '파일이 너무 큽니다(최대 1MB).',
        ]);

        $contents = file_get_contents($request->file('file')->getRealPath());

        if ($contents === false) {
            return back()->withErrors(['file' => '파일을 읽지 못했습니다. 다시 시도해 주세요.']);
        }

        try {
            $report = $importer->import($project, $contents);
        } catch (RuntimeException $e) {
            // 전량 거부(행 수 상한·빈 파일). 조용히 잘라내지 않는다.
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('importReport', $report);
    }

    /**
     * 템플릿 CSV 다운로드. UTF-8 BOM 필수 — 없으면 엑셀이 한글 헤더를 깨뜨린다.
     */
    public function importTemplate(Project $project)
    {
        return response(ParticipantImportService::templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="participants-template.csv"',
        ]);
    }

    public function destroy(Project $project, User $user)
    {
        EventParticipant::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->delete();

        return back()->with('success', '참가자를 행사에서 제외했습니다.');
    }
}
