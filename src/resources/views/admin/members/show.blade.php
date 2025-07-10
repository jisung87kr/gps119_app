<x-layouts.admin title="회원 상세정보 - GPS119 관리자">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">회원 상세정보</h1>
                <p class="mt-2 text-gray-600">{{ $member->name }}님의 정보를 확인하세요.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.members.edit', $member->id) }}" 
                   class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    정보 수정
                </a>
                <a href="{{ route('admin.members') }}" 
                   class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                    목록으로
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Member Information -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">기본 정보</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">이름</label>
                            <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $member->name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                            <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $member->formatted_phone ?? '-' }}</p>
                        </div>

                        @if($member->email)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">이메일</label>
                                <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $member->email }}</p>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">가입일</label>
                            <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $member->created_at->format('Y년 m월 d일 H:i') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">역할</label>
                            <div class="flex flex-wrap gap-2">
                                @if($member->hasRole('admin'))
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">관리자</span>
                                @endif
                                @if($member->hasRole('rescuer'))
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">구조대</span>
                                @endif
                                @if(!$member->hasAnyRole(['admin', 'rescuer']))
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">일반 사용자</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Requests -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">최근 구조 요청</h2>
                    
                    @if($member->requests->isEmpty())
                        <p class="text-gray-500 text-center py-8">구조 요청 내역이 없습니다.</p>
                    @else
                        <div class="space-y-4">
                            @foreach($member->requests->take(10) as $request)
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-900">구조 요청 #{{ $request->id }}</p>
                                        <p class="text-sm text-gray-600">{{ $request->created_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                    <div class="text-right">
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
                                        <div class="mt-1">
                                            <a href="{{ route('admin.requests.show', $request->id) }}" 
                                               class="text-blue-600 hover:text-blue-900 text-sm">상세보기</a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Statistics -->
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">활동 통계</h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">총 구조 요청</span>
                            <span class="font-semibold text-gray-900">{{ $member->requests_count }}건</span>
                        </div>
                        
                        @if($member->hasRole('rescuer'))
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">처리한 요청</span>
                                <span class="font-semibold text-gray-900">{{ $member->assigned_requests_count }}건</span>
                            </div>
                        @endif

                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">가입 기간</span>
                            <span class="font-semibold text-gray-900">{{ $member->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                @if($member->hasRole('rescuer'))
                    <!-- Assigned Requests -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">담당 구조 요청</h3>
                        
                        @if($member->assignedRequests->isEmpty())
                            <p class="text-gray-500 text-center py-4">담당한 구조 요청이 없습니다.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($member->assignedRequests->take(5) as $request)
                                    <div class="p-3 bg-gray-50 rounded">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">요청 #{{ $request->id }}</p>
                                                <p class="text-xs text-gray-600">{{ $request->user->name ?? '알 수 없음' }}</p>
                                            </div>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
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
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>