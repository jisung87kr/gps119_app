<x-layouts.admin title="GPS119 관리자 대시보드">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">시스템 현황</h1>
                <p class="text-sm text-slate-500 font-medium mt-1">GPS119의 전체 운영 현황을 실시간으로 확인합니다.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Live Monitoring</span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <!-- Total Users -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Users</p>
                <div class="flex items-baseline gap-1">
                    <span class="text-2xl font-black text-slate-900 tracking-tighter">{{ number_format($stats['total_users']) }}</span>
                    <span class="text-xs font-bold text-slate-400">명</span>
                </div>
            </div>

            <!-- Total Requests -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm border-l-4 border-l-red-500">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Requests</p>
                <div class="flex items-baseline gap-1">
                    <span class="text-2xl font-black text-slate-900 tracking-tighter">{{ number_format($stats['total_requests']) }}</span>
                    <span class="text-xs font-bold text-slate-400">건</span>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="bg-amber-50 p-5 rounded-2xl border border-amber-100 shadow-sm">
                <p class="text-[10px] font-black text-amber-600 uppercase tracking-widest mb-1">Pending</p>
                <div class="flex items-baseline gap-1">
                    <span class="text-2xl font-black text-amber-700 tracking-tighter">{{ number_format($stats['pending_requests']) }}</span>
                    <span class="text-xs font-bold text-amber-500">대기</span>
                </div>
            </div>

            <!-- Total Projects -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm border-l-4 border-l-purple-500">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Projects</p>
                <div class="flex items-baseline gap-1">
                    <span class="text-2xl font-black text-slate-900 tracking-tighter">{{ number_format($stats['total_projects']) }}</span>
                    <span class="text-xs font-bold text-slate-400">개</span>
                </div>
            </div>

            <!-- Active Projects -->
            <div class="bg-emerald-50 p-5 rounded-2xl border border-emerald-100 shadow-sm">
                <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mb-1">Active Projects</p>
                <div class="flex items-baseline gap-1">
                    <span class="text-2xl font-black text-emerald-700 tracking-tighter">{{ number_format($stats['active_projects']) }}</span>
                    <span class="text-xs font-bold text-emerald-500">활성</span>
                </div>
            </div>
        </div>

        <!-- Middle Section -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
            <!-- Distribution -->
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-4">Request Status</h3>
                    <div class="space-y-3">
                        @php
                            $total = max($stats['total_requests'], 1);
                            $pendingP = ($stats['pending_requests'] / $total) * 100;
                            $progressP = ($stats['in_progress_requests'] / $total) * 100;
                            $completedP = ($stats['completed_requests'] / $total) * 100;
                        @endphp
                        <div>
                            <div class="flex justify-between text-[10px] font-bold mb-1">
                                <span class="text-amber-600">Pending</span>
                                <span>{{ round($pendingP) }}%</span>
                            </div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-500" style="width: {{ $pendingP }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-[10px] font-bold mb-1">
                                <span class="text-blue-600">In Progress</span>
                                <span>{{ round($progressP) }}%</span>
                            </div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500" style="width: {{ $progressP }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-[10px] font-bold mb-1">
                                <span class="text-emerald-600">Completed</span>
                                <span>{{ round($completedP) }}%</span>
                            </div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500" style="width: {{ $completedP }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-900 p-6 rounded-2xl shadow-xl">
                    <h3 class="text-xs font-black text-white uppercase tracking-widest mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-1 gap-2">
                        <a href="{{ route('admin.projects.index') }}" class="flex items-center justify-between p-2 px-3 bg-white/10 hover:bg-white/20 text-white rounded-xl transition-all group">
                            <span class="text-xs font-bold">프로젝트 관리</span>
                            <svg class="w-4 h-4 opacity-50 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                        </a>
                        <a href="{{ route('admin.members') }}" class="flex items-center justify-between p-2 px-3 bg-white/10 hover:bg-white/20 text-white rounded-xl transition-all group">
                            <span class="text-xs font-bold">회원 관리</span>
                            <svg class="w-4 h-4 opacity-50 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                        </a>
                        <a href="{{ route('admin.requests') }}" class="flex items-center justify-between p-2 px-3 bg-blue-600 hover:bg-blue-500 text-white rounded-xl transition-all group">
                            <span class="text-xs font-bold">전체 요청 보기</span>
                            <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Top Projects -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden h-full flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest">Top Projects</h3>
                        <a href="{{ route('admin.projects.index') }}" class="text-[10px] font-black text-blue-600 uppercase tracking-widest hover:underline">All Projects →</a>
                    </div>
                    <div class="p-2 flex-1">
                        @if($project_stats->isEmpty())
                            <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                                <svg class="w-12 h-12 mb-2 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-width="1.5"/></svg>
                                <p class="text-xs font-bold">진행 중인 프로젝트가 없습니다.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($project_stats as $project)
                                    <div class="group flex items-center justify-between p-3.5 bg-white border border-slate-100 rounded-xl hover:border-blue-200 hover:bg-blue-50/30 transition-all duration-200">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <div class="w-1.5 h-1.5 rounded-full {{ $project->status === 'active' ? 'bg-emerald-500' : 'bg-slate-300' }}"></div>
                                                <h4 class="text-sm font-bold text-slate-900 truncate tracking-tight">{{ $project->name }}</h4>
                                            </div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">{{ $project->start_date->format('M d') }} - {{ $project->end_date->format('M d, Y') }}</p>
                                        </div>
                                        <div class="flex items-center gap-4 ml-4">
                                            <div class="text-right">
                                                <p class="text-lg font-black text-slate-900 leading-none tracking-tighter">{{ number_format($project->requests_count) }}</p>
                                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Requests</p>
                                            </div>
                                            <a href="{{ route('admin.projects.show', $project->id) }}" class="p-2 bg-slate-100 text-slate-400 rounded-lg group-hover:bg-blue-600 group-hover:text-white transition-all">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Requests -->
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest">Recent Requests</h3>
                    <a href="{{ route('admin.requests') }}" class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-900 transition-colors">View All</a>
                </div>
                <div class="divide-y divide-slate-50">
                    @forelse($recent_requests as $request)
                        <div class="flex items-center justify-between p-4 px-6 hover:bg-slate-50/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-500">
                                    {{ mb_substr($request->user->name ?? '?', 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-900 tracking-tight">{{ $request->user->name ?? '알 수 없음' }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">{{ $request->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest
                                @if($request->status === 'pending') bg-amber-100 text-amber-700
                                @elseif($request->status === 'in_progress') bg-blue-100 text-blue-700
                                @elseif($request->status === 'completed') bg-emerald-100 text-emerald-700
                                @else bg-slate-100 text-slate-600
                                @endif">
                                {{ $request->status }}
                            </span>
                        </div>
                    @empty
                        <div class="py-8 text-center text-slate-400 text-xs font-bold italic">No recent activity.</div>
                    @endforelse
                </div>
            </div>

            <!-- Recent Users -->
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest">Recent Members</h3>
                    <a href="{{ route('admin.members') }}" class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-900 transition-colors">Manage</a>
                </div>
                <div class="divide-y divide-slate-50">
                    @forelse($recent_users as $user)
                        <div class="flex items-center justify-between p-4 px-6 hover:bg-slate-50/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-900 flex items-center justify-center text-[10px] font-black text-white">
                                    {{ mb_substr($user->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-900 tracking-tight">{{ $user->name }}</p>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">{{ $user->created_at->format('M d, Y') }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest
                                @if($user->hasRole('admin')) bg-red-100 text-red-700
                                @elseif($user->hasRole('rescuer')) bg-emerald-100 text-emerald-700
                                @else bg-blue-100 text-blue-700
                                @endif">
                                {{ $user->roles->first()->name ?? 'User' }}
                            </span>
                        </div>
                    @empty
                        <div class="py-8 text-center text-slate-400 text-xs font-bold italic">No new members.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
