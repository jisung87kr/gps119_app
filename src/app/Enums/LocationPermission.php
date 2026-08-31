<?php

namespace App\Enums;

/**
 * 앱이 보고하는 «OS 위치 권한» 상태 (M-5).
 *
 * 🔑 **`sharing_location` 과 다른 축이다.** 그쪽은 「사용자가 켰는가」(의도)이고
 *    이쪽은 「OS 가 허락했는가」(능력)다. 둘이 어긋난 상태 — 켜뒀는데 권한이 없는 —
 *    가 정확히 M-5 가 드러내려는 것이다. 참가자는 「보이겠지」라고 믿고 상황실은
 *    「신호 끊김」으로 읽는데, 실제로는 **한 번도 보인 적이 없다.**
 *
 * ⚠️ **이 값은 ping 에 실어 보낼 수 없다.** 권한이 끊기면 ping 도 끊기기 때문이다.
 *    앱이 포그라운드 복귀·공유 토글·OS 권한 변경 콜백 시점에 «따로» 보고한다.
 *
 * 📌 경계에서 한 번만 매핑한다(양 OS 의 값이 서로 다르다):
 *      iOS  authorizedAlways      → always
 *           authorizedWhenInUse   → when_in_use
 *           denied · restricted   → denied
 *           notDetermined         → not_determined
 *      Android  ACCESS_BACKGROUND_LOCATION 허용 → always
 *               FINE/COARSE 만 허용             → when_in_use
 *               거부                            → denied
 *
 *    iOS 의 `restricted`(스크린타임·MDM)를 `denied` 로 접는 이유는 **우리가 할 수 있는
 *    안내가 같기 때문**이다. 값을 늘려도 화면이 달라지지 않으면 늘리지 않는다.
 *    기기 정책 때문에 영영 못 켜는 경우를 따로 안내해야 할 «두 번째 근거»가 생기면
 *    그때 쪼갠다.
 */
enum LocationPermission: string
{
    /** 항상 허용 — 백그라운드 추적 가능. */
    case ALWAYS = 'always';

    /** 앱 사용 중만 — 화면을 닫으면 끊긴다. */
    case WHEN_IN_USE = 'when_in_use';

    /** 거부(iOS restricted 포함). */
    case DENIED = 'denied';

    /** 기기의 위치 서비스 자체가 꺼져 있다. 앱 권한과 별개다. */
    case SERVICES_OFF = 'services_off';

    /** 아직 묻지 않았다. */
    case NOT_DETERMINED = 'not_determined';

    /**
     * 화면이 꺼져도 위치가 오는가.
     *
     * 🔴 백그라운드 추적의 «유일한» 충분조건이다. WHEN_IN_USE 로는 안 된다 —
     *    참가자가 폰을 주머니에 넣는 순간 끊긴다.
     */
    public function allowsBackground(): bool
    {
        return $this === self::ALWAYS;
    }

    /**
     * 위치를 «전혀» 못 얻는 상태인가.
     *
     * NOT_DETERMINED 를 여기 넣는다. 공유를 켠 시점에는 이미 물었어야 하므로,
     * 그 조합은 「아직 안 물었다」가 아니라 **「물었는데 배선이 깨졌다」**이다.
     */
    public function blocksTracking(): bool
    {
        return match ($this) {
            self::DENIED, self::SERVICES_OFF, self::NOT_DETERMINED => true,
            self::ALWAYS, self::WHEN_IN_USE => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ALWAYS => '항상 허용',
            self::WHEN_IN_USE => '앱 사용 중만',
            self::DENIED => '거부됨',
            self::SERVICES_OFF => '위치 서비스 꺼짐',
            self::NOT_DETERMINED => '미요청',
        };
    }
}
