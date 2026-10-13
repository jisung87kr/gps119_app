<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AppleIdentityVerifier;
use App\Services\ConsentService;
use App\Services\SocialAccountService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * Sign in with Apple — iOS 앱 셸의 «네이티브» 시트에서만 온다 (App Store 심사 4.8, 2026-10-13).
 *
 * 흐름: ① nonce 발급(세션) → ② 앱이 ASAuthorization 에 그 nonce 를 실어 identityToken 을 받는다
 *       → ③ 여기서 검증하고 로그인. 웹 리다이렉트(Socialite) 방식이 아니다 — 그쪽은 셸의
 *       allowNavigation·form_post 콜백·SameSite 쿠키를 전부 건드려야 한다.
 */
class AppleSignInController extends Controller
{
    private const NONCE_KEY = 'apple_signin_nonce';

    public function nonce(Request $request): JsonResponse
    {
        $nonce = Str::random(40);
        $request->session()->put(self::NONCE_KEY, $nonce);

        return response()->success(['nonce' => $nonce]);
    }

    public function login(
        Request $request,
        AppleIdentityVerifier $verifier,
        SocialAccountService $accounts,
        ConsentService $consent,
    ): JsonResponse {
        $data = $request->validate([
            'identity_token' => ['required', 'string', 'max:4096'],
            // 이름은 «처음 동의할 때 한 번만» 온다(토큰에는 없다). 위조돼도 표시 이름일 뿐이다.
            'given_name' => ['nullable', 'string', 'max:50'],
            'family_name' => ['nullable', 'string', 'max:50'],
        ]);

        // 일회용 — 성공·실패와 무관하게 꺼내서 지운다.
        $nonce = (string) $request->session()->pull(self::NONCE_KEY, '');

        try {
            $identity = $verifier->verify($data['identity_token'], $nonce);
        } catch (UnexpectedValueException $e) {
            Log::warning('Apple 로그인 토큰 거절: '.$e->getMessage());

            return response()->error('Apple 로그인을 확인하지 못했습니다. 다시 시도해 주세요.', 422);
        }

        // 한국식 이름 순서(성+이름). 둘 다 없으면 SocialAccountService 가 「사용자」로 채운다.
        $name = trim(($data['family_name'] ?? '').($data['given_name'] ?? '')) ?: null;

        try {
            $user = $accounts->findOrCreate('apple', $identity['sub'], [
                'name' => $name,
                // 검증되지 않은 이메일로는 기존 계정에 붙이지 않는다(남의 계정 탈취 경로).
                'email' => $identity['email_verified'] ? $identity['email'] : null,
            ]);
        } catch (Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->error('이미 가입된 사용자입니다. 전화번호로 로그인해 주세요.', 409);
            }

            throw $e;
        }

        Auth::login($user);
        $request->session()->regenerate();

        // 🔴 SocialController 와 같은 이유 — 회원가입 폼을 거치지 않았으므로 동의부터 받는다.
        $redirect = $consent->missingRequired($user)
            ? route('consent.show')
            : route('request.create');

        return response()->success(['redirect' => $redirect]);
    }
}
