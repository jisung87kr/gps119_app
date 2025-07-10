<x-layouts.app>
    <div class="w-full max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-8 rounded-lg shadow-sm">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">비밀번호 변경</h1>
            <p class="text-xl text-gray-600 mb-6">안전한 비밀번호로 변경하세요.</p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            @if(session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">현재 비밀번호</label>
                    <input type="password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('current_password') border-red-500 @enderror"
                           id="current_password" name="current_password" required>
                    @error('current_password')
                    <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">새 비밀번호</label>
                    <input type="password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-500 @enderror"
                           id="password" name="password" required>
                    @error('password')
                    <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">새 비밀번호 확인</label>
                    <input type="password"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           id="password_confirmation" name="password_confirmation" required>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">비밀번호 안전 수칙</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>최소 8자 이상으로 설정하세요</li>
                                    <li>대문자, 소문자, 숫자, 특수문자를 포함하세요</li>
                                    <li>개인정보와 관련된 내용은 피하세요</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                        비밀번호 변경
                    </button>
                    <a href="{{ route('profile.show') }}" class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-200 text-center">
                        취소
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
