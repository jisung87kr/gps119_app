<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 행사 역할 가드 (SPEC-06a).
 *
 * 사용: event.role:controller  또는  event.role:paramedic,controller (콤마=OR)
 *
 * 행사 id 해석 (Phase 1은 project 라우트만 사용):
 *   - 라우트 파라미터 {id}/{project} 가 project → 직접 해석.
 *   - (Phase 3) dispatch 라우트 → Dispatch->project_id 로 해석 — BE-3.x 에서 확장.
 *
 * 판정: User::eventRoleIn($project) 가 허용 역할에 포함되거나 시스템 admin 이면 통과.
 */
class EnsureEventRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->error('인증이 필요합니다', 401);
        }

        $project = $this->resolveProject($request);

        if (! $project) {
            return response()->error('행사를 찾을 수 없습니다', 404);
        }

        // 시스템 admin 은 전역 권한으로 통과 (ADR-0002)
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $eventRole = $user->eventRoleIn($project);

        if ($eventRole && in_array($eventRole->value, $roles, true)) {
            return $next($request);
        }

        return response()->error('해당 행사에 대한 권한이 없습니다', 403);
    }

    /**
     * 라우트 바인딩에서 Project 를 해석한다.
     */
    private function resolveProject(Request $request): ?Project
    {
        // 모델 바인딩으로 이미 Project 인스턴스가 들어온 경우
        $bound = $request->route('project');
        if ($bound instanceof Project) {
            return $bound;
        }

        // {id} 또는 {project} 가 프로젝트 id(정수) 인 경우
        $id = $request->route('id') ?? $request->route('project');
        if ($id !== null) {
            return Project::find($id);
        }

        return null;
    }
}
