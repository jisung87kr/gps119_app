<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\LandingResolver;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * 인증 직후 착지 지점 — 역할에 따라 갈린다. (로그인 · 2단계 인증 · 회원가입 공통)
 *
 * 판정은 LandingResolver 한 곳에만 있다. 「어디로 보내는가」의 문이 여럿이면 반드시
 * 한쪽이 뒤처진다 — 실제로 그렇게 됐다:
 *  - 예전에는 `/` 와 로그인이 서로 다른 규칙이라, 같은 사람이 도메인을 직접 치고
 *    들어왔을 때와 로그인 폼을 거쳤을 때 다른 화면을 봤다.
 *  - 그 둘을 합친 뒤에도 «회원가입»만 fortify.home 을 그대로 써서 혼자 /dashboard 로
 *    떨어졌다(브라우저 QA 에서 발견). 이제 세 계약이 같은 클래스를 쓴다.
 *
 * 2단계 인증도 마찬가지다 — 계정에 2FA 를 켜는 순간 착지가 갈리면 그건 버그다.
 *
 * `intended()` 를 유지하는 이유: 로그아웃 상태로 특정 페이지에 들어왔다가 인증한
 * 경우에는 그 페이지로 가야 한다. 역할 기반 경로는 «갈 곳이 따로 없을 때»의 기본값이다.
 */
class LoginResponse implements LoginResponseContract, RegisterResponseContract, TwoFactorLoginResponseContract
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended($this->homeFor($request->user()));
    }

    /** 이 사용자의 기본 착지 경로. */
    private function homeFor(?object $user): string
    {
        return app(LandingResolver::class)->for($user instanceof User ? $user : null);
    }
}
