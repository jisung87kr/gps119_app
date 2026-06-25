<?php

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
| ※ Phase 0 잠정 규칙 ※
| event_participants 테이블/모델은 Phase 1 산출물이라 아직 존재하지 않는다.
| 따라서 행사 단위 정밀 인가(EventParticipant active + EventRole) 대신
| 시스템 전역 역할(spatie: admin / rescuer)로만 잠정 판정한다.
| Phase 1(BE-1.2)에서 User::eventRoleIn(Project)의 active+controller 판정으로 강화할 것.
| TODO(Phase 1): event.{projectId}.control 인가를 EventParticipant 기반으로 교체.
|
*/

// requests.global — 일반 신고(project_id=null)의 전역 관제 채널 (OI-1 확정 반영)
// 시스템 admin·rescuer 가 구독한다.
Broadcast::channel('requests.global', function (User $user) {
    return $user->hasRole('admin') || $user->hasRole('rescuer');
});

// event.{projectId}.control — 행사별 관제 채널
// Phase 0 잠정: admin·rescuer 통과.
// TODO(Phase 1, BE-1.2): $user->eventRoleIn($project) === EventRole::CONTROLLER 또는 시스템 admin 으로 강화.
Broadcast::channel('event.{projectId}.control', function (User $user, int $projectId) {
    return $user->hasRole('admin') || $user->hasRole('rescuer');
});
