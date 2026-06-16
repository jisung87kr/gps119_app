<?php

namespace App\Enums;

enum RequestStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * 사용자에게 보여줄 한글 라벨.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '접수 대기',
            self::IN_PROGRESS => '구조 진행중',
            self::COMPLETED => '구조 완료',
            self::CANCELLED => '요청 취소',
        };
    }

    /**
     * 상태 뱃지용 Tailwind 클래스 (배경/글자/링).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
            self::IN_PROGRESS => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
            self::COMPLETED => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
            self::CANCELLED => 'bg-slate-100 text-slate-500 ring-1 ring-slate-200',
        };
    }

    /**
     * 진행 상태 점(dot) 색상.
     */
    public function dotClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500',
            self::IN_PROGRESS => 'bg-blue-500',
            self::COMPLETED => 'bg-emerald-500',
            self::CANCELLED => 'bg-slate-400',
        };
    }

    /**
     * 처리 중(대기 또는 진행중) 여부.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::IN_PROGRESS], true);
    }
}
