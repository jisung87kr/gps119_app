<?php

namespace App\Services\Push;

use App\Enums\PushDelivery;
use App\Enums\PushPlatform;
use App\Models\DeviceToken;

/**
 * 하나의 전송 규격(웹푸시 / FCM).
 *
 * 구현체는 «보낸다»만 한다 — 토큰 폐기·로깅·수신자 판별은 PushService 의 몫이다.
 * 그래야 규격이 하나 더 늘어도(APNs 직접 연동 등) 정책이 흩어지지 않는다.
 */
interface PushSender
{
    /**
     * 이 전송 규격이 처리하는 플랫폼인가.
     */
    public function supports(PushPlatform $platform): bool;

    /**
     * 자격증명이 갖춰졌는가.
     *
     * 안 갖춰진 것은 «오류»가 아니라 «아직 안 켰다»다 — FCM 은 스토어 계정 명의
     * (N0-4)가 정해져야 프로젝트를 만들 수 있어서, 그때까지는 정상적으로 꺼져 있다.
     */
    public function isConfigured(): bool;

    public function send(DeviceToken $device, PushMessage $message): PushDelivery;
}
