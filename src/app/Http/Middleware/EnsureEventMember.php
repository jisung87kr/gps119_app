<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 행사 참가자(active) 가드 — 역할 무관 (OI-4 권장안: event.member 미들웨어 신설).
 *
 * 사용: event.member
 *
 * "역할 무관 active 참가자" 를 표현하기 위한 경량 가드.
 * 위치 ping(POST .../location) 등 모든 active 참가자가 접근하는 라우트에 사용한다.
 * 시스템 admin 은 전역 권한으로 통과.
 */
class EnsureEventMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->error('인증이 필요합니다', 401);
        }

        $project = $this->resolveProject($request);

        if (! $project) {
            return response()->error('행사를 찾을 수 없습니다', 404);
        }

        // 시스템 admin 은 전역 권한으로 통과
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // active 참가면 역할 무관 통과 (eventRoleIn 은 active 만 역할 반환)
        if ($user->eventRoleIn($project) !== null) {
            return $next($request);
        }

        return response()->error('행사 참가자만 접근할 수 있습니다', 403);
    }

    private function resolveProject(Request $request): ?Project
    {
        $bound = $request->route('project');
        if ($bound instanceof Project) {
            return $bound;
        }

        $id = $request->route('id') ?? $request->route('project');
        if ($id !== null) {
            return Project::find($id);
        }

        return null;
    }
}
