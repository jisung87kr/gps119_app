<x-layouts.admin title="구조요청 상세정보 - GPS119 관리자">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">구조요청 #{{ $rescueRequest->id }}</h1>
                <p class="mt-2 text-gray-600">구조 요청 상세 정보를 확인하고 관리하세요.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.requests') }}" 
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
            <!-- Request Information -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">요청 정보</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">요청 ID</label>
                            <p class="text-gray-900 bg-gray-50 rounded p-3">#{{ $rescueRequest->id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">현재 상태</label>
                            <div class="bg-gray-50 rounded p-3">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    @if($rescueRequest->status === 'pending') bg-yellow-100 text-yellow-800
                                    @elseif($rescueRequest->status === 'in_progress') bg-blue-100 text-blue-800
                                    @elseif($rescueRequest->status === 'completed') bg-green-100 text-green-800
                                    @elseif($rescueRequest->status === 'cancelled') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    @if($rescueRequest->status === 'pending') 대기중
                                    @elseif($rescueRequest->status === 'in_progress') 진행중
                                    @elseif($rescueRequest->status === 'completed') 완료
                                    @elseif($rescueRequest->status === 'cancelled') 취소됨
                                    @else {{ $rescueRequest->status }}
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">요청일시</label>
                            <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $rescueRequest->created_at->format('Y년 m월 d일 H:i') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">담당 구조대원</label>
                            <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $rescueRequest->assignedRescuer->name ?? '미배정' }}</p>
                        </div>

                        @if($rescueRequest->location_latitude && $rescueRequest->location_longitude)
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">위치 정보</label>
                                <div class="bg-gray-50 rounded p-3">
                                    <p class="text-gray-900">
                                        위도: {{ $rescueRequest->location_latitude }}, 
                                        경도: {{ $rescueRequest->location_longitude }}
                                    </p>
                                    <a href="https://maps.google.com/?q={{ $rescueRequest->location_latitude }},{{ $rescueRequest->location_longitude }}" 
                                       target="_blank"
                                       class="text-blue-600 hover:text-blue-800 text-sm">Google 지도에서 보기</a>
                                </div>
                            </div>
                        @endif

                        @if($rescueRequest->description)
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">요청 내용</label>
                                <div class="bg-gray-50 rounded p-3">
                                    <p class="text-gray-900">{{ $rescueRequest->description }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Requester Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">요청자 정보</h2>
                    
                    @if($rescueRequest->user)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">이름</label>
                                <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $rescueRequest->user->name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                                <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $rescueRequest->user->formatted_phone }}</p>
                            </div>

                            @if($rescueRequest->user->email)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">이메일</label>
                                    <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $rescueRequest->user->email }}</p>
                                </div>
                            @endif

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">가입일</label>
                                <p class="text-gray-900 bg-gray-50 rounded p-3">{{ $rescueRequest->user->created_at->format('Y년 m월 d일') }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="{{ route('admin.members.show', $rescueRequest->user->id) }}" 
                               class="text-blue-600 hover:text-blue-900">요청자 상세정보 보기</a>
                        </div>
                    @else
                        <p class="text-gray-500">요청자 정보를 찾을 수 없습니다.</p>
                    @endif
                </div>
            </div>

            <!-- Management Actions -->
            <div class="space-y-6">
                <!-- Status Update -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">상태 관리</h3>
                    
                    <form method="POST" action="{{ route('admin.requests.update', $rescueRequest->id) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">상태 변경</label>
                            <select name="status" id="status" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="pending" {{ $rescueRequest->status === 'pending' ? 'selected' : '' }}>대기중</option>
                                <option value="in_progress" {{ $rescueRequest->status === 'in_progress' ? 'selected' : '' }}>진행중</option>
                                <option value="completed" {{ $rescueRequest->status === 'completed' ? 'selected' : '' }}>완료</option>
                                <option value="cancelled" {{ $rescueRequest->status === 'cancelled' ? 'selected' : '' }}>취소됨</option>
                            </select>
                        </div>

                        <div>
                            <label for="assigned_rescuer_id" class="block text-sm font-medium text-gray-700 mb-1">담당 구조대원</label>
                            <select name="assigned_rescuer_id" id="assigned_rescuer_id" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">미배정</option>
                                @foreach($rescuers as $rescuer)
                                    <option value="{{ $rescuer->id }}" 
                                            {{ $rescueRequest->assigned_rescuer_id == $rescuer->id ? 'selected' : '' }}>
                                        {{ $rescuer->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" 
                                class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                            상태 업데이트
                        </button>
                    </form>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">빠른 작업</h3>
                    
                    <div class="space-y-3">
                        @if($rescueRequest->status === 'pending')
                            <form method="POST" action="{{ route('admin.requests.update', $rescueRequest->id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="in_progress">
                                <button type="submit" 
                                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200"
                                        onclick="return confirm('이 요청을 진행중으로 변경하시겠습니까?')">
                                    진행중으로 변경
                                </button>
                            </form>
                        @endif

                        @if($rescueRequest->status === 'in_progress')
                            <form method="POST" action="{{ route('admin.requests.update', $rescueRequest->id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" 
                                        class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition duration-200"
                                        onclick="return confirm('이 요청을 완료로 변경하시겠습니까?')">
                                    완료로 변경
                                </button>
                            </form>
                        @endif

                        @if($rescueRequest->status !== 'cancelled' && $rescueRequest->status !== 'completed')
                            <form method="POST" action="{{ route('admin.requests.update', $rescueRequest->id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" 
                                        class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition duration-200"
                                        onclick="return confirm('이 요청을 취소하시겠습니까? 이 작업은 되돌릴 수 없습니다.')">
                                    요청 취소
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <!-- Request Timeline -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">처리 기록</h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">요청 생성</p>
                                <p class="text-xs text-gray-500">{{ $rescueRequest->created_at->format('Y-m-d H:i') }}</p>
                            </div>
                        </div>

                        @if($rescueRequest->updated_at > $rescueRequest->created_at)
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">상태 업데이트</p>
                                    <p class="text-xs text-gray-500">{{ $rescueRequest->updated_at->format('Y-m-d H:i') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>