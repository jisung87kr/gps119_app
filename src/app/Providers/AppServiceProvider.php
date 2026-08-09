<?php

namespace App\Providers;

use App\Services\Push\BadgeCounter;
use App\Services\Push\FcmSender;
use App\Services\Push\WebPushSender;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 푸시 발송기 조립 (mobile-app N1).
        // 순서가 곧 우선순위다 — 한 플랫폼을 여러 전송기가 지원하게 되면 앞선 것이 이긴다.
        // FCM 은 자격증명(N0-4 명의 확정 후)이 생기기 전까지 isConfigured()=false 라
        // 앱 토큰이 있어도 조용히 건너뛴다.
        $this->app->singleton(PushService::class, function ($app) {
            return new PushService([
                new WebPushSender(
                    config('push.vapid.public_key'),
                    config('push.vapid.private_key'),
                    config('push.vapid.subject'),
                ),
                new FcmSender(
                    config('push.fcm.project_id'),
                    config('push.fcm.credentials'),
                ),
            ], $app->make(BadgeCounter::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Response::macro('success', function ($data = null, string $message = null, int $status = 200): JsonResponse {
            $response = [
                'success' => true,
            ];

            if ($message) {
                $response['message'] = $message;
            }

            if ($data !== null) {
                $response['data'] = $data;
            }

            return response()->json($response, $status);
        });

        Response::macro('error', function (string $message, int $status = 400, $errors = null): JsonResponse {
            $response = [
                'success' => false,
                'message' => $message,
            ];

            if ($errors !== null) {
                $response['errors'] = $errors;
            }

            return response()->json($response, $status);
        });

        // Register Socialite providers
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('naver', \SocialiteProviders\Naver\Provider::class);
        });

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('kakao', \SocialiteProviders\Kakao\KakaoProvider::class);
        });
    }
}
