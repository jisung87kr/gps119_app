<?php

use App\Enums\EventRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| 브로드캐스트 채널 인가 (실시간 위치·지령 관제 — SPEC-05a)
|--------------------------------------------------------------------------
|
| 여기서 정의한 콜백은 /broadcasting/auth 로 들어오는 채널 구독 인가에 사용된다.
| 콜백이 true(또는 배열)를 반환하면 인가 통과(200), false면 거부(403).
|
*/

// requests.global — 일반 신고(project_id=null)의 전역 관제 채널 (OI-1 확정 반영)
// 시스템 admin·rescuer 가 구독한다.
Broadcast::channel('requests.global', function (User $user) {
    return $user->hasRole('admin') || $user->hasRole('rescuer');
});

// event.{projectId}.control — 행사별 관제 채널 (SPEC-05a)
// Phase 1(BE-1.2) 강화: 해당 행사 active 참가자이면서 EventRole::CONTROLLER, 또는 시스템 admin.
// 구급대(paramedic) 등 비-controller 역할은 control 채널 불통과(ADR-0004).
Broadcast::channel('event.{projectId}.control', function (User $user, int $projectId) {
    // 시스템 admin 은 전역 권한으로 통과(active 참가 여부 무관)
    if ($user->hasRole('admin')) {
        return true;
    }

    $project = Project::find($projectId);
    if (! $project) {
        return false;
    }

    // active 참가 + CONTROLLER 만 통과
    return $user->eventRoleIn($project) === EventRole::CONTROLLER;
});
