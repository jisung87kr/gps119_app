<?php

/*
 * 테스트 부트스트랩.
 *
 * docker-compose 가 컨테이너에 주입하는 OS 환경변수(DB_DATABASE=laravel 등)는
 * Laravel 의 Dotenv 보다 우선하므로, 테스트에서 인메모리 sqlite 를 강제하려면
 * Laravel 부트 이전에 해당 변수를 테스트용으로 덮어써야 한다.
 * (DevOps 영역인 .env / docker-compose 는 건드리지 않고, 테스트 프로세스에 한해 적용)
 */

$overrides = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'BROADCAST_CONNECTION' => 'null',
    'QUEUE_CONNECTION' => 'sync',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
];

foreach ($overrides as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
