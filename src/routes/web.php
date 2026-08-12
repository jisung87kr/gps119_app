<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// 루트는 «이 사람이 여기 온 이유»로 보낸다. 판정은 LandingResolver 한 곳에만 있고
// LoginResponse 도 같은 것을 부른다 — 예전에는 둘이 서로 다른 규칙이라 같은 사람이
// 도메인을 직접 치고 들어왔을 때와 로그인 폼을 거쳤을 때 다른 화면을 봤다.
//
// 비로그인은 로그인으로 «직접» 보낸다 — 예전처럼 신고 작성으로 한 번 튕기면
// auth 미들웨어가 intended=/requests/create 를 세션에 심고, 로그인 직후
// LoginResponse 의 역할별 착지가 그 intended 에 밀려 무력화된다.
// (도메인만 치고 들어오는 것이 가장 흔한 진입 경로라 실질적으로 늘 밀렸다.)
Route::get('/', function (\App\Services\LandingResolver $landing) {
    return redirect($landing->for(Auth::user()));
});

// 법적 고지 — «비로그인도» 볼 수 있어야 한다. 로그인 화면에서 링크되고,
// 스토어 심사(Play 데이터 안전)에서 개인정보처리방침 URL 을 «공개»로 요구한다.
Route::view('/privacy', 'legal.privacy')->name('legal.privacy');
Route::view('/location-terms', 'legal.location-terms')->name('legal.location-terms');

Route::get('/requests/create', function () {
    // 구조대(시스템 롤 rescuer) 계정은 신고를 «올리지» 않는다 — 지령만 받는다.
    // (2026-08-12 현장 결정. 차단 화면이 119·상황실 전화를 대신 제공한다.)
    if (Auth::user()->hasRole('rescuer')) {
        return response()->view('errors.rescuer-no-request', [
            'controlTel' => '010-4794-0119',
        ], 403);
    }

    if (! Auth::user()->phone) {
        // 회원정보 변경 페이지 리다이렉트
        return view('errors.require-phone');
    }

    return view('request.create');
})->middleware(['auth'])->name('request.create');

Route::get('/requests/create/{slug}', function ($slug) {
    $project = \App\Models\Project::where('slug', $slug)->firstOrFail();

    if (Auth::user()->hasRole('rescuer')) {
        return response()->view('errors.rescuer-no-request', [
            'project' => $project,
            'controlTel' => data_get($project->settings, 'emergency_tel', '010-4794-0119'),
        ], 403);
    }

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

// 구급대원 «홈» — 행사 횡단 출동 이력 (현장 피드백 #4·#6).
//
// 🔑 `/events/{id}/dispatch` 를 횡단으로 넓히지 않는다. 그 화면은 단일 행사 실시간
//    작업 화면이다(개인 채널 1개 구독 + 지도 bounds 하나). 횡단으로 만들면 채널을 N개
//    구독하게 되고 지도 전제가 무너진다. 여기는 실시간이 필요 없는 «목록»이라 분리한다.
Route::get('/dispatches', function () {
    $user = Auth::user();

    $dispatches = \App\Models\Dispatch::where('paramedic_id', $user->id)
        ->with(['request:id,type,priority,address,status', 'project:id,name'])
        ->orderByDesc('id')
        ->limit(50)
        ->get();

    $counts = \App\Models\Dispatch::where('paramedic_id', $user->id)
        ->selectRaw('status, count(*) as aggregate')
        ->groupBy('status')
        ->pluck('aggregate', 'status');

    $active = [\App\Enums\DispatchStatus::ACCEPTED, \App\Enums\DispatchStatus::EN_ROUTE, \App\Enums\DispatchStatus::ARRIVED];

    $stats = [
        // 「출동 요청 0건 완료 0건」이라는 지적이 여기다 — 신고 기준 숫자를 보여주고 있었다.
        'assigned' => (int) ($counts[\App\Enums\DispatchStatus::ASSIGNED->value] ?? 0),
        'in_progress' => (int) collect($active)->sum(fn ($s) => $counts[$s->value] ?? 0),
        'completed_today' => \App\Models\Dispatch::where('paramedic_id', $user->id)
            ->where('status', \App\Enums\DispatchStatus::COMPLETED)
            ->whereDate('completed_at', today())
            ->count(),
        'completed_total' => (int) ($counts[\App\Enums\DispatchStatus::COMPLETED->value] ?? 0),
    ];

    // 지령을 받을 수 있는 활성 행사 — 실시간 작업 화면 진입점
    $myEvents = \App\Models\EventParticipant::query()
        ->where('user_id', $user->id)
        ->where('status', \App\Enums\ParticipantStatus::ACTIVE->value)
        ->whereHas('project', fn ($q) => $q->active())
        ->with('project:id,name')
        ->get()
        ->filter(fn ($p) => $p->project !== null && $p->role->canReceiveDispatch());

    return view('dispatch.home', compact('dispatches', 'stats', 'myEvents'));
})->middleware(['auth'])->name('dispatches.index');

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

    // ?project={id} 로 특정 행사를 지정하면 그 행사로 진입한다(푸시 딥링크가 여기 걸린다).
    //
    // 🔑 예전에는 /admin/control 만 이 파라미터를 읽었다. 그런데 «행사 상황실»은
    //    시스템 롤이 그냥 user 인 경우가 흔해서 /control 로 온다 — 즉 딥링크가
    //    정작 상황실에게만 동작하지 않았다. 행사를 2개 이상 맡으면 «엉뚱한 행사»가
    //    열려서, 알림을 받고 들어왔는데 다른 현장을 보게 된다.
    //    관제 권한이 있는 행사 목록 안에서만 고른다(권한 밖 id 는 무시).
    $selectedId = (int) request('project') ?: null;
    if ($selectedId && ! $projects->contains('id', $selectedId)) {
        $selectedId = null;
    }

    return view('control.index', [
        'projects' => $projects,
        'selectedId' => $selectedId,
        // 관리자에게만 관리 셸로 돌아가는 백링크를 준다.
        //
        // 🔑 푸시 딥링크는 «한 URL 로 두 집단»을 태워야 해서 /control 을 쓴다 —
        //    신규 신고의 1순위 수신자인 행사 상황실은 시스템 롤이 보통 user 라
        //    /admin/control 로 보내면 403 을 받는다. 대신 관리자가 알림으로 들어오면
        //    사이드바 없는 화면에 갇히므로, 여기서 백링크만 채워준다.
        //    (행사 controller 에게는 주지 않는다 — 그 대시보드는 admin 미들웨어 뒤에 있다.)
        'backUrl' => $user->hasRole('admin') ? route('admin.dashboard') : null,
    ]);
})->middleware(['auth'])->name('control');

Route::get('/dashboard', function () {
    $user = Auth::user();

    // 구급 쪽 사람의 홈은 «내가 신고한 건수»가 아니라 출동 현황이다 (현장 피드백 #4).
    // 판정은 User::usesDispatchHome() 한 곳 — 하단 탭도 같은 것을 본다.
    if ($user->usesDispatchHome()) {
        return redirect()->route('dispatches.index');
    }

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
    // 명단 CSV 일괄 등록 — 참가자 100명을 한 명씩 넣을 수는 없다(현장 피드백).
    Route::post('/projects/{project}/participants/import', [\App\Http\Controllers\Admin\EventParticipantController::class, 'import'])->name('projects.participants.import');
    Route::get('/projects/{project}/participants/template', [\App\Http\Controllers\Admin\EventParticipantController::class, 'importTemplate'])->name('projects.participants.template');
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
