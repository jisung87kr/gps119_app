<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Http\Controllers\Controller;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use App\Services\EventParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 행사 입장·참가자 API (SPEC-06b).
 *
 * 컨트롤러는 thin — 비즈니스 로직은 EventParticipantService.
 * 응답은 response()->success/error 매크로 사용.
 */
class EventApiController extends Controller
{
    public function __construct(
        private EventParticipantService $participantService
    ) {}

    /**
     * GET /api/events/{joinCode}
     * 입장 전 미리보기 — 민감정보 없는 행사 요약만.
     */
    public function show(string $joinCode): JsonResponse
    {
        $project = Project::where('join_code', $joinCode)->first();

        if (! $project) {
            return response()->error('유효하지 않은 입장 코드입니다.', 404);
        }

        return response()->success([
            'id' => $project->id,
            'name' => $project->name,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'is_active' => $project->isActive(),
        ]);
    }

    /**
     * POST /api/events/{joinCode}/join
     * 행사 입장 — participant=active upsert. 멱등. 전화번호 없으면 거부.
     */
    public function join(string $joinCode): JsonResponse
    {
        try {
            $participant = $this->participantService->joinByCode($joinCode, Auth::user());
        } catch (\RuntimeException $e) {
            return response()->error($e->getMessage(), 422);
        }

        return response()->success([
            'participant' => [
                'id' => $participant->id,
                'role' => $participant->role->value,
                'status' => $participant->status->value,
            ],
            'project' => [
                'id' => $participant->project->id,
                'name' => $participant->project->name,
            ],
        ], '행사에 입장했습니다.');
    }

    /**
     * GET /api/events/{id}/me
     * 내 참가정보 — active 참가자만. (가드: event.member)
     */
    public function me(int $id): JsonResponse
    {
        $participant = EventParticipant::where('project_id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $participant) {
            return response()->error('해당 행사에 참가하고 있지 않습니다.', 404);
        }

        return response()->success([
            'role' => $participant->role->value,
            'status' => $participant->status->value,
            'sharing_location' => $participant->sharing_location,
            'last_seen_at' => $participant->last_seen_at?->toISOString(),
        ]);
    }

    /**
     * PATCH /api/events/{id}/participants/{userId}
     * 현장 수동 역할/상태 배정 — controller/admin 만 (가드: event.role:controller).
     */
    public function assignRole(Request $request, int $id, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(EventRole::class)],
            'status' => ['nullable', Rule::enum(ParticipantStatus::class)],
        ]);

        $project = Project::findOrFail($id);
        $target = User::findOrFail($userId);

        $status = isset($validated['status'])
            ? ParticipantStatus::from($validated['status'])
            : ParticipantStatus::ACTIVE;

        $participant = $this->participantService->assignRole(
            $project,
            $target,
            EventRole::from($validated['role']),
            $status
        );

        return response()->success([
            'participant' => [
                'id' => $participant->id,
                'user_id' => $participant->user_id,
                'role' => $participant->role->value,
                'status' => $participant->status->value,
            ],
        ], '참가자 역할을 변경했습니다.');
    }
}
