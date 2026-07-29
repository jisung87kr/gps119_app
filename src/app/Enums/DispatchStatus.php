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

    public function label(): string
    {
        return match ($this) {
            self::ASSIGNED => '배정',
            self::ACCEPTED => '수락',
            self::EN_ROUTE => '출동',
            self::ARRIVED => '도착',
            self::COMPLETED => '완료',
            self::REJECTED => '거절',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::ASSIGNED => 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',
            self::ACCEPTED => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
            self::EN_ROUTE => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
            self::ARRIVED => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
            self::COMPLETED => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
            self::REJECTED => 'bg-slate-100 text-slate-500 ring-1 ring-slate-200',
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
        };
    }

    /** 활성(진행) 지령: 배정/수락/출동/도착. */
    public function isActive(): bool
    {
        return in_array($this, [self::ASSIGNED, self::ACCEPTED, self::EN_ROUTE, self::ARRIVED], true);
    }

    /** 종료 지령: 완료/거절. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::REJECTED], true);
    }

    /**
     * 현재 상태에서 허용되는 목표 상태 목록 (전이표 그대로, SPEC-02d).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::ASSIGNED => [self::ACCEPTED, self::REJECTED],
            self::ACCEPTED => [self::EN_ROUTE, self::REJECTED],
            self::EN_ROUTE => [self::ARRIVED],
            self::ARRIVED => [self::COMPLETED],
            self::COMPLETED, self::REJECTED => [], // terminal
        };
    }

    /** 목표 상태로의 전이 가능 여부. */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * 이 전이가 연결 requests 에 강제할 상태(없으면 null). (SPEC-02d 동기화 규칙)
     */
    public function syncsRequestStatus(): ?RequestStatus
    {
        return match ($this) {
            self::ACCEPTED, self::EN_ROUTE, self::ARRIVED => RequestStatus::IN_PROGRESS,
            self::COMPLETED => RequestStatus::COMPLETED,
            self::REJECTED, self::ASSIGNED => null, // rejected: 신고 무변경(재지령 대기)
        };
    }
}
