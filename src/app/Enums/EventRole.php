<?php

namespace App\Enums;

/**
 * 행사(프로젝트) 내 참가자 역할 (SPEC-02a).
 *
 * 시스템 전역 역할(spatie: admin/rescuer/user)과는 별개의 "행사 내 역할"이다.
 * 채널 인가·지령 배정 적격 판정에 사용한다.
 */
enum EventRole: string
{
    case PARTICIPANT = 'participant';            // 참가자
    case STAFF = 'staff';                        // 운영진
    case POLICE = 'police';                      // 경찰
    case VOLUNTEER_COURSE = 'volunteer_course';  // 자원봉사자(코스)
    case VOLUNTEER_MEDIC = 'volunteer_medic';    // 자원봉사자(구급)
    case PARAMEDIC = 'paramedic';                // 구급대
    case CONTROLLER = 'controller';              // 상황실

    /**
     * 사용자에게 보여줄 한글 라벨.
     */
    public function label(): string
    {
        return match ($this) {
            self::PARTICIPANT => '참가자',
            self::STAFF => '운영진',
            self::POLICE => '경찰',
            self::VOLUNTEER_COURSE => '자원봉사자(코스)',
            self::VOLUNTEER_MEDIC => '자원봉사자(구급)',
            self::PARAMEDIC => '구급대',
            self::CONTROLLER => '상황실',
        };
    }

    /**
     * 관제 지도 마커 색 (hex).
     */
    public function markerColor(): string
    {
        return match ($this) {
            self::PARTICIPANT => '#6b7280',       // gray-500
            self::STAFF => '#0ea5e9',             // sky-500
            self::POLICE => '#1d4ed8',            // blue-700
            self::VOLUNTEER_COURSE => '#14b8a6',  // teal-500
            self::VOLUNTEER_MEDIC => '#f59e0b',   // amber-500
            self::PARAMEDIC => '#ef4444',         // red-500
            self::CONTROLLER => '#7c3aed',        // violet-600
        };
    }

    /**
     * 사용자 화면 배지 톤 (x-ui.badge 의 tone 프롭).
     * 운영 권한이 있는 역할은 brand, 일반 참가자는 muted.
     */
    public function badgeTone(): string
    {
        return match ($this) {
            self::PARTICIPANT => 'muted',
            self::STAFF, self::POLICE, self::VOLUNTEER_COURSE,
            self::VOLUNTEER_MEDIC, self::PARAMEDIC, self::CONTROLLER => 'brand',
        };
    }

    /**
     * 사용자 화면 아이콘 (x-ui.icon 이름).
     */
    public function icon(): string
    {
        return match ($this) {
            self::PARAMEDIC, self::VOLUNTEER_MEDIC => 'ambulance',
            self::CONTROLLER => 'bell',
            self::VOLUNTEER_COURSE => 'pin',
            self::PARTICIPANT, self::STAFF, self::POLICE => 'user',
        };
    }

    /**
     * 역할 뱃지용 Tailwind 클래스 — 관리자 백오피스 전용 구 팔레트.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PARTICIPANT => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
            self::STAFF => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
            self::POLICE => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
            self::VOLUNTEER_COURSE => 'bg-teal-50 text-teal-700 ring-1 ring-teal-200',
            self::VOLUNTEER_MEDIC => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
            self::PARAMEDIC => 'bg-red-50 text-red-700 ring-1 ring-red-200',
            self::CONTROLLER => 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',
        };
    }

    /**
     * 지령 수령 가능 역할 여부 (구급대 / 자원봉사 구급).
     */
    public function canReceiveDispatch(): bool
    {
        return in_array($this, [self::PARAMEDIC, self::VOLUNTEER_MEDIC], true);
    }

    /**
     * 지령 발령(배정) 가능 역할 여부 (상황실).
     * 시스템 admin 은 EventRole 밖의 전역 권한이므로 별도 OR 조건으로 통과.
     */
    public function canDispatch(): bool
    {
        return $this === self::CONTROLLER;
    }

    /**
     * 관제 화면 열람 가능 역할 여부 (상황실).
     * 시스템 admin 은 별도 OR 조건으로 통과.
     */
    public function canViewControl(): bool
    {
        return $this === self::CONTROLLER;
    }
}
