<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ConsentService;
use App\Services\SocialAccountService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{
    /**
     * Redirect to social provider
     */
    public function redirect($driver)
    {
        return Socialite::driver($driver)->redirect();
    }

    /**
     * Handle social provider callback
     */
    public function callback($driver)
    {
        try {
            $socialUser = Socialite::driver($driver)->user();

            $user = app(SocialAccountService::class)->findOrCreate($driver, (string) $socialUser->getId(), [
                'name' => $socialUser->getName() ?: $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'phone' => $this->extractPhoneNumber($driver, $socialUser),
            ]);

            // Login the user
            Auth::login($user);

            // 🔴 **소셜 가입은 우리 회원가입 폼을 거치지 않는다.** 체크박스를 보여줄
            //    자리가 없으므로 로그인 직후 동의 화면으로 보낸다. 이걸 빼면
            //    소셜 가입자만 «동의 없이» 위치가 수집된다.
            if (app(ConsentService::class)->missingRequired($user)) {
                return redirect()->route('consent.show');
            }

            return redirect()->route('request.create');

        } catch (Exception $e) {
            Log::error('Social login error: ' . $e->getMessage());

            if($e->getCode() == 23000){
                return view('errors.duplicate-user', ['message' => '이미 가입된 사용자입니다.']);
            }

            return view('errors.500', ['message' => '소셜 로그인 중 오류가 발생했습니다.']);
        }
    }

    /**
     * Extract phone number from social provider response
     */
    private function extractPhoneNumber($driver, $socialUser)
    {
        try {
            if ($driver === 'naver' && isset($socialUser->user['response']['mobile'])) {
                return preg_replace('/[^0-9]/', '', $socialUser->user['response']['mobile']);
            } elseif ($driver === 'kakao' && isset($socialUser->user['phone_number'])) {
                return preg_replace('/[^0-9]/', '', $socialUser->user['phone_number']);
            }
        } catch (Exception $e) {
            Log::warning("Failed to extract phone number from {$driver}: " . $e->getMessage());
        }

        return null;
    }
}
