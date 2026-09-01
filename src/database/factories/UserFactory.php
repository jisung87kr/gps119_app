<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            // 전화번호 인증 도메인: phone 은 NOT NULL + unique. 010 + 8자리 유니크 시퀀스.
            'phone' => '010'.fake()->unique()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * 필수 약관에 동의한 사용자.
     *
     * 🔑 **기본값을 «동의함»으로 두지 않는다.** 동의가 없는 계정이 실제로 존재하고
     *    (가입 폼 도입 전에 만들어진 계정), 위치 수집은 바로 그들을 막아야 한다.
     *    기본을 동의로 두면 그 경로를 아무 테스트도 지나가지 않게 된다.
     */
    public function consented(): static
    {
        return $this->afterCreating(function (\App\Models\User $user) {
            app(\App\Services\ConsentService::class)
                ->record($user, \App\Enums\ConsentType::required());
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
