<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Services\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 아직 필수 동의를 못 받은 사용자에게 동의를 받는다.
 *
 * 🔴 **소셜 가입에는 폼이 없다.** 네이버로 시작하면 우리 회원가입 화면을 거치지
 *    않으므로 체크박스를 보여줄 자리가 없다 — 로그인 «직후» 이 화면으로 보낸다.
 *    이걸 빼면 소셜 가입자만 동의 없이 위치가 수집된다.
 */
class ConsentController extends Controller
{
    public function __construct(private ConsentService $consents) {}

    public function show(Request $request)
    {
        $missing = $this->consents->missingRequired($request->user());

        // 받을 게 없으면 이 화면에 머물 이유가 없다.
        if (! $missing) {
            return redirect()->intended(route('request.create'));
        }

        return view('auth.consent', ['missing' => $missing]);
    }

    public function store(Request $request)
    {
        $required = array_map(fn (ConsentType $t) => $t->value, ConsentType::required());

        $request->validate([
            'consents' => ['required', 'array'],
            'consents.*' => [Rule::in($required)],
        ]);

        $given = (array) $request->input('consents', []);

        if (array_diff($required, $given)) {
            return back()->withErrors([
                'consents' => '필수 약관에 모두 동의해야 서비스를 이용할 수 있습니다.',
            ]);
        }

        $this->consents->record($request->user(), ConsentType::required(), $request->ip());

        return redirect()->intended(route('request.create'));
    }

    /** 동의하지 않고 나가는 길. 계정은 남기고 로그아웃만 한다. */
    public function decline(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', '약관에 동의해야 서비스를 이용할 수 있습니다.');
    }
}
