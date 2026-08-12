<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\LandingResolver;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

/**
 * 로그인 직후 착지 지점 — 역할에 따라 갈린다.
 *
 * 관리자는 `/admin/dashboard`(운영 셸), 그 밖은 `config('fortify.home')`(사용자 대시보드).
 * 관리자가 사용자 대시보드로 떨어지면 «내 구조요청 0건» 화면을 보게 되는데,
 * 그건 관리자가 로그인해서 하려던 일이 아니다.
 *
 * 2단계 인증 응답도 같은 규칙을 써야 한다 — 계정에 2FA 를 켜는 순간 착지가 갈리면
 * 그건 버그다. 그래서 두 계약을 한 클래스가 구현하고, `boot()` 에서 둘 다 바인딩한다.
 *
 * `intended()` 를 유지하는 이유: 로그아웃 상태로 특정 페이지에 들어왔다가 로그인한
 * 경우에는 그 페이지로 가야 한다. 역할 기반 경로는 «갈 곳이 따로 없을 때»의 기본값이다.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
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

    /**
     * 이 사용자의 기본 착지 경로.
     *
     * 판정은 LandingResolver 한 곳에만 있다 — `/` 도 같은 것을 부른다. 예전에는 둘이
     * 서로 다른 규칙을 갖고 있어서, 같은 사람이 도메인을 직접 치고 들어왔을 때와
     * 로그인 폼을 거쳤을 때 다른 화면을 봤다.
     */
    private function homeFor(?object $user): string
    {
        return app(LandingResolver::class)->for($user instanceof User ? $user : null);
    }
}
