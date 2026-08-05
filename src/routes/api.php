<?php

use App\Http\Controllers\Api\DeviceTokenApiController;
use App\Http\Controllers\Api\DispatchApiController;
use App\Http\Controllers\Api\EventApiController;
use App\Http\Controllers\Api\EventReportController;
use App\Http\Controllers\Api\LocationApiController;
use App\Http\Controllers\Api\RequestApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/requests', [RequestApiController::class, 'index'])->name('api.requests.index');
    Route::post('/requests', [RequestApiController::class, 'store'])->name('api.requests.store');
    Route::get('/requests/{id}', [RequestApiController::class, 'show'])->name('api.requests.show');
    Route::put('/requests/{id}', [RequestApiController::class, 'update'])->name('api.requests.update');
    Route::delete('/requests/{id}', [RequestApiController::class, 'destroy'])->name('api.requests.destroy');
    // 구조대원 배정. 실시간 지령 에픽에서 POST /requests/{id}/dispatch 로 대체 예정(ADR-0003).
    Route::post('/requests/{id}/assign', [RequestApiController::class, 'assignRescuer'])->name('api.requests.assign');

    // 푸시 수신 통로 등록/해제 (mobile-app N1).
    // 🔴 토큰은 «본문»으로만 받는다 — path 에 넣으면 액세스·프록시·에러 로그에 자격증명이 남는다.
    Route::post('/devices', [DeviceTokenApiController::class, 'store'])->name('api.devices.store');
    Route::delete('/devices/current', [DeviceTokenApiController::class, 'destroyCurrent'])->name('api.devices.destroy');

    // 행사 입장·참가자 (실시간 관제 — BE-1.2 / SPEC-06b)
    Route::get('/events/{joinCode}', [EventApiController::class, 'show'])->name('api.events.show');
    Route::post('/events/{joinCode}/join', [EventApiController::class, 'join'])->name('api.events.join');
    Route::get('/events/{id}/me', [EventApiController::class, 'me'])
        ->middleware('event.member')->name('api.events.me');
    // 현장 수동 역할 배정 — controller/admin 만
    Route::patch('/events/{id}/participants/{userId}', [EventApiController::class, 'assignRole'])
        ->middleware('event.role:controller')->name('api.events.participants.assign');

    // 위치 (실시간 관제 — BE-2.1 / SPEC-06b)
    // ping 수신: active 참가자(역할무관) + rate-limit. 큐로 적재.
    //
    // 🔴 throttle 값 주의: Laravel 문법은 `throttle:최대횟수,분` 이다.
    //    예전 `throttle:2,1` 은 "초당 2회"가 아니라 **분당 2회**였고, 클라이언트는
    //    이동 중 5초 간격(분당 12회)으로 보내므로 12건 중 10건이 429 로 버려졌다.
    //    정지 상태(30초 하트비트 = 분당 2회)에서만 우연히 맞아, 정확도가 가장
    //    필요한 "이동 중"에 정확히 실패하고 있었다.
    //    30 = 이동 5초 간격(12) + 실패분 재전송·복수 탭 여유.
    //    변경 시 locationShare.js 의 MOVE_INTERVAL_MS 와 같이 볼 것.
    Route::post('/events/{id}/location', [LocationApiController::class, 'store'])
        ->middleware(['event.member', 'throttle:30,1'])->name('api.events.location.store');
    // 관제 roster: controller 만
    Route::get('/events/{id}/participants', [LocationApiController::class, 'participants'])
        ->middleware('event.role:controller')->name('api.events.participants.index');
    // 위치공유 토글: active 참가자
    Route::patch('/events/{id}/sharing', [LocationApiController::class, 'sharing'])
        ->middleware('event.member')->name('api.events.sharing');

    // 지령(출동) (실시간 관제 — BE-3.3 / SPEC-06b)
    // 배정: 해당 신고 행사의 controller (event.role 은 {requestId}→신고→행사 해석)
    Route::post('/requests/{requestId}/dispatch', [DispatchApiController::class, 'store'])
        ->middleware('event.role:controller')->name('api.requests.dispatch');
    // 가용 구급대원(거리순+보유지령수): controller
    Route::get('/requests/{requestId}/available-paramedics', [DispatchApiController::class, 'availableParamedics'])
        ->middleware('event.role:controller')->name('api.requests.available-paramedics');
    // 상태 전이: paramedic 본인 또는 그 행사 controller (서비스가 권한 검사)
    Route::patch('/dispatches/{id}/status', [DispatchApiController::class, 'updateStatus'])
        ->name('api.dispatches.status');
    // 본인 지령 목록
    Route::get('/dispatches/mine', [DispatchApiController::class, 'mine'])->name('api.dispatches.mine');
    // 출동 현황 보드: controller
    Route::get('/events/{id}/dispatches', [DispatchApiController::class, 'board'])
        ->middleware('event.role:controller')->name('api.events.dispatches');

    // 관제 초기 로드용 미완료 신고 목록(pending/in_progress)
    Route::get('/events/{id}/requests', [DispatchApiController::class, 'eventRequests'])
        ->middleware('event.role:controller')->name('api.events.requests');

    // 기록 다운로드 (BE-4.1) — controller/admin, 스트리밍 CSV
    Route::middleware('event.role:controller')->group(function () {
        Route::get('/events/{id}/report/requests.csv', [EventReportController::class, 'requests'])->name('api.events.report.requests');
        Route::get('/events/{id}/report/dispatches.csv', [EventReportController::class, 'dispatches'])->name('api.events.report.dispatches');
        Route::get('/events/{id}/report/tracks.csv', [EventReportController::class, 'tracks'])->name('api.events.report.tracks');
    });
});
