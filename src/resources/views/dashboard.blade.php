<x-layouts.app>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-md p-6 my-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">GPS119에 오신 것을 환영합니다</h1>
            <p class="text-xl text-gray-600 mb-6">긴급 상황에서 GPS 위치 정보를 활용하여 신속하게 구조 요청을 할 수 있는 서비스입니다.</p>
            <hr class="my-6 border-gray-200">
            <p class="text-gray-700 mb-8">사용자는 자신의 위치를 기반으로 구조 요청을 보내고, 구조대는 해당 요청을 실시간으로 확인할 수 있습니다.</p>

            @guest
                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
                    <a href="{{ route('login') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 text-center">로그인</a>
                    <a href="{{ route('register') }}" class="border border-blue-600 text-blue-600 hover:bg-blue-50 font-semibold py-3 px-6 rounded-lg transition duration-200 text-center">회원가입</a>
                </div>
            @else
                <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
                    <a href="{{ route('request.create') }}" class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 text-center">긴급 구조 요청</a>
                    <a href="{{ route('profile.show') }}" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 text-center">프로필</a>
                </div>
            @endguest
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-12">
            <div class="bg-white rounded-lg shadow-md p-6 h-full">
                <div class="flex items-center justify-center w-12 h-12 bg-red-100 rounded-lg mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">긴급 구조 요청</h3>
                <p class="text-gray-600">GPS 위치 정보와 함께 신속한 구조 요청을 보낼 수 있습니다.</p>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 h-full">
                <div class="flex items-center justify-center w-12 h-12 bg-blue-100 rounded-lg mb-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">실시간 모니터링</h3>
                <p class="text-gray-600">구조대는 실시간으로 구조 요청을 확인하고 대응할 수 있습니다.</p>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 h-full">
                <div class="flex items-center justify-center w-12 h-12 bg-green-100 rounded-lg mb-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">안전한 시스템</h3>
                <p class="text-gray-600">사용자 권한 관리와 보안 시스템으로 안전하게 이용할 수 있습니다.</p>
            </div>
        </div>
    </div>
</x-layouts.app>
