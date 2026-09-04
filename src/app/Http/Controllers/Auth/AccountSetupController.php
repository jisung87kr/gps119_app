<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Services\ConsentService;
use App\Services\LandingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 관리자가 발급한 계정의 «첫 로그인 셋업» (ADR-0009 D3).
 *
 * 비밀번호 변경과 위치정보법 필수 동의를 «한 화면»에서 받는다 — 발급 계정은 동의 없이
 * 만들어졌으므로, 여기를 통과하지 않으면 동의 없는 계정이 서비스에 들어간다.
 *
 * EnsurePasswordSetup 미들웨어가 must_change_password 인 사용자를 여기로 몰아넣는다.
 */
class AccountSetupController extends Controller
{
    public function __construct(private ConsentService $consents) {}

    public function show(Request $request)
    {
        // 이미 마친 사람이 URL 로 직접 오면 갈 곳으로 보낸다.
        if (! $request->user()->must_change_password) {
            return redirect()->intended(app(LandingResolver::class)->for($request->user()));
        }

        return view('auth.account-setup', [
            'required' => ConsentType::required(),
        ]);
    }

    public function store(Request $request)
    {
        $requiredValues = array_map(fn (ConsentType $t) => $t->value, ConsentType::required());

        $request->validate([
            'password' => ['required', 'confirmed', Password::default()],
            'consents' => ['required', 'array'],
            'consents.*' => [Rule::in($requiredValues)],
        ], [
            'password.required' => '새 비밀번호를 입력해 주세요.',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다.',
        ]);

        if (array_diff($requiredValues, (array) $request->input('consents', []))) {
            return back()->withErrors([
                'consents' => '필수 약관에 모두 동의해야 서비스를 이용할 수 있습니다.',
            ])->withInput();
        }

        $user = $request->user();

        // 🔑 비밀번호 변경과 동의 기록은 «같이» 일어난다 — 중간에 실패해 동의 없는
        //    활성 계정이 남지 않도록.
        DB::transaction(function () use ($user, $request) {
            $user->forceFill([
                'password' => Hash::make($request->input('password')),
                'must_change_password' => false,
            ])->save();

            $this->consents->record($user, ConsentType::required(), $request->ip());
        });

        return redirect()->intended(app(LandingResolver::class)->for($user))
            ->with('status', '비밀번호를 설정하고 약관에 동의했습니다. 이제 서비스를 이용할 수 있습니다.');
    }
}
