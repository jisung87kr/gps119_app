<?php

namespace App\Http\Controllers\Api;

use App\Enums\DispatchStatus;
use App\Exceptions\DispatchAuthorizationException;
use App\Exceptions\DispatchTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Dispatch;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use App\Services\DispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 지령 API (SPEC-06b). 컨트롤러 thin — 로직은 DispatchService.
 */
class DispatchApiController extends Controller
{
    public function __construct(private DispatchService $service) {}

    /**
     * POST /api/requests/{id}/dispatch  (event.role:controller)
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'paramedic_id' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $rescueRequest = RescueRequest::findOrFail($id);
        $paramedic = User::findOrFail($validated['paramedic_id']);

        try {
            $dispatch = $this->service->assign($rescueRequest, $paramedic, Auth::user(), $validated['note'] ?? null);
        } catch (DispatchAuthorizationException $e) {
            return response()->error($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return response()->error($e->getMessage(), 422);
        }

        return response()->success($dispatch, '지령을 배정했습니다.', 201);
    }

    /**
     * PATCH /api/dispatches/{id}/status
     * 가드: 해당 dispatch 의 paramedic 본인 또는 그 행사 controller(서비스 권한 검사).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(DispatchStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'reject_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $dispatch = Dispatch::findOrFail($id);
        $target = DispatchStatus::from($validated['status']);

        try {
            $updated = $this->service->transition(
                $dispatch,
                $target,
                Auth::user(),
                $validated['note'] ?? null,
                $validated['reject_reason'] ?? null
            );
        } catch (DispatchAuthorizationException $e) {
            return response()->error($e->getMessage(), 403);
        } catch (DispatchTransitionException $e) {
            return response()->error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return response()->error($e->getMessage(), 422);
        }

        return response()->success($updated, '지령 상태를 변경했습니다.');
    }

    /**
     * GET /api/dispatches/mine  (auth, 본인 소유)
     */
    public function mine(): JsonResponse
    {
        return response()->success($this->service->myDispatches(Auth::user()));
    }

    /**
     * GET /api/events/{id}/dispatches  (event.role:controller) — 출동 현황 보드
     */
    public function board(int $id): JsonResponse
    {
        $project = Project::findOrFail($id);

        return response()->success($this->service->boardForProject($project));
    }

    /**
     * GET /api/requests/{id}/available-paramedics  (event.role:controller)
     * 가용 구급대원(거리순 + 보유 지령수, online).
     */
    public function availableParamedics(int $id): JsonResponse
    {
        $rescueRequest = RescueRequest::findOrFail($id);

        return response()->success($this->service->availableParamedics($rescueRequest));
    }
}
