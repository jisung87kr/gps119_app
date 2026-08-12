<?php

namespace App\Enums;

/**
 * 지령(출동) 상태 + 전이 계약 (SPEC-02d).
 */
enum DispatchStatus: string
{
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case EN_ROUTE = 'en_route';
    case ARRIVED = 'arrived';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    /**
     * 상황실이 발령을 «회수»한 상태.
     *
     * 🔑 REJECTED 를 재사용하지 않는 이유 — 거절은 「대원이 못 간다」(대원 책임, 사유 필수)이고
     *    회수는 「관제가 뺀다」(관제 판단)다. 한 값에 합치면 행사 리포트의 거절률이 영구히
     *    오염되고, 이미 내보낸 CSV 는 되돌릴 수 없다. (ADR-0007)
     */
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ASSIGNED => '배정',
            self::ACCEPTED => '수락',
            self::EN_ROUTE => '출동',
            self::ARRIVED => '도착',
            self::COMPLETED => '완료',
            self::REJECTED => '거절',
            self::CANCELLED => '회수',
        };
    }

    /**
     * 사용자 화면(구급대원 지령 앱) 배지 톤 — x-ui.badge 의 tone 프롭.
     * 진행 단계는 brand, 배정 대기는 warning, 완료는 success, 거절은 muted.
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::ASSIGNED => 'warning',
            self::ACCEPTED, self::EN_ROUTE, self::ARRIVED => 'brand',
            self::COMPLETED => 'success',
            self::REJECTED, self::CANCELLED => 'muted',
        };
    }

    /**
     * 사용자 화면 배지 아이콘 (x-ui.icon 이름).
     */
    public function badgeIcon(): string
    {
        return match ($this) {
            self::ASSIGNED => 'bell',
            self::ACCEPTED => 'check-circle',
            self::EN_ROUTE => 'ambulance',
            self::ARRIVED => 'pin',
            self::COMPLETED => 'check-circle',
            self::REJECTED, self::CANCELLED => 'x-circle',
        };
    }

    /**
     * 지령 뱃지용 Tailwind 클래스 — 관리자 백오피스 전용 구 팔레트.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::ASSIGNED => 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',
            self::ACCEPTED => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
            self::EN_ROUTE => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
            self::ARRIVED => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
            self::COMPLETED => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
            self::REJECTED => 'bg-slate-100 text-slate-500 ring-1 ring-slate-200',
            self::CANCELLED => 'bg-orange-50 text-orange-700 ring-1 ring-orange-200',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::ASSIGNED => 'bg-violet-500',
            self::ACCEPTED => 'bg-blue-500',
            self::EN_ROUTE => 'bg-sky-500',
            self::ARRIVED => 'bg-indigo-500',
            self::COMPLETED => 'bg-emerald-500',
            self::REJECTED => 'bg-slate-400',
            self::CANCELLED => 'bg-orange-400',
        };
    }

    /** 활성(진행) 지령: 배정/수락/출동/도착. */
    public function isActive(): bool
    {
        return in_array($this, self::activeCases(), true);
    }

    /**
     * 활성 상태 목록 — 스코프·관계에서 쓰는 단일 출처.
     *
     * 예전에는 이 4개 값이 Dispatch::scopeActive 와 Request::activeDispatch 에 각각
     * 하드코딩돼 있었다. 상태가 하나 늘 때 한쪽만 고치면 «활성인데 관계에는 안 잡히는»
     * 지령이 생기고, 그건 화면에서 지령이 통째로 사라지는 것으로 나타난다.
     *
     * @return array<int, self>
     */
    public static function activeCases(): array
    {
        return [self::ASSIGNED, self::ACCEPTED, self::EN_ROUTE, self::ARRIVED];
    }

    /**
     * 활성 상태의 문자열 값 목록(쿼리용).
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::activeCases());
    }

    /**
     * 「현장에서 실제로 대응 중」인 상태: 수락 이후.
     *
     * 배정(assigned)은 아직 대원이 응답하지 않은 «보낸» 상태라 여기 들어가지 않는다.
     * 신고를 in_progress 로 올릴지 판정하는 집계(DispatchService)가 이 경계를 쓴다.
     *
     * @return array<int, string>
     */
    public static function respondingValues(): array
    {
        return [self::ACCEPTED->value, self::EN_ROUTE->value, self::ARRIVED->value];
    }

    /** 종료 지령: 완료/거절/회수. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::REJECTED, self::CANCELLED], true);
    }

    /**
     * 현재 상태에서 허용되는 목표 상태 목록 (전이표 그대로, SPEC-02d).
     *
     * 🔑 ARRIVED 에서는 회수할 수 없다 — 대원이 이미 현장에 도착했으면 오인신고라도
     *    「도착해서 확인하고 종결했다」가 기록상 정확하다. 회수로 지우면 그 이동이 없던 일이 된다.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::ASSIGNED => [self::ACCEPTED, self::REJECTED, self::CANCELLED],
            self::ACCEPTED => [self::EN_ROUTE, self::REJECTED, self::CANCELLED],
            self::EN_ROUTE => [self::ARRIVED, self::CANCELLED],
            self::ARRIVED => [self::COMPLETED],
            self::COMPLETED, self::REJECTED, self::CANCELLED => [], // terminal
        };
    }

    /** 목표 상태로의 전이 가능 여부. */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * 이 전이가 연결 requests 에 «단독으로» 강제할 상태(없으면 null). (SPEC-02d 동기화 규칙)
     *
     * 🔑 다중 배차(ADR-0007 D4) 이후 이 함수는 신고 상태의 «권위»가 아니다 — 한 신고에
     *    주담당 1명 + 보조 N명이 붙으므로, 「보조가 완료를 눌렀다」와 「주담당이 완료를
     *    눌렀다」는 같은 전이인데 신고에 미치는 영향이 다르다. 전이 하나만 보는 함수는
     *    형제 지령을 볼 수 없어 그 구분을 할 수 없다.
     *    실제 판정은 활성 지령 «집계»를 보는 DispatchService::deriveRequestStatus() 가 한다.
     *
     * @deprecated ADR-0007 D4 — DispatchService::deriveRequestStatus() 로 대체. 전이 1건의
     *             의도를 읽는 용도로만 남겨 둔다.
     */
    public function syncsRequestStatus(): ?RequestStatus
    {
        return match ($this) {
            self::ACCEPTED, self::EN_ROUTE, self::ARRIVED => RequestStatus::IN_PROGRESS,
            self::COMPLETED => RequestStatus::COMPLETED,
            self::REJECTED, self::ASSIGNED => null, // rejected: 신고 무변경(재지령 대기)
            // 회수는 신고를 «대기»로 되돌린다. null 로 두면 아무도 안 가는데 신고만
            // in_progress 로 남는 좀비가 된다. (신고 자체가 취소된 경우는
            // DispatchService::syncRequestStatus 의 종료상태 가드가 되살리는 것을 막는다.)
            self::CANCELLED => RequestStatus::PENDING,
        };
    }
}
