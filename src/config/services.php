<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'naver' => [
        'client_id' => env('NAVER_CLIENT_ID'),
        'client_secret' => env('NAVER_CLIENT_SECRET'),
        'redirect' => env('NAVER_REDIRECT_URI'),
    ],
    // 운영용 신규 신고 공지(선택). 미설정이면 AnnounceRequestToDiscord 가 조용히 건너뛴다.
    // env() 를 리스너에서 직접 읽으면 config:cache 시 null 이 되므로 반드시 여기를 경유한다.
    'discord' => [
        'webhook_url' => env('DISCORD_WEBHOOK_URL'),
    ],

    'kakao' => [
        'client_id' => env('KAKAO_CLIENT_ID'),
        'client_secret' => env('KAKAO_CLIENT_SECRET'),
        'redirect' => env('KAKAO_REDIRECT_URI')
    ],

    // Sign in with Apple — iOS 앱 셸의 «네이티브» 로그인만 받는다 (App Store 심사 4.8).
    // identityToken 의 aud 는 웹 Services ID 가 아니라 «앱 번들 ID» 다. 웹 리다이렉트
    // 방식은 쓰지 않으므로 client_secret·Services ID·키(.p8)가 필요 없다.
    'apple' => [
        'bundle_id' => env('APPLE_BUNDLE_ID', 'kr.co.gps119.app'),
    ],
];
