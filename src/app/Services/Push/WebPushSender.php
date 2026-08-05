<?php

namespace App\Services\Push;

use App\Enums\PushDelivery;
use App\Enums\PushPlatform;
use App\Models\DeviceToken;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * 웹 푸시(VAPID). 브라우저 → 서비스워커.
 *
 * 상황실은 PC 웹에서 관제하므로([04 §3-3]) 이 경로가 앱 푸시보다 먼저 필요하다.
 * 앱(N2) 없이도 N1 을 끝까지 검증할 수 있게 해주는 것도 이 경로다.
 */
class WebPushSender implements PushSender
{
    public function __construct(
        private readonly ?string $publicKey,
        private readonly ?string $privateKey,
        private readonly string $subject,
    ) {}

    public function supports(PushPlatform $platform): bool
    {
        return $platform === PushPlatform::WEB;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->publicKey) && ! empty($this->privateKey);
    }

    public function send(DeviceToken $device, PushMessage $message): PushDelivery
    {
        if (! $this->isConfigured()) {
            return PushDelivery::SKIPPED;
        }

        $keys = $device->keys ?? [];
        if (empty($keys['p256dh']) || empty($keys['auth'])) {
            // 키 없는 웹 구독은 복구 불가 — 암호화 자체가 불가능하다. 폐기 대상.
            return PushDelivery::INVALID;
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => $this->subject,
                'publicKey' => $this->publicKey,
                'privateKey' => $this->privateKey,
            ]]);

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $device->token,
                    'publicKey' => $keys['p256dh'],
                    'authToken' => $keys['auth'],
                ]),
                json_encode($message->toWebPayload(), JSON_UNESCAPED_UNICODE),
            );
        } catch (Throwable $e) {
            // 삼키지 않는다 — 값으로 돌려주고 판단은 PushService 가 한다.
            report($e);

            return PushDelivery::FAILED;
        }

        if ($report->isSuccess()) {
            return PushDelivery::DELIVERED;
        }

        // 404/410 = 구독이 죽었다(브라우저 데이터 삭제·구독 해제). 재시도해도 영영 안 간다.
        return $report->isSubscriptionExpired()
            ? PushDelivery::INVALID
            : PushDelivery::FAILED;
    }
}
