<?php

namespace App\Enums;

/**
 * 관제가 보는 «이 참가자 위치가 지금 어떤 상태인가» (M-5).
 *
 * 🔑 **서버에서 파생해 내려보낸다. JS 에 사본을 두지 않는다.**
 *    N0 의 0-8 이 정확히 그 실패였다 — 역할 색·라벨이 PHP 와 JS 두 벌로 있다가
 *    어긋났고, 「PHP 가 단일 출처」라는 주석과 달리 실질 출처는 JS 였다.
 *    여기서 조합해야 할 축이 셋(의도·능력·증거)이나 되므로 화면마다 다시 조합하면
 *    같은 일이 반복된다.
 *
 * 조합하는 세 축:
 *
 *   의도  event_participants.sharing_location   사용자가 켰는가
 *   능력  event_participants.location_permission OS 가 허락했는가   ← M-5 로 추가된 축
 *   증거  last_seen_at 신선도(isOnline)          실제로 들어오는가
 *
 * 🔴 **셋을 구분하지 못하면 상황실이 오판한다.** M-5 이전에는 능력 축이 없어서
 *    「껐다」·「권한이 없다」·「네트워크가 끊겼다」가 전부 같은 «오프라인»으로 보였다.
 *    백그라운드 추적을 붙이면 이 구분이 사고를 가른다 — 참가자는 켜뒀다고 믿는데
 *    한 번도 보인 적이 없는 경우가 BLOCKED 다.
 */
enum TrackingState: string
{
    /** 정상 — 백그라운드 권한 + 최근 위치가 들어오고 있다. */
    case TRACKING = 'tracking';

    /** 권한은 충분한데 위치가 끊겼다. 네트워크·배터리·앱 종료를 의심한다. */
    case STALE = 'stale';

    /** 「앱 사용 중만」 허용 — 참가자가 화면을 닫으면 끊긴다. */
    case FOREGROUND_ONLY = 'foreground_only';

    /** 🔴 공유는 켰는데 OS 권한이 없다. 참가자는 보이는 줄 안다. */
    case BLOCKED = 'blocked';

    /** 공유를 끈 상태. 정상이다. */
    case OFF = 'off';

    /** 앱이 아니거나(웹) 권한을 보고하지 않는 구버전. 판정 불가. */
    case UNKNOWN = 'unknown';

    /**
     * 상황실이 «조치해야 하는» 상태인가.
     *
     * STALE 을 포함하지 않는다 — 잠깐의 신호 끊김은 흔하고, 그걸 경보로 올리면
     * 정작 BLOCKED 가 묻힌다.
     */
    public function needsAttention(): bool
    {
        return $this === self::BLOCKED;
    }

    public function label(): string
    {
        return match ($this) {
            self::TRACKING => '추적 중',
            self::STALE => '신호 끊김',
            self::FOREGROUND_ONLY => '앱 열려 있을 때만',
            self::BLOCKED => '위치 권한 없음',
            self::OFF => '공유 꺼짐',
            self::UNKNOWN => '알 수 없음',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::TRACKING => 'success',
            self::STALE => 'warning',
            self::FOREGROUND_ONLY => 'warning',
            self::BLOCKED => 'danger',
            self::OFF => 'muted',
            self::UNKNOWN => 'neutral',
        };
    }
}
