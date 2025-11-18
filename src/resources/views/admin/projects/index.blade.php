<x-layouts.admin title="프로젝트 관리 - GPS119 관리자">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">프로젝트 관리</h1>
                <p class="mt-2 text-gray-600">프로젝트를 생성하고 관리하세요.</p>
            </div>
            <a href="{{ route('admin.projects.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                새 프로젝트
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <form method="GET" action="{{ route('admin.projects.index') }}" class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-0">
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="프로젝트 이름, 설명으로 검색..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <select name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">모든 상태</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>예정</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>진행중</option>
                            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>완료</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                            검색
                        </button>
                        <a href="{{ route('admin.projects.index') }}" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                            초기화
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">프로젝트</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">기간</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">상태</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">구조요청</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">생성자</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">작업</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($projects as $project)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            @if($project->status === 'active')
                                                <span class="text-blue-500">🔵</span>
                                            @elseif($project->status === 'pending')
                                                <span class="text-yellow-500">⏳</span>
                                            @else
                                                <span class="text-gray-500">⚫</span>
                                            @endif
                                            <span class="text-sm font-medium text-gray-900">{{ $project->name }}</span>
                                        </div>
                                        @if($project->description)
                                            <div class="text-sm text-gray-500 mt-1">{{ Str::limit($project->description, 50) }}</div>
                                        @endif
                                        <div class="text-xs text-gray-400 mt-1">
                                            <code class="bg-gray-100 px-2 py-1 rounded">{{ $project->slug }}</code>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>{{ $project->start_date->format('Y-m-d') }}</div>
                                    <div>{{ $project->end_date->format('Y-m-d') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'active' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-gray-100 text-gray-800',
                                        ];
                                        $statusTexts = [
                                            'pending' => '예정',
                                            'active' => '진행중',
                                            'completed' => '완료',
                                        ];
                                    @endphp
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $statusColors[$project->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusTexts[$project->status] ?? $project->status }}
                                    </span>
                                    @if(!$project->is_active)
                                        <span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            비활성
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $project->requests_count }}건
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $project->creator->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.projects.show', $project->id) }}"
                                           class="text-blue-600 hover:text-blue-900"
                                           title="상세보기">상세</a>
                                        <a href="{{ route('admin.projects.edit', $project->id) }}"
                                           class="text-green-600 hover:text-green-900"
                                           title="수정">수정</a>
                                        <a href="{{ route('admin.projects.export-csv', $project->id) }}"
                                           class="text-purple-600 hover:text-purple-900"
                                           title="CSV 다운로드">
                                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </a>
                                        <form action="{{ route('admin.projects.clone', $project->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    onclick="return confirm('이 프로젝트를 복제하시겠습니까?')"
                                                    class="text-indigo-600 hover:text-indigo-900"
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
                                                    class="text-red-600 hover:text-red-900"
                                                    title="삭제">삭제</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    프로젝트가 없습니다.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($projects->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $projects->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
