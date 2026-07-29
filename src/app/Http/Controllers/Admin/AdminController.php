<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Request as RescueRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_admins' => User::role('admin')->count(),
            'total_rescuers' => User::role('rescuer')->count(),
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

    public function members(Request $request)
    {
        $query = User::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
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
        $roles = ['admin', 'rescuer'];

        return view('admin.members.edit', compact('member', 'roles'));
    }

    public function memberUpdate(Request $request, $id)
    {
        $member = User::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,' . $id],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone,' . $id],
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

        $rescuers = User::role('rescuer')->get();

        return view('admin.requests.show', compact('rescueRequest', 'rescuers'));
    }

    public function requestUpdate(Request $request, $id)
    {
        $rescueRequest = RescueRequest::findOrFail($id);

        $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed,cancelled'],
            'assigned_rescuer_id' => ['nullable', 'exists:users,id'],
        ]);

        $rescueRequest->update([
            'status' => $request->status,
            'assigned_rescuer_id' => $request->assigned_rescuer_id,
        ]);

        return redirect()->back()
            ->with('success', '구조 요청이 성공적으로 업데이트되었습니다.');
    }

    public function memberCreate()
    {
        $roles = ['admin', 'rescuer'];
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
        if (!$request->email && !$request->phone) {
            return back()->withErrors(['email' => '이메일 또는 연락처 중 하나는 필수입니다.'])->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Assign roles
        if ($request->has('roles') && !empty($request->roles)) {
            $user->assignRole($request->roles);
        }

        return redirect()->route('admin.members.show', $user->id)
            ->with('success', '회원이 성공적으로 생성되었습니다.');
    }
}