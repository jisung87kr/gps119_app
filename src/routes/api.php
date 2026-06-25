<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RequestApiController;

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
});
