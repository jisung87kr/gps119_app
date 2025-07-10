<x-layouts.app>
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-8 rounded-lg shadow-sm">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">프로필</h1>
            <p class="text-xl text-gray-600 mb-6">계정 정보를 확인하고 관리하세요.</p>
        </div>

        @if(session('status'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mt-4">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
            <!-- Profile Information -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">개인 정보</h2>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">이름</label>
                            <div class="bg-gray-50 rounded-lg p-3 border">
                                <p class="text-gray-900">{{ auth()->user()->name }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">연락처</label>
                            <div class="bg-gray-50 rounded-lg p-3 border">
                                <p class="text-gray-900">{{ auth()->user()->formatted_phone }}</p>
                            </div>
                        </div>

                        @if(auth()->user()->email)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">이메일</label>
                                <div class="bg-gray-50 rounded-lg p-3 border">
                                    <p class="text-gray-900">{{ auth()->user()->email }}</p>
                                </div>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">가입일</label>
                            <div class="bg-gray-50 rounded-lg p-3 border">
                                <p class="text-gray-900">{{ auth()->user()->created_at->format('Y년 m월 d일') }}</p>
                            </div>
                        </div>

                        @if(auth()->user()->hasRole('rescuer'))
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">권한</label>
                                <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                                    <p class="text-green-800 font-medium">구조대</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Profile Actions -->
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">계정 관리</h3>

                    <div class="space-y-4">
                        <a href="{{ route('profile.edit') }}" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 block text-center">
                            프로필 수정
                        </a>

                        <a href="{{ route('profile.password.edit') }}" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 block text-center">
                            비밀번호 변경
                        </a>

                        <a href="{{ route('profile.delete') }}" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 block text-center">
                            회원 탈퇴
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200">
                                로그아웃
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">통계</h3>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">총 구조 요청</span>
                        <span class="font-semibold text-gray-900">{{ auth()->user()->requests()->count() }}건</span>
                    </div>
                    
                    @if(auth()->user()->hasRole('rescuer'))
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">처리한 요청</span>
                            <span class="font-semibold text-gray-900">{{ auth()->user()->assignedRequests()->count() }}건</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="mt-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">최근 활동</h3>
                
                @if(auth()->user()->requests()->latest()->limit(5)->get()->isEmpty())
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="text-gray-500">아직 구조 요청 내역이 없습니다.</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach(auth()->user()->requests()->latest()->limit(5)->get() as $request)
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <h4 class="font-medium text-gray-900">구조 요청</h4>
                                    <p class="text-sm text-gray-600">{{ $request->created_at->format('Y년 m월 d일 H:i') }}</p>
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
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
