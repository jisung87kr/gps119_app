<x-layouts.app>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 w-full">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <div class="mx-auto h-24 w-24 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="h-12 w-12 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.728-.833-2.498 0L4.316 15.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    회원가입 오류
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    이미 등록된 사용자 정보입니다.
                </p>
                <p class="text-sm text-gray-600">
                    다른 소셜 계정으로 가입하셨거나, 이미 계정이 존재합니다.
                </p>
            </div>

            <div class="space-y-4">
                <a href="{{ route('login') }}"
                   class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    로그인 페이지로 이동
                </a>

                <a href="{{ route('request.create') }}"
                   class="group relative w-full flex justify-center py-3 px-4 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    홈으로 이동
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
