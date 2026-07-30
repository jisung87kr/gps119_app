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
     * 사용자 화면 배지 톤 (x-ui.badge 의 tone 프롭).
     *
     * 디자인 시스템 "Ink + Brand": 배지는 흰 배경 + ink-200 테두리가 기본이고
     * 색은 아이콘·텍스트에만 쓴다. 그래서 클래스 문자열이 아니라 톤/아이콘만 넘긴다.
     * (아래 badgeClasses() 는 관리자 백오피스가 계속 쓰는 구 팔레트 — 건드리지 말 것)
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::IN_PROGRESS => 'neutral',
            self::COMPLETED => 'success',
            self::CANCELLED => 'muted',
        };
    }

    /**
     * 사용자 화면 배지 아이콘 (x-ui.icon 이름).
     */
    public function badgeIcon(): string
    {
        return match ($this) {
            self::PENDING => 'clock',
            self::IN_PROGRESS => 'ambulance',
            self::COMPLETED => 'check-circle',
            self::CANCELLED => 'x-circle',
        };
    }

    /**
     * 상태 뱃지용 Tailwind 클래스 (배경/글자/링) — 관리자 백오피스 전용 구 팔레트.
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
