<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Database\QueryException;

/**
 * 소셜 계정 → 우리 회원 (네이버·카카오의 웹 콜백과 Apple 네이티브 로그인이 함께 쓴다).
 *
 * 찾는 순서: ① provider + provider_id ② 이메일(있을 때만) — 찾으면 소셜 정보를 붙인다 ③ 새로 만든다.
 */
class SocialAccountService
{
    /**
     * @param  array{name?: ?string, email?: ?string, avatar?: ?string, phone?: ?string}  $profile
     *
     * @throws Exception code 23000 — 같은 전화번호·이메일로 이미 가입된 사람이 있다
     */
    public function findOrCreate(string $provider, string $providerId, array $profile): User
    {
        $user = User::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($user) {
            return $user;
        }

        $email = $profile['email'] ?? null;

        if ($email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'avatar' => $profile['avatar'] ?? $user->avatar,
                ]);

                return $user;
            }
        }

        try {
            return User::create([
                'name' => ($profile['name'] ?? null) ?: '사용자',
                'email' => $email,
                'phone' => $profile['phone'] ?? null,
                'provider' => $provider,
                'provider_id' => $providerId,
                'avatar' => $profile['avatar'] ?? null,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new Exception('duplicate_user', 23000);
            }

            throw $e;
        }
    }
}
