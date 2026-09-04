<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 관리자가 발급한 계정(must_change_password)은 «첫 로그인에서» 비밀번호 변경 + 필수 동의를
 * 마칠 때까지 다른 어떤 화면에도 닿지 못한다 (ADR-0009 D3).
 *
 * 🔴 발급 계정은 «동의 없이» 만들어진다(관리자가 대리 발급이므로). 이 게이트를 빠져나가면
 *    동의 없는 계정이 신고·위치 화면에 들어간다 — 그래서 화면 자체를 못 열게 막는다.
 *
 * 허용 예외: 셋업 화면 자체, 로그아웃, 그리고 약관 열람(셋업 폼의 링크가 새 탭으로 여는 곳).
 */
class EnsurePasswordSetup
{
    /** 발급-대기 상태에서도 닿아야 하는 라우트 이름. */
    private const ALLOWED = [
        'account.setup.show',
        'account.setup.store',
        'logout',
        'legal.privacy',
        'legal.location-terms',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName !== null && in_array($routeName, self::ALLOWED, true)) {
            return $next($request);
        }

        // API(JSON) 요청은 리다이렉트 대신 명시적으로 막는다 — 발급-대기 계정이 세션으로
        // API 를 때릴 때 조용히 통과시키지 않는다.
        if ($request->expectsJson()) {
            abort(403, '비밀번호를 먼저 변경해야 합니다.');
        }

        return redirect()->route('account.setup.show');
    }
}
