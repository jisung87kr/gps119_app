<?php

namespace App\Enums;

enum RequestPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    /**
     * 사용자에게 보여줄 한글 라벨.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => '낮음',
            self::MEDIUM => '보통',
            self::HIGH => '높음',
            self::CRITICAL => '긴급',
        };
    }

    /**
     * 사용자 화면 배지 톤 (x-ui.badge 의 tone 프롭).
     * 레드는 긴급 전용이므로 CRITICAL/HIGH 에만 danger 를 쓴다.
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::LOW => 'muted',
            self::MEDIUM => 'neutral',
            self::HIGH, self::CRITICAL => 'danger',
        };
    }

    /**
     * 배지 배경을 채울지 여부 — 긴급(CRITICAL)만 채운다.
     */
    public function badgeFilled(): bool
    {
        return $this === self::CRITICAL;
    }

    /**
     * 사용자 화면 배지 아이콘 (x-ui.icon 이름). 낮음/보통은 아이콘 없이 텍스트만.
     */
    public function badgeIcon(): ?string
    {
        return match ($this) {
            self::HIGH, self::CRITICAL => 'alert-circle',
            self::LOW, self::MEDIUM => null,
        };
    }

    /**
     * 우선순위 뱃지용 Tailwind 클래스 — 관리자 백오피스(통계) 전용 구 팔레트.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::LOW => 'bg-slate-100 text-slate-600',
            self::MEDIUM => 'bg-sky-50 text-sky-700',
            self::HIGH => 'bg-orange-50 text-orange-700',
            self::CRITICAL => 'bg-red-50 text-red-700',
        };
    }
}
