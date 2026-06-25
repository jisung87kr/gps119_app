<?php

use App\Http\Controllers\Api\EventApiController;
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

    // 행사 입장·참가자 (실시간 관제 — BE-1.2 / SPEC-06b)
    Route::get('/events/{joinCode}', [EventApiController::class, 'show'])->name('api.events.show');
    Route::post('/events/{joinCode}/join', [EventApiController::class, 'join'])->name('api.events.join');
    Route::get('/events/{id}/me', [EventApiController::class, 'me'])
        ->middleware('event.member')->name('api.events.me');
    // 현장 수동 역할 배정 — controller/admin 만
    Route::patch('/events/{id}/participants/{userId}', [EventApiController::class, 'assignRole'])
        ->middleware('event.role:controller')->name('api.events.participants.assign');

    // 위치 (실시간 관제 — BE-2.1 / SPEC-06b)
    // ping 수신: active 참가자(역할무관) + rate-limit(초당 1~2). 큐로 적재.
    Route::post('/events/{id}/location', [LocationApiController::class, 'store'])
        ->middleware(['event.member', 'throttle:2,1'])->name('api.events.location.store');
    // 관제 roster: controller 만
    Route::get('/events/{id}/participants', [LocationApiController::class, 'participants'])
        ->middleware('event.role:controller')->name('api.events.participants.index');
    // 위치공유 토글: active 참가자
    Route::patch('/events/{id}/sharing', [LocationApiController::class, 'sharing'])
        ->middleware('event.member')->name('api.events.sharing');
});
