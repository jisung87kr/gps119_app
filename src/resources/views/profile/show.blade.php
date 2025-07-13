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

                    <!-- 소셜로그인 정보 표시 -->
                    @if(auth()->user()->provider)
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center">
                                @if(auth()->user()->provider === 'naver')
                                    <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center mr-4">
                                        <span class="text-white font-bold text-lg">N</span>
                                    </div>
                                @elseif(auth()->user()->provider === 'kakao')
                                    <div class="w-12 h-12 bg-yellow-400 rounded-lg flex items-center justify-center mr-4">
                                        <span class="text-gray-800 font-bold text-lg">K</span>
                                    </div>
                                @else
                                    <div class="w-12 h-12 bg-gray-500 rounded-lg flex items-center justify-center mr-4">
                                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                @endif
                                <div class="flex-1">
                                    <div class="flex items-center mb-1">
                                        <h3 class="text-base font-semibold text-gray-900">
                                            {{ ucfirst(auth()->user()->provider) }} 소셜 계정
                                        </h3>
                                        <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            연동됨
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600">
                                        소셜 계정으로 간편하게 로그인할 수 있습니다.
                                    </p>
                                    @if(auth()->user()->avatar)
                                        <div class="mt-2 flex items-center">
                                            <img src="{{ auth()->user()->avatar }}" alt="프로필 이미지" class="w-8 h-8 rounded-full border-2 border-white shadow-sm">
                                            <span class="ml-2 text-xs text-gray-500">프로필 이미지 동기화됨</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

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

                        @if(!auth()->user()->provider)
                            <a href="{{ route('profile.password.edit') }}" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 block text-center">
                                비밀번호 변경
                            </a>
                        @else
                            <div class="w-full bg-gray-200 text-gray-500 font-semibold py-3 px-4 rounded-lg text-center cursor-not-allowed">
                                <div class="flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    소셜 계정 (비밀번호 불필요)
                                </div>
                            </div>
                        @endif

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
