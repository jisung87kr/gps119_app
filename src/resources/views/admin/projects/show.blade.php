<x-layouts.admin title="{{ $project->name }} - GPS119 관리자">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $project->name }}</h1>
                    <p class="mt-2 text-gray-600">{{ $project->description }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.projects.edit', $project->id) }}"
                       class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                        수정
                    </a>
                    <form action="{{ route('admin.projects.destroy', $project->id) }}" method="POST" class="inline" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition duration-200">
                            삭제
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Project Info Card -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">프로젝트 정보</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-500">시작일</p>
                    <p class="text-base font-medium text-gray-900">{{ $project->start_date->format('Y-m-d') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">종료일</p>
                    <p class="text-base font-medium text-gray-900">{{ $project->end_date->format('Y-m-d') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">상태</p>
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
                    <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full {{ $statusColors[$project->status] ?? 'bg-gray-100 text-gray-800' }}">
                        {{ $statusTexts[$project->status] ?? $project->status }}
                    </span>
                </div>
                <div>
                    <p class="text-sm text-gray-500">활성화</p>
                    <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full {{ $project->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $project->is_active ? '활성' : '비활성' }}
                    </span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- URL Section -->
                    <div>
                        <p class="text-sm text-gray-500 mb-2">프로젝트 URL</p>
                        <div class="flex gap-2 mb-3">
                            <input type="text"
                                   id="projectUrl"
                                   value="{{ $project->getUrl() }}"
                                   readonly
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700 text-sm">
                            <button onclick="copyUrl()"
                                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition duration-200">
                                복사
                            </button>
                        </div>
                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <form action="{{ route('admin.projects.clone', $project->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                        onclick="return confirm('이 프로젝트를 복제하시겠습니까?')"
                                        class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition duration-200 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    복제
                                </button>
                            </form>
                            <a href="{{ route('admin.projects.export-csv', $project->id) }}"
                               class="px-3 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition duration-200 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                CSV 다운로드
                            </a>
                        </div>
                    </div>

                    <!-- QR Code Section -->
                    <div class="text-center">
                        <p class="text-sm text-gray-500 mb-2">QR 코드</p>
                        <div class="inline-block p-3 bg-white border-2 border-gray-200 rounded-lg">
                            <img src="{{ route('admin.projects.qrcode', $project->id) }}"
                                 alt="QR Code"
                                 class="w-40 h-40">
                        </div>
                        <p class="text-xs text-gray-500 mt-2">스캔하여 요청 페이지로 이동</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-sm text-gray-500">생성자</p>
                <p class="text-base font-medium text-gray-900">{{ $project->creator->name ?? '-' }}</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-gray-600">전체 요청</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $project->requests_count }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-600">대기중</p>
                        <p class="text-lg font-semibold text-yellow-900">{{ $project->pending_requests_count }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-600">진행중</p>
                        <p class="text-lg font-semibold text-blue-900">{{ $project->in_progress_requests_count }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-green-50 rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-600">완료</p>
                        <p class="text-lg font-semibold text-green-900">{{ $project->completed_requests_count }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Stats Chart -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- 일별 요청 추이 -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">일별 요청 추이</h3>
                <div class="space-y-2">
                    @if($dailyStats->isEmpty())
                        <p class="text-gray-500 text-center py-8">데이터가 없습니다.</p>
                    @else
                        @php
                            $maxCount = $dailyStats->max('count');
                        @endphp
                        @foreach($dailyStats as $stat)
                            <div class="flex items-center gap-3">
                                <div class="w-24 text-sm text-gray-600">
                                    {{ \Carbon\Carbon::parse($stat->date)->format('m/d') }}
                                </div>
                                <div class="flex-1">
                                    <div class="w-full bg-gray-200 rounded-full h-6 relative">
                                        <div class="bg-purple-600 h-6 rounded-full flex items-center justify-end pr-2"
                                             style="width: {{ $maxCount > 0 ? ($stat->count / $maxCount * 100) : 0 }}%">
                                            <span class="text-xs font-semibold text-white">{{ $stat->count }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- 구조대원별 처리 현황 -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">구조대원별 처리 현황</h3>
                <div class="space-y-3">
                    @if($rescuerStats->isEmpty())
                        <p class="text-gray-500 text-center py-8">배정된 구조대원이 없습니다.</p>
                    @else
                        @foreach($rescuerStats as $rescuer)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="font-medium text-gray-900">{{ $rescuer->name }}</div>
                                    <div class="text-sm text-gray-500">총 {{ $rescuer->total_assigned }}건</div>
                                </div>
                                <div class="grid grid-cols-2 gap-3 mt-2">
                                    <div class="bg-blue-50 rounded p-2">
                                        <div class="text-xs text-blue-600">진행중</div>
                                        <div class="text-lg font-semibold text-blue-900">{{ $rescuer->in_progress_count }}</div>
                                    </div>
                                    <div class="bg-green-50 rounded p-2">
                                        <div class="text-xs text-green-600">완료</div>
                                        <div class="text-lg font-semibold text-green-900">{{ $rescuer->completed_count }}</div>
                                    </div>
                                </div>
                                @if($rescuer->total_assigned > 0)
                                    <div class="mt-2">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-600 h-2 rounded-full"
                                                 style="width: {{ ($rescuer->completed_count / $rescuer->total_assigned * 100) }}%">
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1 text-right">
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

        <!-- Status Distribution -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">상태별 요청 분포</h3>
            @php
                $totalRequests = $project->requests_count;
                $statuses = [
                    ['label' => '대기중', 'count' => $project->pending_requests_count, 'color' => 'bg-yellow-500'],
                    ['label' => '진행중', 'count' => $project->in_progress_requests_count, 'color' => 'bg-blue-500'],
                    ['label' => '완료', 'count' => $project->completed_requests_count, 'color' => 'bg-green-500'],
                    ['label' => '취소됨', 'count' => $project->cancelled_requests_count, 'color' => 'bg-red-500'],
                ];
            @endphp

            @if($totalRequests > 0)
                <div class="mb-4">
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
                                <div class="w-3 h-3 rounded {{ $status['color'] }}"></div>
                                <span class="text-sm text-gray-600">{{ $status['label'] }}</span>
                            </div>
                            <div class="mt-1">
                                <span class="text-2xl font-bold text-gray-900">{{ $status['count'] }}</span>
                                <span class="text-sm text-gray-500">
                                    ({{ number_format($status['count'] / $totalRequests * 100, 1) }}%)
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-center py-8">데이터가 없습니다.</p>
            @endif
        </div>

        <!-- Requests List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">구조요청 목록</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">요청자</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">상태</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">담당자</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">요청일시</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">작업</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($project->requests as $request)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #{{ $request->id }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $request->user->name ?? '알 수 없음' }}</div>
                                    <div class="text-sm text-gray-500">{{ $request->user->formatted_phone ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'in_progress' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                        ];
                                        $statusTexts = [
                                            'pending' => '대기중',
                                            'in_progress' => '진행중',
                                            'completed' => '완료',
                                            'cancelled' => '취소됨',
                                        ];
                                    @endphp
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $statusColors[$request->status->value] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusTexts[$request->status->value] ?? $request->status->value }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $request->assignedRescuer->name ?? '미배정' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $request->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="/admin/requests/{{ $request->id }}" class="text-blue-600 hover:text-blue-900">상세보기</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
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
