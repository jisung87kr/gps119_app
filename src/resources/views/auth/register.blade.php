<x-layouts.app title="GPS119 - 회원가입">
    <div class="flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8 w-full">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">GPS119 회원가입</h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    이미 계정이 있으신가요?
                    <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500">
                        로그인하기
                    </a>
                </p>
            </div>

            <form class="mt-8 space-y-6" method="POST" action="{{ route('register') }}">
                @csrf

                <div class="space-y-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">연락처</label>
                        <input type="tel"
                               id="phone"
                               name="phone"
                               value="{{ old('phone') }}"
                               required
                               class="mt-1 appearance-none relative block w-full px-3 py-2 border @error('phone') border-red-300 @else border-gray-300 @enderror placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="010-1234-5678">
                        @error('phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">비밀번호</label>
                        <input type="password"
                               id="password"
                               name="password"
                               required
                               class="mt-1 appearance-none relative block w-full px-3 py-2 border @error('password') border-red-300 @else border-gray-300 @enderror placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="비밀번호">
                        @error('password')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">비밀번호 확인</label>
                        <input type="password"
                               id="password_confirmation"
                               name="password_confirmation"
                               required
                               class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="비밀번호 확인">
                    </div>
                </div>

                <div>
                    <button type="submit"
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        회원가입
                    </button>

                    <a href="{{route('login.social', 'naver')}}"
                       class="mt-2 relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">네이버 간편 가입</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
