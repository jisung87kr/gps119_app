<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\AuthController;

Route::get('/', function () {
    return redirect()->route('request.create');
});

Route::get('/requests/create', function () {
    return view('request.create');
})->middleware(['auth'])->name('request.create');

Route::get('/requests/{request}', function (\App\Models\Request $request) {
    return view('request.show', [
        'request' => $request,
    ]);
})->middleware(['auth'])->name('request.show');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// Admin routes
Route::get('/admin/register', [AuthController::class, 'showAdminRegister'])->name('admin.register');
Route::post('/admin/register', [AuthController::class, 'adminRegister']);

// Admin panel routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('dashboard');

    // Member management
    Route::get('/members', [\App\Http\Controllers\Admin\AdminController::class, 'members'])->name('members');
    Route::get('/members/create', [\App\Http\Controllers\Admin\AdminController::class, 'memberCreate'])->name('members.create');
    Route::post('/members', [\App\Http\Controllers\Admin\AdminController::class, 'memberStore'])->name('members.store');
    Route::get('/members/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'memberShow'])->name('members.show');
    Route::get('/members/{id}/edit', [\App\Http\Controllers\Admin\AdminController::class, 'memberEdit'])->name('members.edit');
    Route::patch('/members/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'memberUpdate'])->name('members.update');

    // Request management
    Route::get('/requests', [\App\Http\Controllers\Admin\AdminController::class, 'requests'])->name('requests');
    Route::get('/requests/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'requestShow'])->name('requests.show');
    Route::patch('/requests/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'requestUpdate'])->name('requests.update');
});

// Profile routes
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/password', [ProfileController::class, 'editPassword'])->name('profile.password.edit');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('/profile/delete', [ProfileController::class, 'deleteAccount'])->name('profile.delete');
    Route::delete('/profile', [ProfileController::class, 'destroyAccount'])->name('profile.destroy');
});
