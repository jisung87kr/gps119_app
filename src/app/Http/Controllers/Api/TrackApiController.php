<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\TrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * GET /api/events/{id}/tracks  (event.role:controller)
 *
 * 🔴 **관제만 본다.** 참가자 전원의 지나온 자리는 관제의 판단 자료이지 서로 공유할
 *    정보가 아니다. 라우트 미들웨어가 막는다.
 */
class TrackApiController extends Controller
{
    public function __construct(private TrackService $tracks) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:1', 'max:'.TrackService::MAX_MINUTES],
            'user_ids' => ['nullable', 'array', 'max:200'],
            'user_ids.*' => ['integer'],
        ]);

        $project = Project::findOrFail($id);
        $minutes = $validated['minutes'] ?? 60;
        $since = Carbon::now()->subMinutes($minutes);

        return response()->success([
            'since' => $since->toIso8601String(),
            'minutes' => $minutes,
            'tracks' => $this->tracks->forProject($project, $since, $validated['user_ids'] ?? null),
        ]);
    }
}
