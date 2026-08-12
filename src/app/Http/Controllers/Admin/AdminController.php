<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Models\Dispatch;
use App\Models\Project;
use App\Models\Request as RescueRequest;
use App\Models\User;
use App\Services\RequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_admins' => User::role('admin')->count(),
            'total_requests' => RescueRequest::count(),
            'pending_requests' => RescueRequest::where('status', 'pending')->count(),
            'in_progress_requests' => RescueRequest::where('status', 'in_progress')->count(),
            'completed_requests' => RescueRequest::where('status', 'completed')->count(),
            'total_projects' => \App\Models\Project::count(),
            // 활성 행사는 날짜 기반 스코프가 단일 출처 (stale status 컬럼 대신)
            'active_projects' => \App\Models\Project::active()->count(),
            'today_requests' => RescueRequest::whereDate('created_at', now())->count(),
        ];

        $recent_requests = RescueRequest::with(['user', 'project'])
            ->latest()
            ->limit(8)
            ->get();

        $recent_users = User::latest()
            ->limit(6)
            ->get();

        // 프로젝트별 요청 통계 (상위 5개 프로젝트)
        $project_stats = \App\Models\Project::withCount('requests')
            ->orderByDesc('requests_count')
            ->limit(5)
            ->get();

        // 최근 14일 일별 신고 수 (차트용, 실데이터)
        $daily = collect(range(13, 0))->map(fn ($d) => [
            'label' => now()->subDays($d)->format('n/j'),
            'count' => RescueRequest::whereDate('created_at', now()->subDays($d))->count(),
        ]);

        // 오늘 vs 어제 증감 (KPI 추세 뱃지, 실데이터)
        $yesterday = RescueRequest::whereDate('created_at', now()->subDay())->count();
        $stats['today_delta'] = $stats['today_requests'] - $yesterday;

        // 최근 7일 vs 이전 7일 신고량 (추세)
        $week = RescueRequest::where('created_at', '>=', now()->subDays(7))->count();
        $prevWeek = RescueRequest::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();
        $stats['week_requests'] = $week;
        $stats['week_delta_pct'] = $prevWeek > 0 ? round(($week - $prevWeek) / $prevWeek * 100) : null;

        return view('admin.dashboard', compact('stats', 'recent_requests', 'recent_users', 'project_stats', 'daily'));
    }

    /**
     * 통계 페이지 — 프로젝트(행사)별 신고 현황 + 유형/우선순위/시간대/지령 집계.
     * ?period=7|30|90|all (기본 30일) 로 기간을 좁힌다.
     */
    public function statistics(Request $request)
    {
        // 기간 필터: created_at >= $since. 'all' 이면 $since = null(전체).
        $periods = ['7' => '최근 7일', '30' => '최근 30일', '90' => '최근 90일', 'all' => '전체'];
        $period = array_key_exists((string) $request->query('period'), $periods)
            ? (string) $request->query('period')
            : '30';
        $since = $period === 'all' ? null : now()->subDays((int) $period)->startOfDay();

        // 행사(프로젝트) 필터: ?project={id}. 존재하는 행사만 허용, 아니면 null(전체).
        $projectList = Project::orderByDesc('id')->get(['id', 'name']);
        $projectId = (int) $request->query('project') ?: null;
        if ($projectId && ! $projectList->contains('id', $projectId)) {
            $projectId = null;
        }

        // 기간·행사 스코프 헬퍼(요청/지령 공통) — 두 테이블 모두 project_id 보유.
        $inPeriod = function ($q, $col = 'created_at') use ($since, $projectId) {
            if ($since) {
                $q->where($col, '>=', $since);
            }
            if ($projectId) {
                $q->where('project_id', $projectId);
            }

            return $q;
        };

        // ── KPI ──
        $reqBase = fn () => $inPeriod(RescueRequest::query());

        $total = (clone $reqBase())->count();
        $completed = (clone $reqBase())->where('status', 'completed')->count();
        $pending = (clone $reqBase())->where('status', 'pending')->count();
        $inProgress = (clone $reqBase())->where('status', 'in_progress')->count();
        $cancelled = (clone $reqBase())->where('status', 'cancelled')->count();

        // 평균 대응 시간(신고→완료, 분) · 평균 수락 시간(지령 배정→수락, 분)
        $avgCompleteMin = $inPeriod(RescueRequest::whereNotNull('completed_at'))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) as m')
            ->value('m');
        $avgAcceptMin = $inPeriod(Dispatch::whereNotNull('accepted_at'), 'assigned_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, assigned_at, accepted_at)) as m')
            ->value('m');

        $kpi = [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'cancelled' => $cancelled,
            'completion_rate' => $total > 0 ? round($completed / $total * 100) : 0,
            'avg_complete_min' => $avgCompleteMin !== null ? (int) round($avgCompleteMin) : null,
            'avg_accept_min' => $avgAcceptMin !== null ? (int) round($avgAcceptMin) : null,
        ];

        // ── 프로젝트(행사)별 신고 현황 (핵심) ──
        $scoped = fn ($q) => $since ? $q->where('requests.created_at', '>=', $since) : $q;
        $project_rows = Project::query()
            ->withCount([
                'requests as req_total' => $scoped,
                'requests as req_pending' => fn ($q) => $scoped($q)->where('status', 'pending'),
                'requests as req_in_progress' => fn ($q) => $scoped($q)->where('status', 'in_progress'),
                'requests as req_completed' => fn ($q) => $scoped($q)->where('status', 'completed'),
                'requests as req_cancelled' => fn ($q) => $scoped($q)->where('status', 'cancelled'),
                'participants as active_participants' => fn ($q) => $q->where('status', 'active'),
            ])
            ->when($projectId, fn ($q) => $q->where('id', $projectId))
            ->orderByDesc('req_total')
            ->orderByDesc('id')
            ->get();

        // ── 신고 유형 분포 ──
        $typeCounts = $inPeriod(RescueRequest::query())
            ->selectRaw('type, COUNT(*) as c')->groupBy('type')->pluck('c', 'type');
        $type_dist = collect(RequestType::cases())->map(fn ($t) => [
            'label' => $t->label(),
            'count' => (int) ($typeCounts[$t->value] ?? 0),
        ]);

        // ── 우선순위 분포 ──
        $prioCounts = $inPeriod(RescueRequest::query())
            ->selectRaw('priority, COUNT(*) as c')->groupBy('priority')->pluck('c', 'priority');
        $priority_dist = collect(RequestPriority::cases())->map(fn ($p) => [
            'label' => $p->label(),
            'badge' => $p->badgeClasses(),
            'count' => (int) ($prioCounts[$p->value] ?? 0),
        ]);

        // ── 시간대별(0~23시) 신고 분포 ──
        $hourCounts = $inPeriod(RescueRequest::query())
            ->selectRaw('HOUR(created_at) as h, COUNT(*) as c')->groupBy('h')->pluck('c', 'h');
        $hourly = collect(range(0, 23))->map(fn ($h) => [
            'hour' => $h,
            'count' => (int) ($hourCounts[$h] ?? 0),
        ]);

        // ── 지령(출동) 상태 분포 ──
        $dispatch_dist = $inPeriod(Dispatch::query())
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $dispatch_total = (int) $dispatch_dist->sum();

        // ── 응급대원 처리 순위 (완료 지령 기준 상위 8) ──
        $top_responders = $inPeriod(Dispatch::query())
            ->whereNotNull('paramedic_id')
            ->select('paramedic_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(status = 'completed') as completed"))
            ->groupBy('paramedic_id')
            ->orderByDesc('total')
            ->limit(8)
            ->with('paramedic:id,name')
            ->get();

        return view('admin.statistics', compact(
            'periods', 'period', 'projectList', 'projectId', 'kpi', 'project_rows',
            'type_dist', 'priority_dist', 'hourly',
            'dispatch_dist', 'dispatch_total', 'top_responders'
        ));
    }

    public function members(Request $request)
    {
        $query = User::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->has('role') && $request->role) {
            $query->role($request->role);
        }

        $members = $query->withCount('requests')
            ->latest()
            ->paginate(20);

        return view('admin.members.index', compact('members'));
    }

    public function memberShow($id)
    {
        $member = User::with(['requests', 'assignedRequests'])
            ->withCount(['requests', 'assignedRequests'])
            ->findOrFail($id);

        return view('admin.members.show', compact('member'));
    }

    public function memberEdit($id)
    {
        $member = User::findOrFail($id);
        // 시스템 롤은 «일반회원 / 관리자회원» 둘뿐이다. 체크 해제 = 일반회원.
        $roles = ['admin'];

        return view('admin.members.edit', compact('member', 'roles'));
    }

    public function memberUpdate(Request $request, $id)
    {
        $member = User::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$id],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone,'.$id],
            'roles' => ['array'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ];

        // 비밀번호가 입력된 경우에만 업데이트
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $member->update($updateData);

        // Update roles
        if ($request->has('roles')) {
            $member->syncRoles($request->roles);
        } else {
            $member->syncRoles([]);
        }

        return redirect()->route('admin.members.show', $member->id)
            ->with('success', '회원 정보가 성공적으로 업데이트되었습니다.');
    }

    public function requestShow(Request $request, $id)
    {
        $rescueRequest = RescueRequest::with(['user', 'assignedRescuer'])
            ->findOrFail($id);

        // AJAX 또는 JSON 요청인 경우 JSON 반환
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($rescueRequest);
        }

        return view('admin.requests.show', compact('rescueRequest'));
    }

    /**
     * 관리자 화면의 신고 상태 변경.
     *
     * 🔑 예전에는 여기서 `$rescueRequest->update([...])` 를 직접 했다. 그래서 관리자
     *    취소만 canBeCancelled() 검사를 건너뛰고, 활성 지령을 고아로 남기고, 아무에게도
     *    알리지 않았다 — API 취소와 «같은 버튼처럼 보이는 다른 동작»이었다.
     *    상태 변경은 서비스를 거친다.
     */
    public function requestUpdate(Request $request, $id, RequestService $requestService)
    {
        $rescueRequest = RescueRequest::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed,cancelled'],
            'assigned_rescuer_id' => ['nullable', 'exists:users,id'],
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            if ($validated['status'] === RequestStatus::CANCELLED->value) {
                $requestService->cancelRequest(
                    $rescueRequest,
                    $request->user(),
                    $validated['cancel_reason'] ?? '관리자 취소'
                );
            } else {
                $requestService->updateRequest($rescueRequest, [
                    'status' => RequestStatus::from($validated['status']),
                    'assigned_rescuer_id' => $validated['assigned_rescuer_id'] ?? null,
                ], $request->user());
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()
            ->with('success', '구조 요청이 성공적으로 업데이트되었습니다.');
    }

    public function memberCreate()
    {
        // 시스템 롤은 «일반회원 / 관리자회원» 둘뿐이다. 체크 해제 = 일반회원.
        $roles = ['admin'];

        return view('admin.members.create', compact('roles'));
    }

    public function memberStore(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'roles' => ['array'],
        ]);

        // Ensure at least one login method is provided
        if (! $request->email && ! $request->phone) {
            return back()->withErrors(['email' => '이메일 또는 연락처 중 하나는 필수입니다.'])->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Assign roles
        if ($request->has('roles') && ! empty($request->roles)) {
            $user->assignRole($request->roles);
        }

        return redirect()->route('admin.members.show', $user->id)
            ->with('success', '회원이 성공적으로 생성되었습니다.');
    }
}
