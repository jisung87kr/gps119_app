<?php

namespace App\Services\Push;

use App\Enums\PushDelivery;
use App\Enums\PushPlatform;
use App\Models\DeviceToken;
use Closure;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * FCM HTTP v1 (iOS 는 FCM→APNs 경유). 앱 푸시 단일 창구.
 *
 * 자격증명(서비스 계정 JSON)이 없으면 조용히 SKIPPED 다 — «오류»가 아니라
 * «아직 안 켰다»이고, 알림 실패가 구조요청 접수를 막으면 안 된다.
 *
 * 🔴 **앱 푸시에는 우회로가 없다.** 앱 웹뷰에는 서비스워커가 없어서 웹 푸시(VAPID)로
 *    대체할 수 없다(M-24). 즉 이 클래스가 죽으면 앱 사용자는 알림을 «전혀» 못 받는다.
 */
class FcmSender implements PushSender
{
    /** FCM HTTP v1 발송에 필요한 최소 스코프. */
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** 액세스 토큰 캐시 키. 자격증명 경로별로 분리한다(개발/운영 동시 사용 대비). */
    private readonly string $cacheKey;

    /**
     * @param  Closure|null  $tokenFetcher  테스트용 주입점. 반환은 fetchAuthToken() 과 같은
     *                                      배열 형태: ['access_token' => string, 'expires_in' => int]
     */
    public function __construct(
        private readonly ?string $projectId,
        private readonly ?string $credentialsPath,
        private readonly ?Closure $tokenFetcher = null,
    ) {
        $this->cacheKey = 'push.fcm.token:'.sha1((string) $this->credentialsPath);
    }

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

        // ⚠️ 토큰 발급 실패도 이 try 에 걸려 FAILED 로 나간다. 의도한 것이다 —
        //    PushService 는 기기 목록을 돌며 send() 를 부르는데(PushService::71),
        //    여기서 예외를 던지면 «자격증명 하나 때문에 그 배치의 나머지 수신자가
        //    통째로 누락»된다. 대신 report() 로 반드시 드러나게 한다.
        //
        // 🔴 **알려진 한계**: 자격증명이 상한 상태는 재시도해도 영영 성공하지 않는데
        //    FAILED 는 재시도 대상이다. PushDelivery 에 «설정 오류» 구분이 없어서
        //    지금은 이렇게 둔다. 로그에는 남으므로 조용히 사라지지는 않는다.
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

        // 🔑 401/403 은 «토큰이 상했다»는 뜻이다. 캐시를 비우지 않으면 남은 TTL 동안
        //    상한 토큰을 계속 써서 재시도가 전부 같은 이유로 실패한다 — 재시도가
        //    의미를 가지려면 다음 시도에서 새 토큰을 받아야 한다.
        if (in_array($response->status(), [401, 403], true)) {
            Cache::forget($this->cacheKey);

            return PushDelivery::FAILED;
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
     * 🔑 **캐시한다.** 구글 토큰은 1시간 유효한데, 캐시가 없으면 «푸시 한 건마다»
     *    구글에 토큰을 새로 요청한다. 신고 하나가 수신자 수만큼 발송되므로 대량
     *    행사에서 그대로 지연·쿼터 문제로 돌아온다.
     *
     * TTL 은 고정값이 아니라 응답의 expires_in 에서 여유(60초)를 뺀 값을 쓴다 —
     * 구글이 만료를 바꾸면 고정값 쪽이 조용히 어긋난다.
     */
    private function accessToken(): string
    {
        $cached = Cache::get($this->cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = ($this->tokenFetcher ?? $this->defaultTokenFetcher())();

        if (empty($token['access_token']) || ! is_string($token['access_token'])) {
            // 빈 토큰을 그대로 흘리면 401 이 나고, 그건 FAILED(재시도)로 분류돼
            // «영영 성공 못 할 요청»을 계속 재시도하게 된다. 여기서 끊는다.
            throw new RuntimeException('FCM 액세스 토큰을 발급받지 못했습니다. 서비스 계정 자격증명을 확인하세요.');
        }

        $ttl = max(60, (int) ($token['expires_in'] ?? 3600) - 60);
        Cache::put($this->cacheKey, $token['access_token'], $ttl);

        return $token['access_token'];
    }

    /**
     * 실제 발급기. 서비스 계정 JWT → OAuth2 교환은 google/auth 에 맡긴다 —
     * 직접 짜면 서명·클록스큐·만료 경계에서 틀리고, 틀린 티가 늦게 난다.
     */
    private function defaultTokenFetcher(): Closure
    {
        return fn (): array => (new ServiceAccountCredentials(self::SCOPE, $this->credentialsPath))
            ->fetchAuthToken();
    }
}
