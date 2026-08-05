<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// 루트는 «이 사람이 여기 온 이유»로 보낸다.
//
// 관리자는 관리 셸로. 그 밖의 로그인 사용자는 신고 작성(이 앱의 주 용도)으로.
// 비로그인은 로그인으로 «직접» 보낸다 — 예전처럼 신고 작성으로 한 번 튕기면
// auth 미들웨어가 intended=/requests/create 를 세션에 심고, 로그인 직후
// LoginResponse 의 역할별 착지가 그 intended 에 밀려 무력화된다.
// (도메인만 치고 들어오는 것이 가장 흔한 진입 경로라 실질적으로 늘 밀렸다.)
Route::get('/', function () {
    $user = Auth::user();

    if (! $user) {
        return redirect()->route('login');
    }

    return $user->hasRole('admin')
        ? redirect()->route('admin.dashboard')
        : redirect()->route('request.create');
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
    // FE-3.4: 신고자 상태추적용 담당자/상황실 정보 동봉(웹 뷰 데이터 — 실시간 갱신은 채널)
    $request->load(['activeDispatch.paramedic', 'project']);

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

// 참가자 활동 화면 (FE-2.2): 위치 자동공유 시작/토글. 가드: 해당 행사 active 참가자.
Route::get('/events/{id}/active', function ($id) {
    $user = Auth::user();
    $project = \App\Models\Project::findOrFail($id);

    $role = $user->eventRoleIn($project); // active 참가만 역할 반환
    if ($role === null) {
        // 미참가/pending → 입장 화면으로 유도
        return redirect()->route('events.join');
    }

    return view('event.active', [
        'project' => $project,
        'role' => $role->value,
        'roleLabel' => $role->label(),
        // 구급대/자원봉사 구급이면 지령(출동) 화면 진입 링크 노출
        'canDispatch' => $role->canReceiveDispatch(),
    ]);
})->middleware(['auth'])->name('events.active');

// 구급대원 지령 앱 (FE-3.2). 가드: 해당 행사 active + 지령 수령 가능 역할(paramedic/volunteer_medic).
Route::get('/events/{id}/dispatch', function ($id) {
    $user = Auth::user();
    $project = \App\Models\Project::findOrFail($id);

    $role = $user->eventRoleIn($project);
    if ($role === null) {
        return redirect()->route('events.join'); // 미참가 → 입장
    }
    if (! $role->canReceiveDispatch()) {
        return redirect()->route('events.active', $project->id); // 수령 역할 아님 → 활동화면
    }

    return view('dispatch.index', [
        'project' => $project,
        'role' => $role->value,
        'roleLabel' => $role->label(),
    ]);
})->middleware(['auth'])->name('events.dispatch');

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

    // 참가 중인 행사(활동/지령 화면 진입점) — 운영 인력이 로그인 후 자기 행사로 갈 통로
    $myEvents = \App\Models\EventParticipant::query()
        ->where('user_id', $user->id)
        ->where('status', \App\Enums\ParticipantStatus::ACTIVE->value)
        ->with('project:id,name')
        ->orderByDesc('project_id')
        ->get()
        ->filter(fn ($p) => $p->project !== null);

    return view('dashboard', compact('stats', 'activeRequests', 'recentRequests', 'myEvents'));
})->middleware(['auth'])->name('dashboard');

// Admin routes
Route::get('/admin/register', [AuthController::class, 'showAdminRegister'])->name('admin.register');
Route::post('/admin/register', [AuthController::class, 'adminRegister']);

// Admin panel routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/statistics', [\App\Http\Controllers\Admin\AdminController::class, 'statistics'])->name('statistics');

    // Member management
    Route::get('/members', [\App\Http\Controllers\Admin\AdminController::class, 'members'])->name('members');
    Route::get('/members/create', [\App\Http\Controllers\Admin\AdminController::class, 'memberCreate'])->name('members.create');
    Route::post('/members', [\App\Http\Controllers\Admin\AdminController::class, 'memberStore'])->name('members.store');
    Route::get('/members/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'memberShow'])->name('members.show');
    Route::get('/members/{id}/edit', [\App\Http\Controllers\Admin\AdminController::class, 'memberEdit'])->name('members.edit');
    Route::patch('/members/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'memberUpdate'])->name('members.update');

    // 신고 상세·상태변경 (목록 SPA는 실시간 관제로 대체되어 제거 — ADR-0005 이후)
    Route::get('/requests/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'requestShow'])->name('requests.show');
    Route::patch('/requests/{id}', [\App\Http\Controllers\Admin\AdminController::class, 'requestUpdate'])->name('requests.update');

    // 행사 참가자·역할 관리 (EventRole) — resource projects 보다 먼저 등록해 /{project}/participants 우선 매칭
    Route::get('/projects/{project}/participants', [\App\Http\Controllers\Admin\EventParticipantController::class, 'index'])->name('projects.participants');
    Route::post('/projects/{project}/participants', [\App\Http\Controllers\Admin\EventParticipantController::class, 'store'])->name('projects.participants.store');
    Route::patch('/projects/{project}/participants/{user}', [\App\Http\Controllers\Admin\EventParticipantController::class, 'update'])->name('projects.participants.update');
    Route::delete('/projects/{project}/participants/{user}', [\App\Http\Controllers\Admin\EventParticipantController::class, 'destroy'])->name('projects.participants.destroy');

    // Project management
    Route::resource('projects', \App\Http\Controllers\Admin\ProjectController::class);
    Route::get('/projects/{id}/qrcode', [\App\Http\Controllers\Admin\ProjectController::class, 'qrcode'])->name('projects.qrcode');
    Route::post('/projects/{id}/clone', [\App\Http\Controllers\Admin\ProjectController::class, 'clone'])->name('projects.clone');
    Route::get('/projects/{id}/export-csv', [\App\Http\Controllers\Admin\ProjectController::class, 'exportCsv'])->name('projects.export-csv');

    // 실시간 관제 — 관리자 셸에 임베드된 웹 관제 SPA(FE-2.1). 활성 행사 전체를 관제.
    // ?project={id} 로 특정 행사를 지정하면 해당 행사로 진입(활성 행사가 아니면 무시).
    Route::get('/control', function () {
        $projects = \App\Models\Project::active()->orderByDesc('id')->get(['id', 'name']);

        $selectedId = (int) request('project') ?: null;
        if ($selectedId && ! $projects->contains('id', $selectedId)) {
            $selectedId = null;
        }

        // 관제는 전용 풀블리드 레이아웃(control.index) — 좌측 관리자 GNB 없음.
        // 헤더에 대시보드로 돌아가는 백링크만 노출한다.
        return view('control.index', [
            'projects' => $projects,
            'selectedId' => $selectedId,
            'backUrl' => route('admin.dashboard'),
        ]);
    })->name('control');
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
