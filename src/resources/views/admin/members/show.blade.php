<x-layouts.admin title="회원 상세정보 - GPS119 관리자">
    <div class="p-5 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">회원 상세정보</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $member->name }}님의 정보를 확인하세요.</p>
            </div>
            <div class="sm:ml-auto flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.members.edit', $member->id) }}"
                   class="inline-flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    정보 수정
                </a>
                <a href="{{ route('admin.members') }}"
                   class="inline-flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    목록으로
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <!-- Member Information -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h2 class="text-base font-semibold text-slate-900">기본 정보</h2>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                        <div>
                            <p class="text-sm text-slate-500 mb-1.5">이름</p>
                            <p class="text-sm font-medium text-slate-800">{{ $member->name }}</p>
                        </div>

                        <div>
                            <p class="text-sm text-slate-500 mb-1.5">연락처</p>
                            <p class="text-sm font-medium text-slate-800"><x-ui.phone :value="$member->phone" reveal tel /></p>
                        </div>

                        @if($member->email)
                            <div>
                                <p class="text-sm text-slate-500 mb-1.5">이메일</p>
                                <p class="text-sm font-medium text-slate-800">{{ $member->email }}</p>
                            </div>
                        @endif

                        <div>
                            <p class="text-sm text-slate-500 mb-1.5">가입일</p>
                            <p class="text-sm font-medium text-slate-800 tabular-nums">{{ $member->created_at->format('Y년 m월 d일 H:i') }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <p class="text-sm text-slate-500 mb-1.5">역할</p>
                            <div class="flex flex-wrap gap-2">
                                @if($member->hasRole('admin'))
                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-red-50 text-red-700 ring-1 ring-red-200">관리자회원</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600">일반회원</span>
                                @endif
                            </div>
                            {{-- 구조·구급 역할은 «회원 등급»이 아니라 행사별 역할이다.
                                 행사가 끝나면 그 역할도 끝나므로 여기서 «지금» 값을 보여준다. --}}
                            <p class="text-sm text-slate-500 mt-3 mb-1.5">행사 역할 (진행 중인 행사)</p>
                            <div class="flex flex-wrap gap-2">
                                @forelse($member->eventParticipations()->with('project:id,name')->get()->filter(fn ($p) => $p->project?->isActive()) as $p)
                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $p->role->badgeClasses() }}">
                                        {{ $p->project->name }} · {{ $p->role->label() }}
                                    </span>
                                @empty
                                    <span class="text-sm text-slate-400">없음</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Requests -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h2 class="text-base font-semibold text-slate-900">최근 구조 요청</h2>
                    </div>

                    @if($member->requests->isEmpty())
                        <p class="px-6 py-14 text-center text-sm text-slate-400">구조 요청 내역이 없습니다.</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach($member->requests->take(10) as $request)
                                <li class="flex items-center justify-between gap-3 px-6 py-4">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-800">구조 요청 #{{ $request->id }}</p>
                                        <p class="text-xs text-slate-400 tabular-nums">{{ $request->created_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                    <div class="text-right flex-none">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($request->status->value === 'pending') bg-amber-50 text-amber-700
                                            @elseif($request->status->value === 'in_progress') bg-blue-50 text-blue-700
                                            @elseif($request->status->value === 'completed') bg-emerald-50 text-emerald-700
                                            @else bg-slate-100 text-slate-500
                                            @endif">
                                            @if($request->status->value === 'pending') 대기중
                                            @elseif($request->status->value === 'in_progress') 진행중
                                            @elseif($request->status->value === 'completed') 완료
                                            @else {{ $request->status }}
                                            @endif
                                        </span>
                                        <div class="mt-1">
                                            <a href="{{ route('admin.requests.show', $request->id) }}"
                                               class="text-blue-600 hover:text-blue-700 text-sm font-medium">상세보기</a>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <!-- Statistics -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="text-base font-semibold text-slate-900">활동 통계</h3>
                    </div>

                    <div class="p-6 space-y-3.5">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-slate-500">총 구조 요청</span>
                            <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ $member->requests_count }}건</span>
                        </div>

                        @php ($dispatchCount = \App\Models\Dispatch::where('paramedic_id', $member->id)->count())
                        @if($dispatchCount > 0)
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-slate-500">받은 지령</span>
                                <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ $dispatchCount }}건</span>
                            </div>
                        @endif

                        <div class="flex justify-between items-center">
                            <span class="text-sm text-slate-500">가입 기간</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $member->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                @if($member->assignedRequests->isNotEmpty())
                    <!-- Assigned Requests (legacy assigned_rescuer_id — ADR-0003 이전 데이터) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100">
                            <h3 class="text-base font-semibold text-slate-900">담당 구조 요청</h3>
                        </div>

                        @if($member->assignedRequests->isEmpty())
                            <p class="px-6 py-10 text-center text-sm text-slate-400">담당한 구조 요청이 없습니다.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach($member->assignedRequests->take(5) as $request)
                                    <li class="px-6 py-3.5">
                                        <div class="flex justify-between items-start gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-800">요청 #{{ $request->id }}</p>
                                                <p class="text-xs text-slate-400 truncate">{{ $request->user->name ?? '알 수 없음' }}</p>
                                            </div>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium flex-none
                                                @if($request->status->value === 'pending') bg-amber-50 text-amber-700
                                                @elseif($request->status->value === 'in_progress') bg-blue-50 text-blue-700
                                                @elseif($request->status->value === 'completed') bg-emerald-50 text-emerald-700
                                                @else bg-slate-100 text-slate-500
                                                @endif">
                                                @if($request->status->value === 'pending') 대기중
                                                @elseif($request->status->value === 'in_progress') 진행중
                                                @elseif($request->status->value === 'completed') 완료
                                                @else {{ $request->status }}
                                                @endif
                                            </span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
