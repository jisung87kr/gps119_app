<!DOCTYPE html>
<html lang="ko" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'GPS119' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp,container-queries"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 font-sans selection:bg-blue-100 selection:text-blue-700">
    <div class="flex flex-col min-h-screen">
        <header class="sticky top-0 z-[100] bg-white/80 backdrop-blur-md border-b border-slate-200/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex justify-between items-center h-14">
                    <div class="flex items-center gap-8">
                        <a href="{{ url('/') }}" class="flex items-center gap-2 group">
                            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center shadow-lg shadow-blue-200 group-hover:scale-105 transition-transform">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                </svg>
                            </div>
                            <span class="text-lg font-black tracking-tighter text-slate-900">GPS<span class="text-blue-600">119</span></span>
                        </a>

                        <nav class="hidden md:flex items-center gap-1">
                            @auth
                                <a href="{{ route('request.create') }}" class="px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors">구조 요청</a>
                                <a href="{{ route('dashboard') }}" class="px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors">대시보드</a>
                            @endauth
                        </nav>
                    </div>

                    <div class="flex items-center gap-3">
                        @guest
                            <div class="hidden md:flex items-center gap-2">
                                <a href="{{ route('login') }}" class="px-4 py-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors">로그인</a>
                                <a href="{{ route('register') }}" class="px-4 py-1.5 text-sm font-bold text-white bg-slate-900 rounded-lg hover:bg-slate-800 transition-all shadow-sm">회원가입</a>
                            </div>
                        @else
                            <div class="hidden md:flex items-center gap-3 border-l border-slate-200 ml-2 pl-5" x-data="{ open: false }">
                                <button @click="open = !open" class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-slate-100 transition-colors">
                                    <div class="w-7 h-7 bg-slate-200 rounded-full flex items-center justify-center text-[10px] font-bold text-slate-600 border border-slate-300">
                                        {{ mb_substr(Auth::user()->name, 0, 1) }}
                                    </div>
                                    <span class="text-sm font-semibold text-slate-700">{{ Auth::user()->name }}</span>
                                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="open" @click.away="open = false" 
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="absolute right-0 mt-36 w-48 bg-white rounded-xl shadow-xl border border-slate-200/60 py-1.5 z-50">
                                    <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 font-medium transition-colors">프로필 설정</a>
                                    <div class="my-1 border-t border-slate-100"></div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-semibold transition-colors">로그아웃</button>
                                    </form>
                                </div>
                            </div>
                        @endguest

                        <!-- Mobile Menu Button -->
                        <div class="md:hidden" x-data="{ open: false }">
                            <button @click="open = !open" class="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                            <div x-show="open" @click.away="open = false" 
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-2"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="absolute right-4 left-4 mt-2 bg-white rounded-2xl shadow-2xl border border-slate-200/60 p-2 z-[110]">
                                <div class="space-y-1">
                                    @auth
                                        <a href="{{ route('request.create') }}" class="block px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded-xl">구조 요청</a>
                                        <a href="{{ route('dashboard') }}" class="block px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded-xl">대시보드</a>
                                        <div class="border-t border-slate-100 my-2"></div>
                                        <a href="{{ route('profile.show') }}" class="block px-4 py-3 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-xl">내 프로필</a>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left px-4 py-3 text-sm font-bold text-red-600 hover:bg-red-50 rounded-xl">로그아웃</button>
                                        </form>
                                    @else
                                        <a href="{{ route('login') }}" class="block px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded-xl">로그인</a>
                                        <a href="{{ route('register') }}" class="block px-4 py-3 text-sm font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-xl">회원가입</a>
                                    @endauth
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 flex flex-col">
            {{ $slot }}
        </main>

        <footer class="bg-white border-t border-slate-200/60 py-10 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 text-center md:text-left">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div>
                        <div class="flex items-center gap-2 mb-4 justify-center md:justify-start">
                            <div class="w-6 h-6 bg-slate-200 rounded flex items-center justify-center">
                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                </svg>
                            </div>
                            <span class="text-sm font-black tracking-tighter text-slate-400">GPS119</span>
                        </div>
                        <p class="text-xs text-slate-400 font-medium">업체명: 세이브미 | 사업자번호: 852-08-02915</p>
                        <p class="text-xs text-slate-400 mt-1 font-medium">&copy; {{ date('Y') }} GPS119. All rights reserved.</p>
                    </div>
                    <div class="text-xs text-slate-400 font-medium md:text-right">
                        Made with ❤️ by <a href="" class="text-blue-500 hover:underline">indigo404.com</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
