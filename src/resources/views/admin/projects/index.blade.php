<x-layouts.admin title="프로젝트 관리 - GPS119 관리자">
    <div class="p-5 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">프로젝트 관리</h1>
                <p class="mt-1 text-sm text-slate-500">프로젝트를 생성하고 관리합니다.</p>
            </div>
            <a href="{{ route('admin.projects.create') }}"
               class="sm:ml-auto inline-flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                새 프로젝트
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="p-4 sm:p-5">
                <form method="GET" action="{{ route('admin.projects.index') }}" class="flex flex-col sm:flex-row flex-wrap gap-3">
                    <div class="flex-1 min-w-0 relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="프로젝트 이름, 설명으로 검색..."
                               class="w-full pl-9 pr-3 py-2.5 border border-slate-200 rounded-xl bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                    </div>
                    <select name="status" class="px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                        <option value="">모든 상태</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>예정</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>진행중</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>완료</option>
                    </select>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 sm:flex-none bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 transition font-medium text-sm shadow-sm shadow-blue-600/20">
                            검색
                        </button>
                        <a href="{{ route('admin.projects.index') }}" class="flex-1 sm:flex-none text-center bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 transition font-medium text-sm">
                            초기화
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/70">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">프로젝트</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">기간</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">상태</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">구조요청</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">생성자</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">작업</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($projects as $project)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            @if($project->status === 'active')
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 flex-none"></span>
                                            @elseif($project->status === 'pending')
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 flex-none"></span>
                                            @else
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-300 flex-none"></span>
                                            @endif
                                            <span class="text-sm font-medium text-slate-800">{{ $project->name }}</span>
                                        </div>
                                        @if($project->description)
                                            <div class="text-sm text-slate-500 mt-1">{{ Str::limit($project->description, 50) }}</div>
                                        @endif
                                        <div class="text-xs text-slate-400 mt-1.5">
                                            <code class="bg-slate-100 text-slate-500 px-2 py-0.5 rounded-md font-mono">{{ $project->slug }}</code>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 tabular-nums">
                                    <div>{{ $project->start_date->format('Y-m-d') }}</div>
                                    <div class="text-slate-400">{{ $project->end_date->format('Y-m-d') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full {{ $statusColors[$project->status] ?? 'bg-slate-100 text-slate-500' }}">
                                        {{ $statusTexts[$project->status] ?? $project->status }}
                                    </span>
                                    @if(!$project->is_active)
                                        <span class="ml-1.5 inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-500">
                                            비활성
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-700 tabular-nums">
                                    {{ $project->requests_count }}건
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                    {{ $project->creator->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('admin.projects.show', $project->id) }}"
                                           class="text-blue-600 hover:text-blue-700"
                                           title="상세보기">상세</a>
                                        <a href="{{ route('admin.projects.edit', $project->id) }}"
                                           class="text-slate-600 hover:text-slate-900"
                                           title="수정">수정</a>
                                        <a href="{{ route('admin.projects.export-csv', $project->id) }}"
                                           class="text-slate-400 hover:text-slate-700"
                                           title="CSV 다운로드">
                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </a>
                                        <form action="{{ route('admin.projects.clone', $project->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    onclick="return confirm('이 프로젝트를 복제하시겠습니까?')"
                                                    class="text-slate-400 hover:text-slate-700"
                                                    title="복제">
                                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                </svg>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.projects.destroy', $project->id) }}" method="POST" class="inline" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-red-600 hover:text-red-700"
                                                    title="삭제">삭제</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-400">
                                    프로젝트가 없습니다.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($projects->hasPages())
                <div class="px-6 py-4 border-t border-slate-100">
                    {{ $projects->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
