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

// event.{projectId}.locations — 위치 presence 채널 (BE-2.2 / SPEC-05a)
// 해당 행사 active 참가자면 누구나 통과(역할 무관). 시스템 admin 도 통과.
// presence 인가 payload 는 {user_id, role} 만(연락처 없음).
Broadcast::channel('event.{projectId}.locations', function (User $user, int $projectId) {
    $project = Project::find($projectId);
    if (! $project) {
        return false;
    }

    // 시스템 admin: 관제 모니터링용 통과(역할은 표기상 controller 로 노출)
    if ($user->hasRole('admin')) {
        return ['user_id' => $user->id, 'role' => EventRole::CONTROLLER->value];
    }

    $role = $user->eventRoleIn($project); // active 만 역할 반환
    if ($role === null) {
        return false;
    }

    return ['user_id' => $user->id, 'role' => $role->value];
});

// event.{projectId}.dispatch.{userId} — 구급대원 개인 지령 채널 (BE-3.3 / SPEC-05a)
// 본인(auth id===userId) + 해당 행사 active 참가 + 지령 수령 가능 역할만.
Broadcast::channel('event.{projectId}.dispatch.{userId}', function (User $user, int $projectId, int $userId) {
    if ($user->id !== $userId) {
        return false;
    }
    $project = Project::find($projectId);
    if (! $project) {
        return false;
    }
    $role = $user->eventRoleIn($project);

    return $role !== null && $role->canReceiveDispatch();
});

// event.{projectId}.requester.{userId} — 신고자 본인 채널 (BE-3.3 / SPEC-05a)
// 본인(auth id===userId) + 그 행사에 본인 신고 이력 보유(OI-5: 소유 신고 존재만 확인).
Broadcast::channel('event.{projectId}.requester.{userId}', function (User $user, int $projectId, int $userId) {
    if ($user->id !== $userId) {
        return false;
    }

    return \App\Models\Request::where('project_id', $projectId)
        ->where('user_id', $user->id)
        ->exists();
});
