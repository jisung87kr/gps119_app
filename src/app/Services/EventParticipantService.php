<?php

namespace App\Services;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\Project;
use App\Models\User;
use RuntimeException;

/**
 * 행사 입장·역할 배정 로직 응집 (SPEC-04b).
 *
 * 비즈니스 로직은 서비스레이어에 둔다(컨트롤러는 thin).
 */
class EventParticipantService
{
    /**
     * join_code 로 입장.
     *
     * - 미참가면 생성: 자가선택 participant = active (기본).
     * - 재호출 멱등: 이미 참가 행이 있으면 그대로 반환(중복 row 없음, unique 제약).
     * - 비활성 행사면 거부(422 → 컨트롤러에서 처리).
     * - 전화번호 없으면 거부(05 require-phone 정책 계승).
     *
     * Q1(사전명단 CSV 매칭)은 v1 범위 밖. 기본 participant 입장 + assignRole 수동배정으로 충분.
     * TODO(후속): 전화번호 기반 사전명단 CSV 매칭 시 해당 역할로 입장(데이터소스 결정 후).
     */
    public function joinByCode(string $joinCode, User $user): EventParticipant
    {
        $project = Project::where('join_code', $joinCode)->first();

        if (! $project) {
            throw new RuntimeException('유효하지 않은 입장 코드입니다.');
        }

        if (! $project->isActive()) {
            throw new RuntimeException('현재 입장할 수 없는 행사입니다.');
        }

        // 전화번호 필수 (구급대원이 신고자에게 직접 전화 — 도메인 핵심)
        if (empty($user->phone)) {
            throw new RuntimeException('전화번호가 등록되어야 행사에 참가할 수 있습니다.');
        }

        $participant = EventParticipant::firstOrCreate(
            ['project_id' => $project->id, 'user_id' => $user->id],
            [
                'role' => EventRole::PARTICIPANT,
                'status' => ParticipantStatus::ACTIVE,
                'joined_at' => now(),
            ]
        );

        return $participant->load('project');
    }

    /**
     * controller/admin 이 참가자 역할/상태 변경(승인 포함).
     *
     * 권한 검사(controller/admin)는 라우트 미들웨어(event.role:controller)에서 1차 수행.
     * 여기서는 대상 참가 행을 upsert 한다(미참가 사용자도 수동 배정 가능).
     */
    public function assignRole(
        Project $project,
        User $target,
        EventRole $role,
        ParticipantStatus $status = ParticipantStatus::ACTIVE
    ): EventParticipant {
        $participant = EventParticipant::firstOrNew([
            'project_id' => $project->id,
            'user_id' => $target->id,
        ]);

        $participant->role = $role;
        $participant->status = $status;
        if (! $participant->joined_at) {
            $participant->joined_at = now();
        }
        $participant->save();

        return $participant;
    }

    /**
     * 위치공유 on/off (SPEC-04b). Phase 2 위치 ping 에서 사용.
     */
    public function setSharing(Project $project, User $user, bool $on): EventParticipant
    {
        $participant = EventParticipant::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $participant->sharing_location = $on;
        $participant->save();

        return $participant;
    }
}
