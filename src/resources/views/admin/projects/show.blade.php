<x-layouts.admin title="{{ $project->name }} - GPS119 관리자">
    <div class="p-5 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $project->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $project->description }}</p>
            </div>
            <div class="sm:ml-auto flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.projects.participants', $project->id) }}"
                   class="inline-flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    참가자 관리
                </a>
                <a href="{{ route('admin.projects.edit', $project->id) }}"
                   class="inline-flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors">
                    수정
                </a>
                @unless($project->is_default)
                    <form action="{{ route('admin.projects.destroy', $project->id) }}" method="POST" class="inline" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 bg-white text-red-600 border border-red-200 px-4 py-2.5 rounded-xl hover:bg-red-50 font-medium text-sm transition-colors">
                            삭제
                        </button>
                    </form>
                @endunless
            </div>
        </div>

        <!-- Project Info Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-semibold text-slate-900">프로젝트 정보</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-slate-500">시작일</p>
                        <p class="mt-1 text-sm font-medium text-slate-800 tabular-nums">{{ $project->start_date->format('Y-m-d') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">종료일</p>
                        <p class="mt-1 text-sm font-medium text-slate-800 tabular-nums">{{ $project->end_date->format('Y-m-d') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">상태</p>
                        @php
                            $statusColors = [
                                'pending' => 'bg-amber-50 text-amber-700',
                                'active' => 'bg-emerald-50 text-emerald-700',
                                'completed' => 'bg-slate-100 text-slate-500',
                            ];
                            $statusTexts = [
                                'pending' => '예정',
                                'active' => '진행중',
                                'completed' => '완료',
                            ];
                        @endphp
                        <span class="mt-1 inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $statusColors[$project->status] ?? 'bg-slate-100 text-slate-500' }}">
                            {{ $statusTexts[$project->status] ?? $project->status }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">활성화</p>
                        <span class="mt-1 inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $project->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $project->is_active ? '활성' : '비활성' }}
                        </span>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-slate-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- URL Section -->
                        <div>
                            <p class="block text-sm font-medium text-slate-700 mb-1.5">프로젝트 URL</p>
                            <div class="flex gap-2 mb-3">
                                <input type="text"
                                       id="projectUrl"
                                       value="{{ $project->getUrl() }}"
                                       readonly
                                       class="flex-1 px-3.5 py-2.5 border border-slate-200 rounded-xl bg-slate-50 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                                <button onclick="copyUrl()"
                                        class="inline-flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                                    복사
                                </button>
                            </div>
                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-2">
                                <form action="{{ route('admin.projects.clone', $project->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                            onclick="return confirm('이 프로젝트를 복제하시겠습니까?')"
                                            class="inline-flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        복제
                                    </button>
                                </form>
                                <a href="{{ route('admin.projects.export-csv', $project->id) }}"
                                   class="inline-flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    CSV 다운로드
                                </a>
                            </div>
                        </div>

                        <!-- QR Code Section -->
                        <div class="text-center">
                            <p class="block text-sm font-medium text-slate-700 mb-1.5">QR 코드</p>
                            <div class="inline-block p-3 bg-white border border-slate-200 rounded-2xl shadow-sm">
                                <img src="{{ route('admin.projects.qrcode', $project->id) }}"
                                     alt="QR Code"
                                     class="w-40 h-40">
                            </div>
                            <p class="mt-1.5 text-xs text-slate-400">스캔하여 요청 페이지로 이동</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-slate-100">
                    <p class="text-sm text-slate-500">생성자</p>
                    <p class="mt-1 text-sm font-medium text-slate-800">{{ $project->creator->name ?? '-' }}</p>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-slate-100 grid place-items-center">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">전체 요청</p>
                        <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $project->requests_count }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-amber-50 grid place-items-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">대기중</p>
                        <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $project->pending_requests_count }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-blue-50 grid place-items-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">진행중</p>
                        <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $project->in_progress_requests_count }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 grid place-items-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">완료</p>
                        <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $project->completed_requests_count }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Stats Chart -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 일별 요청 추이 -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="text-base font-semibold text-slate-900">일별 요청 추이</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-2">
                        @if($dailyStats->isEmpty())
                            <p class="text-sm text-slate-400 text-center py-8">데이터가 없습니다.</p>
                        @else
                            @php
                                $maxCount = $dailyStats->max('count');
                            @endphp
                            @foreach($dailyStats as $stat)
                                <div class="flex items-center gap-3">
                                    <div class="w-24 text-sm text-slate-500 tabular-nums">
                                        {{ \Carbon\Carbon::parse($stat->date)->format('m/d') }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="w-full bg-slate-100 rounded-full h-6 relative">
                                            <div class="bg-blue-600 h-6 rounded-full flex items-center justify-end pr-2"
                                                 style="width: {{ $maxCount > 0 ? ($stat->count / $maxCount * 100) : 0 }}%">
                                                <span class="text-xs font-semibold text-white tabular-nums">{{ $stat->count }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <!-- 구조대원별 처리 현황 -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                <div class="px-6 py-4 border-b border-slate-100">
                    <h3 class="text-base font-semibold text-slate-900">구조대원별 처리 현황</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        @if($rescuerStats->isEmpty())
                            <p class="text-sm text-slate-400 text-center py-8">배정된 구조대원이 없습니다.</p>
                        @else
                            @foreach($rescuerStats as $rescuer)
                                <div class="border border-slate-200 rounded-xl p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="font-medium text-slate-900">{{ $rescuer->name }}</div>
                                        <div class="text-sm text-slate-500 tabular-nums">총 {{ $rescuer->total_assigned }}건</div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 mt-2">
                                        <div class="bg-blue-50 rounded-lg p-2.5">
                                            <div class="text-xs text-slate-500">진행중</div>
                                            <div class="text-lg font-semibold text-slate-900 tabular-nums">{{ $rescuer->in_progress_count }}</div>
                                        </div>
                                        <div class="bg-emerald-50 rounded-lg p-2.5">
                                            <div class="text-xs text-slate-500">완료</div>
                                            <div class="text-lg font-semibold text-slate-900 tabular-nums">{{ $rescuer->completed_count }}</div>
                                        </div>
                                    </div>
                                    @if($rescuer->total_assigned > 0)
                                        <div class="mt-3">
                                            <div class="w-full bg-slate-100 rounded-full h-2">
                                                <div class="bg-emerald-500 h-2 rounded-full"
                                                     style="width: {{ ($rescuer->completed_count / $rescuer->total_assigned * 100) }}%">
                                                </div>
                                            </div>
                                            <div class="text-xs text-slate-400 mt-1 text-right tabular-nums">
                                                완료율 {{ number_format($rescuer->completed_count / $rescuer->total_assigned * 100, 1) }}%
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Distribution -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="text-base font-semibold text-slate-900">상태별 요청 분포</h3>
            </div>
            <div class="p-6">
                @php
                    $totalRequests = $project->requests_count;
                    $statuses = [
                        ['label' => '대기중', 'count' => $project->pending_requests_count, 'color' => 'bg-amber-500'],
                        ['label' => '진행중', 'count' => $project->in_progress_requests_count, 'color' => 'bg-blue-500'],
                        ['label' => '완료', 'count' => $project->completed_requests_count, 'color' => 'bg-emerald-500'],
                        ['label' => '취소됨', 'count' => $project->cancelled_requests_count, 'color' => 'bg-red-500'],
                    ];
                @endphp

                @if($totalRequests > 0)
                    <div class="mb-5">
                        <div class="flex w-full h-8 rounded-lg overflow-hidden">
                            @foreach($statuses as $status)
                                @if($status['count'] > 0)
                                    <div class="{{ $status['color'] }}"
                                         style="width: {{ ($status['count'] / $totalRequests * 100) }}%"
                                         title="{{ $status['label'] }}: {{ $status['count'] }}건">
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach($statuses as $status)
                            <div class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-2.5 h-2.5 rounded-full {{ $status['color'] }}"></div>
                                    <span class="text-sm text-slate-500">{{ $status['label'] }}</span>
                                </div>
                                <div class="mt-1">
                                    <span class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $status['count'] }}</span>
                                    <span class="text-sm text-slate-400 tabular-nums">
                                        ({{ number_format($status['count'] / $totalRequests * 100, 1) }}%)
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-400 text-center py-8">데이터가 없습니다.</p>
                @endif
            </div>
        </div>

        <!-- Requests List -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-semibold text-slate-900">구조요청 목록</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/70">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">요청자</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">상태</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">담당자</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">요청일시</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">작업</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($project->requests as $request)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 tabular-nums">
                                    #{{ $request->id }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900">{{ $request->user->name ?? '알 수 없음' }}</div>
                                    <div class="text-sm text-slate-500 tabular-nums">{{ $request->user->formatted_phone ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-amber-50 text-amber-700',
                                            'in_progress' => 'bg-blue-50 text-blue-700',
                                            'completed' => 'bg-emerald-50 text-emerald-700',
                                            'cancelled' => 'bg-red-50 text-red-700',
                                        ];
                                        $statusTexts = [
                                            'pending' => '대기중',
                                            'in_progress' => '진행중',
                                            'completed' => '완료',
                                            'cancelled' => '취소됨',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $statusColors[$request->status->value] ?? 'bg-slate-100 text-slate-600' }}">
                                        {{ $statusTexts[$request->status->value] ?? $request->status->value }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                    {{ $request->assignedRescuer->name ?? '미배정' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 tabular-nums">
                                    {{ $request->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="/admin/requests/{{ $request->id }}" class="text-blue-600 hover:text-blue-700">상세보기</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-400">
                                    구조요청이 없습니다.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function copyUrl() {
            const urlInput = document.getElementById('projectUrl');
            urlInput.select();
            urlInput.setSelectionRange(0, 99999); // For mobile devices

            try {
                navigator.clipboard.writeText(urlInput.value).then(() => {
                    alert('URL이 클립보드에 복사되었습니다!');
                }).catch(() => {
                    // Fallback for older browsers
                    document.execCommand('copy');
                    alert('URL이 클립보드에 복사되었습니다!');
                });
            } catch (err) {
                console.error('Failed to copy:', err);
                alert('URL 복사에 실패했습니다.');
            }
        }
    </script>
</x-layouts.admin>
