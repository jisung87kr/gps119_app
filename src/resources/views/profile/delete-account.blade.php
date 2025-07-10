<x-layouts.app>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-red-50 to-pink-50 p-8 rounded-lg shadow-sm">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">계정 삭제</h1>
            <p class="text-xl text-gray-600 mb-6">계정을 영구적으로 삭제합니다.</p>
        </div>

        <div class="max-w-2xl mx-auto mt-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">경고</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p>계정 삭제는 되돌릴 수 없습니다. 삭제하기 전에 다음 사항을 확인하세요:</p>
                                <ul class="list-disc list-inside mt-2 space-y-1">
                                    <li>모든 개인 데이터가 영구적으로 삭제됩니다</li>
                                    <li>구조 요청 기록이 모두 삭제됩니다</li>
                                    <li>삭제 후 같은 이메일로 재가입이 가능합니다</li>
                                    <li>이 작업은 취소할 수 없습니다</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">삭제될 정보</h3>
                    <div class="space-y-2 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <span>이름:</span>
                            <span class="font-medium">{{ auth()->user()->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>이메일:</span>
                            <span class="font-medium">{{ auth()->user()->email }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>가입일:</span>
                            <span class="font-medium">{{ auth()->user()->created_at->format('Y년 m월 d일') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>구조 요청 수:</span>
                            <span class="font-medium">{{ auth()->user()->requests()->count() }}건</span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-6">
                    @csrf
                    @method('DELETE')

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            계정 삭제를 확인하기 위해 현재 비밀번호를 입력하세요
                        </label>
                        <input type="password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 @error('password') border-red-500 @enderror"
                               id="password" name="password" required>
                        @error('password')
                        <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="confirm_delete" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded" required>
                        <label for="confirm_delete" class="ml-2 block text-sm text-gray-700">
                            위의 내용을 모두 확인했으며, 계정 삭제에 동의합니다.
                        </label>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" 
                                class="flex-1 bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition duration-200"
                                onclick="return confirm('정말로 계정을 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')">
                            계정 삭제
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