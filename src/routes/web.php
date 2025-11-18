<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialController;

Route::get('/', function () {
    return redirect()->route('request.create');
});

Route::get('/requests/create', function () {
    if(!Auth::user()->phone){
        // 회원정보 변경 페이지 리다이렉트
        return view('errors.require-phone');
    }

    return view('request.create');
})->middleware(['auth'])->name('request.create');

Route::get('/requests/create/{slug}', function ($slug) {
    $project = \App\Models\Project::where('slug', $slug)->firstOrFail();

    // 프로젝트가 활성화되어 있는지 확인
    if (!$project->isActive()) {
        return view('errors.project-inactive', compact('project'));
    }

    if(!Auth::user()->phone){
        return view('errors.require-phone', compact('project'));
    }

    return view('request.create-project', compact('project'));
})->middleware(['auth'])->name('request.create.project');

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
    Route::patch('/requests/{id}/quick-update', [\App\Http\Controllers\Admin\AdminController::class, 'requestQuickUpdate'])->name('requests.quick-update');

    // Project management
    Route::resource('projects', \App\Http\Controllers\Admin\ProjectController::class);
    Route::get('/projects/{id}/qrcode', [\App\Http\Controllers\Admin\ProjectController::class, 'qrcode'])->name('projects.qrcode');
    Route::post('/projects/{id}/clone', [\App\Http\Controllers\Admin\ProjectController::class, 'clone'])->name('projects.clone');
    Route::get('/projects/{id}/export-csv', [\App\Http\Controllers\Admin\ProjectController::class, 'exportCsv'])->name('projects.export-csv');
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

// Social login routes
Route::get('/login/{driver}', [SocialController::class, 'redirect'])->name('login.social');
Route::get('/auth/{driver}/callback', [SocialController::class, 'callback']);
