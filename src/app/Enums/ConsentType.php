<?php

namespace App\Enums;

/**
 * 받아야 하는 동의의 종류.
 *
 * 🔴 **위치기반서비스 이용약관은 개인정보처리방침과 «분리»해서 받는다.**
 *    위치정보법은 개인위치정보 수집에 별도 동의를 요구한다 — 한 체크박스로 묶으면
 *    동의를 받은 것으로 보지 않는다.
 */
enum ConsentType: string
{
    case PRIVACY = 'privacy';
    case LOCATION_TERMS = 'location_terms';

    public function label(): string
    {
        return match ($this) {
            self::PRIVACY => '개인정보처리방침',
            self::LOCATION_TERMS => '위치기반서비스 이용약관',
        };
    }

    /** 동의 화면에서 링크할 곳 */
    public function routeName(): string
    {
        return match ($this) {
            self::PRIVACY => 'legal.privacy',
            self::LOCATION_TERMS => 'legal.location-terms',
        };
    }

    /** 지금 유효한 판. 화면의 「시행일」과 같은 값이어야 한다. */
    public function currentVersion(): string
    {
        return config('legal.versions.'.$this->value);
    }

    /** 가입에 «필수»인 것들. 둘 다 필수다 — 선택 동의는 아직 없다. */
    public static function required(): array
    {
        return [self::PRIVACY, self::LOCATION_TERMS];
    }
}
