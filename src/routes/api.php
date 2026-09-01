<?php

use App\Http\Controllers\Api\ClientErrorController;
use App\Http\Controllers\Api\ConsentApiController;
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

// 웹뷰 JS 에러 수집 (M-16).
//
// 🔑 **auth 밖에 둔다.** 로그인 «전» 화면에서 난 에러도 받아야 한다 — 거기서 깨지면
//    사용자는 들어오지도 못하는데, 정작 그 화면이 가장 안 보이는 곳이다.
// 🔴 throttle 은 «분당» 이다(아래 위치 API 주석 참조). 폭주의 1차 방어는 클라이언트
//    게이트(errorReport.js)이고, 여기는 마지막 방어선이다.
Route::post('/client-errors', [ClientErrorController::class, 'store'])
    ->middleware('throttle:20,1')->name('api.client-errors.store');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/requests', [RequestApiController::class, 'index'])->name('api.requests.index');
    Route::post('/requests', [RequestApiController::class, 'store'])->name('api.requests.store');
    Route::get('/requests/{id}', [RequestApiController::class, 'show'])->name('api.requests.show');
    Route::put('/requests/{id}', [RequestApiController::class, 'update'])->name('api.requests.update');
    Route::delete('/requests/{id}', [RequestApiController::class, 'destroy'])->name('api.requests.destroy');
    // 푸시 수신 통로 등록/해제 (mobile-app N1).
    // 🔴 토큰은 «본문»으로만 받는다 — path 에 넣으면 액세스·프록시·에러 로그에 자격증명이 남는다.
    // 약관 동의 — 위치 공유를 켜는 «그 자리»에서 받는다(화면 이동 없이).
    Route::get('/consents', [ConsentApiController::class, 'index'])->name('api.consents.index');
    Route::post('/consents', [ConsentApiController::class, 'store'])
        ->middleware('throttle:10,1')->name('api.consents.store');

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

    // OS 위치 권한 보고 (M-5, ADR-0008).
    // ⚠️ /location(ping)과 «합치면 안 된다» — 권한이 끊기면 ping 도 끊겨서, 정작
    //    알아야 할 순간에 아무것도 안 온다. 공유가 꺼져 있어도 받는다.
    //    호출 빈도는 낮다(포그라운드 복귀·토글·권한 변경 콜백)이므로 ping 만큼
    //    느슨한 스로틀이 필요 없다.
    Route::patch('/events/{id}/location-permission', [LocationApiController::class, 'locationPermission'])
        ->middleware(['event.member', 'throttle:20,1'])->name('api.events.location-permission');

    // 지령(출동) (실시간 관제 — BE-3.3 / SPEC-06b)
    // 배정: 해당 신고 행사의 controller (event.role 은 {requestId}→신고→행사 해석)
    Route::post('/requests/{requestId}/dispatch', [DispatchApiController::class, 'store'])
        ->middleware('event.role:controller')->name('api.requests.dispatch');
    // 보조 인원 «추가» 배정 (ADR-0007 D4 — 주담당 1명 + 보조 N명).
    // 🔑 위 라우트의 플래그가 아니라 별도 URL 이다. 한 신고에 두 명이 붙는 것은 관제사의
    //    명시적 결정이어야 하고, 같은 문에 옵션으로 달면 오탭·클라이언트 버그로 생긴다.
    Route::post('/requests/{requestId}/dispatch/support', [DispatchApiController::class, 'storeSupport'])
        ->middleware('event.role:controller')->name('api.requests.dispatch.support');
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
