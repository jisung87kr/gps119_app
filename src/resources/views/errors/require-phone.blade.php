<x-layouts.app>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 w-full">
        <div class="max-w-md w-full space-y-8 px-4">
            <div class="text-center">
                <!-- 전화기 아이콘 -->
                <div class="mx-auto h-24 w-24 bg-orange-100 rounded-full flex items-center justify-center mb-6">
                    <svg class="h-12 w-12 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                </div>

                <!-- 제목 -->
                <h2 class="text-3xl font-bold text-gray-900 mb-4">
                    연락처가 필요합니다
                </h2>

                <!-- 설명 -->
                <div class="space-y-3 mb-8">
                    <p class="text-lg text-gray-600">
                        GPS119 서비스 이용을 위해<br>
                        연락처 정보가 필요합니다.
                    </p>
                    <p class="text-sm text-gray-500">
                        구조 요청 시 신속한 연락을 위해<br>
                        전화번호를 등록해주세요.
                    </p>
                </div>
            </div>

            <!-- 액션 버튼들 -->
            <div class="space-y-4">
                <a href="{{ route('profile.edit') }}"
                   class="group relative w-full flex justify-center py-4 px-6 border border-transparent text-base font-medium rounded-xl text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transform transition-all duration-200 hover:scale-105 shadow-lg">
                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    연락처 등록하기
                </a>

                <button onclick="history.back()"
                        class="group relative w-full flex justify-center py-3 px-4 border border-gray-300 text-sm font-medium rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-all duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    이전으로 돌아가기
                </button>
            </div>

            <!-- 도움말 -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm">
                        <p class="text-blue-800 font-medium mb-1">연락처 정보 보호</p>
                        <p class="text-blue-700">
                            회원님의 연락처는 구조 요청 시에만 사용되며,
                            개인정보보호법에 따라 안전하게 보호됩니다.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
