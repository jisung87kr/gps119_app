<?php

namespace App\Services\Push;

use App\Enums\PushDelivery;
use App\Enums\PushPlatform;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * FCM HTTP v1 (iOS 는 FCM→APNs 경유). 앱 푸시 단일 창구.
 *
 * ⚠️ **지금은 자격증명이 없어 항상 SKIPPED 다.** FCM 프로젝트를 만들려면 스토어·
 * 인증서 명의 주체(N0-4, M-8)가 먼저 정해져야 하는데 그건 «합의» 항목이라 코드로
 * 풀 수 없다. 그래서 «설정되면 동작하는 자리»를 만들어 두고 꺼둔다 —
 * 명의가 정해지면 .env 세 줄로 켜진다.
 *
 * 액세스 토큰 발급(서비스 계정 JWT → OAuth2)은 자격증명이 생긴 뒤에 붙인다.
 * 지금 짜면 «한 번도 실행해보지 못한 인증 코드»가 남는다.
 */
class FcmSender implements PushSender
{
    public function __construct(
        private readonly ?string $projectId,
        private readonly ?string $credentialsPath,
    ) {}

    public function supports(PushPlatform $platform): bool
    {
        return $platform->usesFcm();
    }

    public function isConfigured(): bool
    {
        return ! empty($this->projectId)
            && ! empty($this->credentialsPath)
            && is_readable($this->credentialsPath);
    }

    public function send(DeviceToken $device, PushMessage $message): PushDelivery
    {
        if (! $this->isConfigured()) {
            return PushDelivery::SKIPPED;
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                    'message' => array_merge(
                        $message->toFcmPayload(),
                        ['token' => $device->token],
                    ),
                ]);
        } catch (Throwable $e) {
            report($e);

            return PushDelivery::FAILED;
        }

        if ($response->successful()) {
            return PushDelivery::DELIVERED;
        }

        // UNREGISTERED / INVALID_ARGUMENT(토큰) = 기기가 앱을 지웠거나 토큰이 갱신됐다.
        $status = $response->json('error.details.0.errorCode') ?? $response->json('error.status');

        if (in_array($status, ['UNREGISTERED', 'INVALID_ARGUMENT'], true) || $response->status() === 404) {
            return PushDelivery::INVALID;
        }

        return PushDelivery::FAILED;
    }

    /**
     * 서비스 계정 → OAuth2 액세스 토큰.
     *
     * 자격증명이 생긴 뒤에 구현한다(google/auth 또는 직접 JWT). isConfigured() 가
     * false 인 동안에는 호출되지 않으므로, 여기서 «미구현»을 감추지 않고 던진다 —
     * 조용히 빈 문자열을 돌려주면 401 을 FAILED 로 오해해 영원히 재시도한다.
     */
    private function accessToken(): string
    {
        throw new \LogicException(
            'FCM 액세스 토큰 발급이 아직 구현되지 않았습니다. '.
            '스토어·FCM 명의 주체(N0-4) 확정 후 서비스 계정 자격증명과 함께 구현하세요.'
        );
    }
}
