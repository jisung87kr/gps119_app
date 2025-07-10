<x-layouts.app>
    <div class="flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8 w-full">
        <div class="max-w-md w-full space-y-8">
            <div class="bg-white shadow-md rounded-lg p-6">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">비밀번호 찾기</h2>
                </div>
                
                @if (session('status'))
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                        <input type="tel" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror"
                               id="phone" name="phone" value="{{ old('phone') }}" required placeholder="010-1234-5678">
                        @error('phone')
                        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                        비밀번호 재설정 링크 전송
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-800 text-sm">로그인으로 돌아가기</a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
