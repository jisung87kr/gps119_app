<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Database\QueryException;
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

            // Check if user exists by provider and provider_id first
            $user = User::where('provider', $driver)
                       ->where('provider_id', $socialUser->getId())
                       ->first();

            // If not found, check by email
            if (!$user && $socialUser->getEmail()) {
                $user = User::where('email', $socialUser->getEmail())->first();

                // Update existing user with social provider info
                if ($user) {
                    $user->update([
                        'provider' => $driver,
                        'provider_id' => $socialUser->getId(),
                        'avatar' => $socialUser->getAvatar(),
                    ]);
                }
            }

            if (!$user) {
                // Create new user if doesn't exist
                $userData = [
                    'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: '사용자',
                    'email' => $socialUser->getEmail(),
                    'provider' => $driver,
                    'provider_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                ];

                // Try to get phone from social provider response
                $userData['phone'] = $this->extractPhoneNumber($driver, $socialUser);

                $user = $this->createUser($userData);
            }

            // Login the user
            Auth::login($user);

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

    /**
     * Create new user with error handling
     */
    private function createUser($userData)
    {
        try {
            return User::create($userData);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                // Duplicate entry error
                throw new Exception('duplicate_user', 23000);
            } else {
                // Other database error, rethrow
                throw $e;
            }
        }
    }
}
