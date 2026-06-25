<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('request.create');
});

Route::get('/requests/create', function () {
    if (! Auth::user()->phone) {
        // 회원정보 변경 페이지 리다이렉트
        return view('errors.require-phone');
    }

    return view('request.create');
})->middleware(['auth'])->name('request.create');

Route::get('/requests/create/{slug}', function ($slug) {
    $project = \App\Models\Project::where('slug', $slug)->firstOrFail();

    // 프로젝트가 활성화되어 있는지 확인
    if (! $project->isActive()) {
        return view('errors.project-inactive', compact('project'));
    }

    if (! Auth::user()->phone) {
        return view('errors.require-phone', compact('project'));
    }

    return view('request.create-project', compact('project'));
})->middleware(['auth'])->name('request.create.project');

Route::get('/requests/{request}', function (\App\Models\Request $request) {
    return view('request.show', [
        'request' => $request,
    ]);
})->middleware(['auth'])->name('request.show');

// 행사 입장 (FE-1.1). auth 미들웨어가 미로그인 시 로그인으로 보내고 intended URL 보존 →
// 폼 로그인(redirect()->intended)으로 같은 입장 지점 복귀. (소셜 로그인 복귀는 후속 TODO)
Route::get('/events/join', function () {
    return view('event.join');
})->middleware(['auth'])->name('events.join');

// QR 딥링크: join_code 를 URL 로 전달 → 코드 프리필 후 자동 미리보기.
// (카메라 스캐너는 v1 범위 밖 — QR 는 이 URL 로 진입시키는 방식. 카메라 라이브러리는 후속 TODO)
Route::get('/events/join/{joinCode}', function (string $joinCode) {
    return view('event.join', ['prefillCode' => strtoupper($joinCode)]);
})->middleware(['auth'])->name('events.join.code');

// 웹 관제 SPA (FE-2.1). 가드: 시스템 admin 또는 행사 controller(active).
// admin → 활성 행사 전체, controller → 본인이 active CONTROLLER 인 활성 행사만.
Route::get('/control', function () {
    $user = Auth::user();

    if ($user->hasRole('admin')) {
        $projects = \App\Models\Project::active()->orderByDesc('id')->get(['id', 'name']);
    } else {
        $projectIds = \App\Models\EventParticipant::query()
            ->where('user_id', $user->id)
            ->where('role', \App\Enums\EventRole::CONTROLLER->value)
            ->where('status', \App\Enums\ParticipantStatus::ACTIVE->value)
            ->pluck('project_id');

        $projects = \App\Models\Project::active()
            ->whereIn('id', $projectIds)
            ->orderByDesc('id')
            ->get(['id', 'name']);

        // controller 권한이 없거나 관제할 활성 행사가 없으면 차단
        if ($projects->isEmpty()) {
            abort(403, '관제 권한이 없습니다.');
        }
    }

    return view('control.index', ['projects' => $projects]);
})->middleware(['auth'])->name('control');

Route::get('/dashboard', function () {
    $user = Auth::user();

    $counts = $user->requests()
        ->selectRaw('status, count(*) as aggregate')
        ->groupBy('status')
        ->pluck('aggregate', 'status');

    $stats = [
        'total' => (int) $counts->sum(),
        'pending' => (int) ($counts[\App\Enums\RequestStatus::PENDING->value] ?? 0),
        'in_progress' => (int) ($counts[\App\Enums\RequestStatus::IN_PROGRESS->value] ?? 0),
        'completed' => (int) ($counts[\App\Enums\RequestStatus::COMPLETED->value] ?? 0),
    ];

    // 처리 중인(대기/진행중) 요청은 상단에 강조 노출
    $activeRequests = $user->requests()
        ->with(['project', 'assignedRescuer'])
        ->whereIn('status', [
            \App\Enums\RequestStatus::PENDING->value,
            \App\Enums\RequestStatus::IN_PROGRESS->value,
        ])
        ->latest('requested_at')
        ->get();

    // 전체 최근 요청 내역
    $recentRequests = $user->requests()
        ->with('project')
        ->latest('requested_at')
        ->limit(8)
        ->get();

    return view('dashboard', compact('stats', 'activeRequests', 'recentRequests'));
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
