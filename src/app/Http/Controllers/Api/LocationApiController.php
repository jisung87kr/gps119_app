<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationPingRequest;
use App\Models\Project;
use App\Services\EventParticipantService;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 위치 API (SPEC-06b).
 *
 * 컨트롤러는 thin — 로직은 LocationService / EventParticipantService.
 * 인가는 라우트 미들웨어(event.member / event.role:controller).
 */
class LocationApiController extends Controller
{
    public function __construct(
        private LocationService $locationService,
        private EventParticipantService $participantService,
    ) {}

    /**
     * POST /api/events/{id}/location  (event.member)
     * 위치 ping 수신 — 캐시 갱신 + 큐 적재 + 브로드캐스트. 비동기 적재라 본문 없음.
     */
    public function store(StoreLocationPingRequest $request, int $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        try {
            $this->locationService->record($project, Auth::user(), $request->validated());
        } catch (\RuntimeException $e) {
            return response()->error($e->getMessage(), 403);
        }

        return response()->success(null, '위치가 기록되었습니다.', 202);
    }

    /**
     * GET /api/events/{id}/participants  (event.role:controller)
     * 관제 초기 로드/폴백 roster — 전 인원 최신위치+역할+online.
     */
    public function participants(int $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        return response()->success($this->participantService->rosterForControl($project));
    }

    /**
     * PATCH /api/events/{id}/sharing  (event.member)
     * 위치공유 on/off.
     */
    public function sharing(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'sharing_location' => ['required', 'boolean'],
        ]);

        $project = Project::findOrFail($id);
        $participant = $this->participantService->setSharing(
            $project,
            Auth::user(),
            $validated['sharing_location']
        );

        return response()->success([
            'sharing_location' => $participant->sharing_location,
        ], '위치공유 설정을 변경했습니다.');
    }
}
