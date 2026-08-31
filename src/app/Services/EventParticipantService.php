<?php

namespace App\Services;

use App\Enums\EventRole;
use App\Enums\LocationPermission;
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
                // 🔑 **참가하면 위치 공유가 켜진 채로 시작한다.** 구조 지원이 이 행사의
                //    목적이고, 참가자가 아무것도 누르지 않아도 상황실이 볼 수 있어야 한다.
                //
                // 🔴 **firstOrCreate 의 «생성» 값이라 재입장에는 적용되지 않는다.**
                //    이게 요점이다 — 매번 켜면 사용자가 «끈» 공유가 재입장만으로
                //    되살아난다. 실제로 활동 화면이 enable() 을 무조건 불러서 그렇게
                //    되고 있었다(2026-08-31). 의도는 데이터에 한 번만 새기고,
                //    그 뒤로는 서버 값을 따른다.
                'sharing_location' => true,
            ]
        );

        // 🔑 «입장한 시각»은 매번 갱신한다. joined_at 은 최초 입장이라, 두 행사를 오가는
        //    사람에게는 영원히 처음 들어간 쪽이 이긴다 — 그건 「지금 있는 현장」이 아니다.
        //    동시에 두 행사에 참가한 사람의 신고를 어디에 붙일지가 여기서 결정된다.
        $participant->forceFill(['last_entered_at' => now()])->save();

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
     * 앱이 보고한 OS 위치 권한 상태를 기록한다 (M-5, ADR-0008).
     *
     * ⚠️ **ping 에 실어 보낼 수 없어서 별도 진입점이 필요하다.** 권한이 끊기면 ping 도
     *    끊기므로, 정작 알아야 할 순간에 아무것도 안 온다. 앱은 포그라운드 복귀·공유
     *    토글·OS 권한 변경 콜백에서 «공유가 꺼져 있어도» 보고한다.
     *
     * 🔑 **`sharing_location` 을 건드리지 않는다.** 의도와 능력은 다른 축이고,
     *    권한이 없다고 사용자의 «켬»을 서버가 꺼버리면 권한을 되돌렸을 때 왜 안 되는지
     *    아무도 모른다. 되돌아오면 그대로 다시 흐르는 편이 맞다.
     *
     * 멱등하다. 같은 값을 여러 번 보내도 시각만 갱신된다 — 그 시각이 「언제 본
     * 상태인가」라서 갱신되는 게 맞다.
     */
    public function setLocationPermission(
        Project $project,
        User $user,
        LocationPermission $permission,
    ): EventParticipant {
        $participant = EventParticipant::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $participant->location_permission = $permission;
        $participant->location_permission_at = now();
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
            // M-5 — 「공유 켬 + 권한 없음」을 관제가 구분할 수 있게 한다.
            // 세 축의 조합은 모델이 «한 번만» 한다(0-8 의 교훈). 화면은 이 값으로 분기한다.
            'sharing_location' => $p->sharing_location,
            'tracking_state' => $p->trackingState($onlineThresholdSeconds)->value,
        ])->all();
    }
}
