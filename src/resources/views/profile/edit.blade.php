<x-layouts.app>
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="w-full max-w-2xl mx-auto mt-8">
            <div class="">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">프로필 수정</h1>
                <p class="text-xl text-gray-600 mb-6">개인 정보를 수정하세요.</p>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                @if(session('status'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                        {{ session('status') }}
                    </div>
                @endif

                <!-- 소셜로그인 정보 표시 -->
                @if(auth()->user()->provider)
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            @if(auth()->user()->provider === 'naver')
                                <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-white font-bold text-sm">N</span>
                                </div>
                            @elseif(auth()->user()->provider === 'kakao')
                                <div class="w-10 h-10 bg-yellow-400 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-gray-800 font-bold text-sm">K</span>
                                </div>
                            @else
                                <div class="w-10 h-10 bg-gray-500 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                            @endif
                            <div class="flex-1">
                                <div class="flex items-center">
                                    <h3 class="text-sm font-semibold text-gray-900">
                                        {{ ucfirst(auth()->user()->provider) }} 계정으로 가입
                                    </h3>
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        연동됨
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1">
                                    소셜 계정으로 간편하게 로그인할 수 있습니다.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">이름</label>
                        <div class="relative">
                            <input type="text"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                                   id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required>
                            @if(auth()->user()->provider)
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                </div>
                            @endif
                        </div>
                        @error('name')
                        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                        @if(auth()->user()->provider)
                            <p class="text-xs text-gray-500 mt-1">{{ ucfirst(auth()->user()->provider) }}에서 가져온 정보입니다.</p>
                        @endif
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                        <input type="tel"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror"
                               id="phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required placeholder="010-1234-5678">
                        @error('phone')
                        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                            변경사항 저장
                        </button>
                        <a href="{{ route('profile.show') }}" class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-200 text-center">
                            취소
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
