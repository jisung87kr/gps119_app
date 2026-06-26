<?php

namespace App\Http\Middleware;

use App\Models\Dispatch;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 행사 역할 가드 (SPEC-06a).
 *
 * 사용: event.role:controller  또는  event.role:paramedic,controller (콤마=OR)
 *
 * 행사 id 해석(08 문서):
 *   - {requestId} → 신고의 project_id (신고 스코프 라우트: 지령 배정 등)
 *   - {dispatch}  → 지령의 project_id (지령 스코프 라우트)
 *   - {project}/{id} → 프로젝트 직접
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
        // 1. 신고 스코프({requestId}) → 신고의 행사
        $reqParam = $request->route('requestId');
        if ($reqParam !== null) {
            $r = $reqParam instanceof RescueRequest ? $reqParam : RescueRequest::find($reqParam);

            return $r?->project;
        }

        // 2. 지령 스코프({dispatch}) → 지령의 행사 (08 문서)
        $dispatchParam = $request->route('dispatch');
        if ($dispatchParam !== null) {
            $d = $dispatchParam instanceof Dispatch ? $dispatchParam : Dispatch::find($dispatchParam);

            return $d?->project;
        }

        // 3. 모델 바인딩으로 이미 Project 인스턴스가 들어온 경우
        $bound = $request->route('project');
        if ($bound instanceof Project) {
            return $bound;
        }

        // 4. {id} 또는 {project} 가 프로젝트 id(정수) 인 경우
        $id = $request->route('id') ?? $request->route('project');
        if ($id !== null) {
            return Project::find($id);
        }

        return null;
    }
}
