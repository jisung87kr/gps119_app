<?php

namespace App\Services;

use App\Enums\EventRole;
use App\Enums\ParticipantStatus;
use App\Models\EventParticipant;
use App\Models\EventRoster;
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
     * 🔑 역할은 «사전명단»(event_rosters)이 정한다 (2026-08-12, 예전 TODO(Q1) 구현).
     *    운영진과 참가자가 «같은» 입장 QR 을 쓴다 — 운영진에게 별도 동선을 만들면
     *    현장 안내문에 QR 이 두 개 붙고, 그건 반드시 헷갈린다.
     *    명단에 있으면 그 역할, 없으면 일반 참가자.
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

        // 명단 조회는 «정규화된» 번호로 한다. User::setPhoneAttribute 가 숫자만 저장하므로
        // 보통은 그대로지만, 형식이 남아 있는 과거 행에서도 매칭이 깨지지 않게 한 번 더 건다.
        // 여기서 못 찾으면 운영진이 조용히 «참가자»로 들어오고 아무도 그 사실을 모른다.
        $phone = ParticipantImportService::normalizePhone($user->phone);
        $roster = $phone ? EventRoster::findByPhone($project->id, $phone) : null;

        $participant = EventParticipant::firstOrCreate(
            ['project_id' => $project->id, 'user_id' => $user->id],
            [
                'role' => $roster?->role ?? EventRole::PARTICIPANT,
                'status' => ParticipantStatus::ACTIVE,
                'joined_at' => now(),
            ]
        );

        // 명단 소진 기록. 이미 소진됐으면 덮지 않는다 — «처음 들어온 시각»이 기록이다.
        // (재입장은 firstOrCreate 가 기존 참가 행을 그대로 돌려주므로 역할도 안 바뀐다.
        //  관리자가 화면에서 바꾼 역할을 재입장이 되돌리면 안 되기 때문이다.)
        if ($roster && ! $roster->isClaimed()) {
            $roster->forceFill(['user_id' => $user->id, 'claimed_at' => now()])->save();
        }

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

    /**
     * 관제 초기 로드/폴백용 roster (SPEC-04b/06b).
     *
     * active 참가 전원의 최신 위치 캐시 + 역할 + online 여부를 1쿼리로 반환.
     * 이력(location_pings) 미조회 — event_participants 캐시만 사용.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rosterForControl(Project $project, int $onlineThresholdSeconds = 60): array
    {
        $rows = EventParticipant::query()
            ->forProject($project->id)
            ->active()
            ->with('user:id,name')
            ->get();

        return $rows->map(fn (EventParticipant $p) => [
            'user_id' => $p->user_id,
            'name' => $p->user?->name,
            'role' => $p->role->value,
            'status' => $p->status->value,
            'last_lat' => $p->last_lat,
            'last_lng' => $p->last_lng,
            // 정확도(m). "어디 있는지"만큼 "얼마나 확실한지"가 구조 판단을 바꾼다.
            'last_accuracy' => $p->last_accuracy,
            'last_seen_at' => $p->last_seen_at?->toISOString(),
            'online' => $p->isOnline($onlineThresholdSeconds),
        ])->all();
    }
}
