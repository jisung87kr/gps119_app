<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    /*
     * 🔴 웹뷰 앱(mobile-app 에픽)의 인증이 통째로 여기 걸린다.
     *
     * 원격 URL 방식(01 A안)이라 웹뷰의 오리진 = 웹 오리진이므로 세션 쿠키가 «그대로»
     * 산다. 단 그건 **이 목록에 그 호스트가 있을 때만** 성립한다. 빠지면 /api/* 전체와
     * /broadcasting/auth 가 401 이 되고 — 즉 실시간이 통째로 죽는다.
     *
     * ⚠️ 포트까지 «정확히» 일치해야 한다. 개발은 :9050, 운영이 443 이면 포트 없는 형태다.
     *    운영 도메인은 M-1 로 미정이므로, 정해지면 .env 의 SANCTUM_STATEFUL_DOMAINS 에
     *    넣는다(이 파일의 기본값을 고치지 않는다 — 환경마다 달라야 하는 값이다).
     *
     * 📌 예전에는 sprintf 인자가 3개인데 자리표시자가 2개뿐이라
     *    Sanctum::currentRequestHost() 가 «조용히 버려지고» 있었다(업스트림은 그 줄을
     *    주석 처리해 둔다). 실제 요청 호스트가 자동으로 잡힐 거라 믿으면 안 된다.
     *
     * ⚠️ `app.gps119.co.kr:9050` 은 «개발 포트가 섞인» 값이라 운영에서 맞을 가능성이
     *    낮지만, 운영 .env 를 확인하기 전까지 지우지 않는다 — 만약 운영이 이 기본값에
     *    의존하고 있다면 지우는 순간 /api/* 와 /broadcasting/auth 가 전부 401 이 된다.
     *    M-1(운영 도메인 확정) 때 .env 로 명시하고 이 줄을 정리한다.
     */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1,localhost:9050,app.gps119.co.kr:9050',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
