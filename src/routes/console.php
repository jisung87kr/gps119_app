<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 위치 이력 자동 파기 — 개인정보처리방침의 보존기간과 «같은 값»을 실제로 집행한다.
//
// 🔑 이 앱에서 가장 민감한 데이터인데 지금까지 자동 파기가 없었다(OI-7 / 에픽 Q2).
//    보존기간은 config/location.php 한 곳이고, 방침 문서의 숫자도 거기서 나온다.
//
// ⚠️ 서버에 `schedule:run` 크론이 걸려 있어야 실제로 돈다. 없으면 이 코드는
//    「등록은 됐는데 아무 일도 안 하는」 상태가 된다 — DEPLOY.md §4 참조.
Schedule::command('location:purge')
    ->dailyAt('04:40')
    ->withoutOverlapping();
