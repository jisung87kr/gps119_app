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
     * 우선순위 뱃지용 Tailwind 클래스.
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
