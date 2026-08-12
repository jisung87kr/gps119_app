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
     * 관제 지도 마커 색 (hex, 대문자).
     *
     * 정본은 control-map-spec.md §2 표다. 이 메서드가 그 정본의 코드상 «단일 출처»이고,
     * 관제 SPA 는 mapMeta() → data-role-meta 로 «주입받아» 쓴다(JS 에 hex 사본 없음).
     * 색맹 대비로 색과 함께 아이콘 형태를 병용하므로(§2), 색만 바꾸면 안 된다.
     */
    public function markerColor(): string
    {
        return match ($this) {
            self::PARTICIPANT => '#6B7280',       // gray-500
            self::STAFF => '#2563EB',             // blue-600
            self::POLICE => '#1E3A8A',            // blue-900
            self::VOLUNTEER_COURSE => '#16A34A',  // green-600
            self::VOLUNTEER_MEDIC => '#F59E0B',   // amber-500
            self::PARAMEDIC => '#DC2626',         // red-600
            self::CONTROLLER => '#7C3AED',        // violet-600
        };
    }

    /**
     * 관제 지도용 역할 메타 전량 — 뷰가 JS 로 주입하는 페이로드.
     *
     * 키 순서 = enum 선언 순서 = 관제 역할 필터 표시 순서(ROLE_ORDER).
     * 아이콘 «형태»는 SVG path 라 JS(roleMeta.js)가 갖고, 여기서는 색·라벨만 넘긴다.
     */
    public static function mapMeta(): array
    {
        $meta = [];

        foreach (self::cases() as $role) {
            $meta[$role->value] = [
                'label' => $role->label(),
                'color' => $role->markerColor(),
            ];
        }

        return $meta;
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
     * 지령 수령 «자격» 여부 (구급대 / 자원봉사 구급).
     *
     * 🔑 이것은 «지령 화면·개인 채널에 들어올 수 있는가»이지 «새 지령의 배정 후보인가»가
     *    아니다. 후보는 isDispatchCandidate() 다. 둘을 한 메서드로 겸하다가, 후보를
     *    구급대로 좁히라는 요구가 오면 이미 지령을 받은 자원봉사 구급이 자기 지령 화면에서
     *    쫓겨나고(진행 중 지령이 즉시 고아가 된다) 활동화면에서도 참가자로 강등되는
     *    파급이 생긴다. 그래서 여기는 넓게 두고 후보만 좁힌다.
     */
    public function canReceiveDispatch(): bool
    {
        return in_array($this, [self::PARAMEDIC, self::VOLUNTEER_MEDIC], true);
    }

    /**
     * 새 지령의 배정 «후보» 여부 — 구급대만.
     *
     * 현장 요구(2026-08-12): 자원봉사(구급)는 지령 대상에서 빼고 구조요청 화면만 쓴다.
     */
    public function isDispatchCandidate(): bool
    {
        return $this === self::PARAMEDIC;
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
