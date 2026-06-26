<?php

namespace App\Enums;

/**
 * 신고 유형 (SPEC-02c).
 *
 * UI 상황 버튼 → 유형. priority 는 type 에서 defaultPriority() 로 자동 매핑하되
 * 상황실 수동 상향 가능(priority 컬럼 유지).
 */
enum RequestType: string
{
    case ACCIDENT = 'accident';   // 사고
    case BREAKDOWN = 'breakdown'; // 고장
    case OTHER = 'other';         // 기타
    case EMERGENCY = 'emergency'; // 긴급전화

    public function label(): string
    {
        return match ($this) {
            self::ACCIDENT => '사고',
            self::BREAKDOWN => '고장',
            self::OTHER => '기타',
            self::EMERGENCY => '긴급전화',
        };
    }

    /**
     * 유형별 기본 우선순위 (확정 매핑).
     * EMERGENCY→CRITICAL, ACCIDENT→HIGH, BREAKDOWN→MEDIUM, OTHER→LOW.
     */
    public function defaultPriority(): RequestPriority
    {
        return match ($this) {
            self::EMERGENCY => RequestPriority::CRITICAL,
            self::ACCIDENT => RequestPriority::HIGH,
            self::BREAKDOWN => RequestPriority::MEDIUM,
            self::OTHER => RequestPriority::LOW,
        };
    }

    /**
     * 관제 지도 신고 핀 아이콘(roleMeta/프론트 lookup 키).
     */
    public function markerIcon(): string
    {
        return match ($this) {
            self::ACCIDENT => 'accident',
            self::BREAKDOWN => 'breakdown',
            self::OTHER => 'info',
            self::EMERGENCY => 'phone',
        };
    }

    /**
     * 유형 뱃지용 Tailwind 클래스 (기존 Enum 패턴 일관성).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::ACCIDENT => 'bg-orange-50 text-orange-700 ring-1 ring-orange-200',
            self::BREAKDOWN => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
            self::OTHER => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
            self::EMERGENCY => 'bg-red-50 text-red-700 ring-1 ring-red-200',
        };
    }
}
