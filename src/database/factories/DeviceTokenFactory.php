<?php

namespace Database\Factories;

use App\Enums\PushPlatform;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeviceToken>
 */
class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    public function definition(): array
    {
        $token = 'https://fcm.googleapis.com/wp/'.Str::random(64);

        return [
            'user_id' => User::factory(),
            'platform' => PushPlatform::WEB,
            'token' => $token,
            'token_hash' => DeviceToken::hashFor($token),
            // 실제 브라우저가 만드는 형식(비압축 P-256 점 65바이트 / 시크릿 16바이트).
            'keys' => [
                'p256dh' => 'BLBp8HPFqpO7-5A5LYAst0PpZhMm_vBliL_ivSQ2ovTgNNcXDkIM9HHmCB2qXgXmIfiQzStsvGiP9VFA_55rJ3M',
                'auth' => 'WSQQVi70R0dbW8G6Ztj6kQ',
            ],
            'app_version' => null,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function android(): static
    {
        return $this->state(function () {
            $token = Str::random(163);

            return [
                'platform' => PushPlatform::ANDROID,
                'token' => $token,
                'token_hash' => DeviceToken::hashFor($token),
                'keys' => null,
                'app_version' => '1.0.0',
            ];
        });
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()->subDay()]);
    }
}
