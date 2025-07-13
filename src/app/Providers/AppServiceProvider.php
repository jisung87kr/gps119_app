<?php

namespace App\Providers;

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
        //
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
