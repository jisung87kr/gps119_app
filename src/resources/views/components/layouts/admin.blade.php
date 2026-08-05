<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-PJV2CH8GCP"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-PJV2CH8GCP');
    </script>
    <meta charset="utf-8">
    {{--
        viewport-fit=cover — 이게 없으면 env(safe-area-inset-*) 가 «전부 0» 이 된다.
        브라우저에서는 상태바가 페이지 영역이 아니라 티가 안 났지만, 웹뷰·홈화면 PWA 는
        화면 최상단부터 시작해서 헤더가 상태바/다이나믹 아일랜드 밑으로 들어간다.
        cover 를 켜면 edge-to-edge 가 되므로 «상단 크롬마다» env() 패딩이 짝으로 필요하다.
    --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'GPS119 관리자' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-900">
@php
    $pendingCount = \App\Models\Request::where('status', 'pending')->count();
    $navItems = [
        ['label' => '대시보드', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'group' => '운영',
         'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['label' => '실시간 관제', 'route' => 'admin.control', 'active' => 'admin.control', 'group' => '운영', 'badge' => $pendingCount,
         'icon' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 12c0 1.2-.3 2.3-.9 3.3M4.5 12c0-1.2.3-2.3.9-3.3'],
        ['label' => '통계', 'route' => 'admin.statistics', 'active' => 'admin.statistics', 'group' => '운영',
         'icon' => 'M3 3v18h18M7 15l3-4 3 3 4-6'],
        ['label' => '행사', 'route' => 'admin.projects.index', 'active' => 'admin.projects*', 'group' => '관리',
         'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['label' => '회원관리', 'route' => 'admin.members', 'active' => 'admin.members*', 'group' => '관리',
         'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    ];
@endphp

<div x-data="{ nav: false, user: false }" class="min-h-screen">

    <!-- mobile backdrop -->
    <div x-show="nav" x-cloak x-transition.opacity @click="nav = false"
         class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden"></div>

    <!-- ===== SIDEBAR ===== -->
    <aside x-cloak
           class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-white border-r border-slate-200 transition-transform duration-200 lg:translate-x-0"
           :class="nav ? 'translate-x-0' : '-translate-x-full'"
           style="padding-top: env(safe-area-inset-top); padding-bottom: env(safe-area-inset-bottom)">
        <!-- brand -->
        <div class="flex items-center gap-2.5 h-16 px-5 border-b border-slate-100">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-blue-600 text-white">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.2-7-11a7 7 0 0 1 14 0c0 5.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.4"/></svg>
                </span>
                <span class="text-lg font-bold tracking-tight">GPS119</span>
                <span class="px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 bg-slate-100 rounded">관제</span>
            </a>
            <button @click="nav = false" class="ml-auto p-1 text-slate-400 lg:hidden" aria-label="닫기">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- nav -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
            @php $lastGroup = null; @endphp
            @foreach($navItems as $item)
                @if($item['group'] !== $lastGroup)
                    <p class="px-3 pt-4 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $item['group'] }}</p>
                    @php $lastGroup = $item['group']; @endphp
                @endif
                @php
                    $isActive = isset($item['active']) ? request()->routeIs($item['active'])
                              : (isset($item['active_uri']) ? request()->is($item['active_uri']) : false);
                    $href = $item['url'] ?? route($item['route']);
                @endphp
                <a href="{{ $href }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $isActive ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <svg class="w-5 h-5 flex-none {{ $isActive ? 'text-blue-600' : 'text-slate-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $item['icon'] }}"/>
                    </svg>
                    <span>{{ $item['label'] }}</span>
                    @if(!empty($item['badge']) && $item['badge'] > 0)
                        <span class="ml-auto grid place-items-center min-w-[20px] h-5 px-1.5 text-[11px] font-bold text-white bg-red-500 rounded-full tabular-nums">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <!-- user -->
        <div class="relative border-t border-slate-100 p-3">
            <div x-show="user" x-cloak @click.away="user = false" x-transition
                 class="absolute left-3 right-3 bottom-[68px] bg-white border border-slate-200 rounded-lg shadow-lg overflow-hidden">
                <a href="{{ route('dashboard') }}" class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">사용자 페이지</a>
                <a href="{{ route('profile.show') }}" class="block px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">프로필</a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button type="submit" class="block w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">로그아웃</button>
                </form>
            </div>
            <button @click="user = !user" class="flex items-center gap-3 w-full px-2 py-2 rounded-lg hover:bg-slate-50 transition-colors">
                <span class="grid place-items-center w-9 h-9 rounded-full bg-blue-600 text-white text-sm font-semibold">{{ mb_substr(auth()->user()->name ?? 'A', 0, 1) }}</span>
                <span class="min-w-0 text-left">
                    <span class="block text-sm font-semibold text-slate-800 truncate">{{ auth()->user()->name }}</span>
                    <span class="block text-xs text-slate-400">시스템 관리자</span>
                </span>
                <svg class="ml-auto w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </button>
        </div>
    </aside>

    <!-- ===== MAIN ===== -->
    <div class="lg:pl-64">
        <!-- mobile top bar -->
        <div class="lg:hidden sticky top-0 z-30 flex items-center gap-3 min-h-14 px-4 bg-white border-b border-slate-200"
             style="padding-top: env(safe-area-inset-top)">
            <button @click="nav = true" class="p-1 -ml-1 text-slate-600" aria-label="메뉴 열기">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="font-bold tracking-tight">GPS119 <span class="text-blue-600">관제</span></span>
        </div>
        <main>
            {{ $slot }}
        </main>
    </div>
</div>

<script src="//unpkg.com/alpinejs" defer></script>
</body>
</html>
