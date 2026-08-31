<?php

namespace App\Http\Controllers\Api;

use App\Enums\LocationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationPingRequest;
use App\Models\Project;
use App\Services\EventParticipantService;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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

    /**
     * 앱이 OS 위치 권한 상태를 보고한다 (M-5, ADR-0008).
     *
     * ⚠️ **`/location`(ping)과 합칠 수 없다.** 권한이 끊기면 ping 이 안 오므로,
     *    정작 알아야 할 «권한이 없어진 순간»에 아무 신호도 안 온다. 그래서 별도 경로이고
     *    **공유가 꺼져 있어도 받는다** — 그래야 관제가 「껐다」와 「막혔다」를 가른다.
     *
     * 🔑 응답으로 파생 상태를 되돌려준다. 앱이 「지금 내가 어떻게 보이는지」를 그대로
     *    쓸 수 있어야 «켰는데 안 보인다»를 사용자에게 설명할 수 있다 — 그 판정을
     *    앱에서 다시 만들면 서버와 어긋난다.
     */
    public function locationPermission(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'permission' => ['required', Rule::enum(LocationPermission::class)],
        ]);

        $project = Project::findOrFail($id);
        $participant = $this->participantService->setLocationPermission(
            $project,
            Auth::user(),
            LocationPermission::from($validated['permission']),
        );

        return response()->success([
            'location_permission' => $participant->location_permission->value,
            'tracking_state' => $participant->trackingState()->value,
        ], '위치 권한 상태를 기록했습니다.');
    }
}
