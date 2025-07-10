<x-layouts.admin title="GPS119 관리자 대시보드">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">관리자 대시보드</h1>
            <p class="mt-2 text-gray-600">GPS119 시스템 전체 현황을 확인하세요.</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Users -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">전체 사용자</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($stats['total_users']) }}</p>
                    </div>
                </div>
            </div>

            <!-- Total Requests -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">전체 구조요청</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($stats['total_requests']) }}</p>
                    </div>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">대기중 요청</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($stats['pending_requests']) }}</p>
                    </div>
                </div>
            </div>

            <!-- Rescuers -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">구조대원</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($stats['total_rescuers']) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Request Status Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">구조 요청 현황</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">대기중</span>
                        <span class="text-sm font-medium text-yellow-600">{{ $stats['pending_requests'] }}건</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">진행중</span>
                        <span class="text-sm font-medium text-blue-600">{{ $stats['in_progress_requests'] }}건</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">완료</span>
                        <span class="text-sm font-medium text-green-600">{{ $stats['completed_requests'] }}건</span>
                    </div>
                </div>
            </div>

            <!-- User Role Distribution -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">사용자 구성</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">일반 사용자</span>
                        <span class="text-sm font-medium text-blue-600">{{ $stats['total_users'] - $stats['total_admins'] - $stats['total_rescuers'] }}명</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">구조대원</span>
                        <span class="text-sm font-medium text-green-600">{{ $stats['total_rescuers'] }}명</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">관리자</span>
                        <span class="text-sm font-medium text-red-600">{{ $stats['total_admins'] }}명</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">빠른 작업</h3>
                <div class="space-y-3">
                    <a href="{{ route('admin.members') }}" class="block w-full bg-blue-600 text-white text-center py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                        회원 관리
                    </a>
                    <a href="{{ route('admin.requests') }}" class="block w-full bg-green-600 text-white text-center py-2 px-4 rounded-md hover:bg-green-700 transition duration-200">
                        구조요청 관리
                    </a>
                    <a href="{{ route('admin.requests', ['status' => 'pending']) }}" class="block w-full bg-yellow-600 text-white text-center py-2 px-4 rounded-md hover:bg-yellow-700 transition duration-200">
                        대기중 요청 확인
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Requests -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">최근 구조 요청</h3>
                </div>
                <div class="p-6">
                    @if($recent_requests->isEmpty())
                        <p class="text-gray-500 text-center py-4">최근 구조 요청이 없습니다.</p>
                    @else
                        <div class="space-y-4">
                            @foreach($recent_requests as $request)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $request->user->name ?? '알 수 없음' }}</p>
                                        <p class="text-sm text-gray-600">{{ $request->created_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($request->status === 'pending') bg-yellow-100 text-yellow-800
                                        @elseif($request->status === 'in_progress') bg-blue-100 text-blue-800
                                        @elseif($request->status === 'completed') bg-green-100 text-green-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        @if($request->status === 'pending') 대기중
                                        @elseif($request->status === 'in_progress') 진행중
                                        @elseif($request->status === 'completed') 완료
                                        @else {{ $request->status }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Users -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">최근 가입 사용자</h3>
                </div>
                <div class="p-6">
                    @if($recent_users->isEmpty())
                        <p class="text-gray-500 text-center py-4">최근 가입한 사용자가 없습니다.</p>
                    @else
                        <div class="space-y-4">
                            @foreach($recent_users as $user)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $user->name }}</p>
                                        <p class="text-sm text-gray-600">{{ $user->created_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                    <div class="text-right">
                                        @if($user->hasRole('admin'))
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">관리자</span>
                                        @elseif($user->hasRole('rescuer'))
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">구조대</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">사용자</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>