<?php

namespace App\Enums;

/**
 * 푸시를 받을 «기기»의 종류 (mobile-app 에픽 N1).
 *
 * web 이 같은 표에 있는 이유: 상황실은 PC 웹에서 관제한다([04 §3-3]).
 * 「앱 푸시」와 「웹 푸시」는 전송 규격만 다를 뿐, 도메인에서는 둘 다
 * «이 사람에게 닿는 통로» 하나다. 통로마다 표를 나누면 수신자 조회가
 * 두 벌이 되고, 결국 한쪽만 고치는 날이 온다.
 */
enum PushPlatform: string
{
    case IOS = 'ios';
    case ANDROID = 'android';
    case WEB = 'web';

    public function label(): string
    {
        return match ($this) {
            self::IOS => 'iOS 앱',
            self::ANDROID => 'Android 앱',
            self::WEB => '웹 브라우저',
        };
    }

    /**
     * 앱(FCM) 경로인가. web 만 웹푸시(VAPID) 경로다.
     */
    public function usesFcm(): bool
    {
        return $this !== self::WEB;
    }
}
