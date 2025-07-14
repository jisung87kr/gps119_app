<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp,container-queries"></script>

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="flex flex-col min-h-screen">
        <nav class="bg-blue-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
                        <a href="{{ url('/') }}" class="text-white text-xl font-bold">GPS119</a>

                        <div class="hidden md:block ml-10">
                            <div class="flex items-baseline space-x-4">
                                @auth
                                    <a href="{{ route('request.create') }}" class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">구조 요청</a>
                                    <a href="{{ route('dashboard') }}" class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">대시보드</a>
                                @endauth
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:block">
                        <div class="flex items-center space-x-4">
                            @guest
                                <a href="{{ route('login') }}" class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium">로그인</a>
                                <a href="{{ route('register') }}" class="bg-blue-500 hover:bg-blue-400 text-white px-4 py-2 rounded-md text-sm font-medium">회원가입</a>
                            @else
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="text-white hover:bg-blue-700 px-3 py-2 rounded-md text-sm font-medium flex items-center">
                                        {{ Auth::user()->name }}
                                        <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>

                                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                                        <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">프로필</a>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">로그아웃</button>
                                        </form>
                                    </div>
                                </div>
                            @endguest
                        </div>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="md:hidden" x-data="{ open: false }" >
                        <button @click="open = !open" class="text-white hover:bg-blue-700 p-2 rounded-md">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            @auth
                                <a href="{{ route('request.create') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">구조 요청</a>
                                <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">대시보드</a>
                            @endauth
                            @guest
                                <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">로그인</a>
                                <a href="{{ route('register') }}" class="block px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">회원가입</a>
                            @else
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">로그아웃</button>
                                </form>
                            @endguest
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex flex-1">
            {{ $slot }}
        </main>

        <footer class="bg-gray-800 text-white py-4">
            <div class="max-w-7xl mx-auto px-4 text-center">
                <p>업체명: 세이브미</p>
                <p>사업자번호: 852-08-02915</p>
                <p>&copy; {{ date('Y') }} GPS119. All rights reserved.</p>
                <p>Made with ❤️ by <a href="" class="text-blue-400 hover:underline">indigo404.com</a></p>
            </div>
        </footer>
    </div>
    <!-- Alpine.js for dropdowns -->
{{--    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>--}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
