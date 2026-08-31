{{--
    사용자 화면 셸 — src/tmp/design-system.html "Ink + Brand" v6 기준.
    기존 웹사이트형 셸(sticky 헤더 + nav + Alpine 드롭다운 + 푸터)을 모바일 앱형
    셸(최소 헤더 + 고정 하단 탭 바)로 재작성했다.

    프롭:
      title      <title> 태그 문구
      heading    주면 서브페이지 헤더(타이틀). 없으면 브랜드 로고 헤더
      back       heading 과 함께 주면 뒤로가기 버튼 노출 + 타이틀 중앙 정렬
      tab        하단 탭 강제 지정(home|request|profile). 기본은 라우트명 자동 판별
      bare       헤더·탭바·컨테이너 없이 슬롯만 렌더 (인증/전체화면 지도/에러 화면)
      padded     컨테이너 좌우 패딩(px-5) 적용 여부. 기본 true
      bodyClass  <body> 높이/스크롤 클래스. 기본 min-h-screen(=100vh, 문서가 늘어남).
                 전체화면 지도처럼 h-[100dvh] 로 자체 높이를 잡는 화면은
                 "h-[100dvh] overflow-hidden overscroll-none" 을 넘겨 body 스크롤을
                 잠근다 — 안 그러면 100vh - 100dvh 만큼 문서가 남아 스크롤되고,
                 모바일 브라우저 UI 밑으로 바텀시트 하단이 잘려 들어간다.

    슬롯:
      $actions   헤더 우측 액션 영역

    사업자 정보(업체명·사업자번호)는 푸터가 사라졌으므로 프로필 화면 하단에 있다.
--}}
@props([
    'title' => null,
    'heading' => null,
    'back' => null,
    'tab' => null,
    'bare' => false,
    'padded' => true,
    'bodyClass' => 'min-h-screen',
])

<!DOCTYPE html>
<html lang="ko" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    {{--
        viewport-fit=cover 가 없으면 env(safe-area-inset-*) 가 전부 0 이다.
        하단 탭바(x-ui.tab-bar)가 이미 env(safe-area-inset-bottom) 을 쓰고 있었는데
        **그동안 한 번도 동작하지 않았다** — 이 한 줄이 빠져 있었기 때문이다.
    --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- 웹 푸시 구독용 VAPID 공개키. «공개»키라 노출이 정상이다(개인키는 서버에만). --}}
    <meta name="vapid-public-key" content="{{ config('push.vapid.public_key') }}">
    <title>{{ $title ?? 'GPS119' }}</title>

    {{-- PWA: 설치형 매니페스트 + 테마색(brand-600) + iOS 홈화면 아이콘 --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0E6E7C">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="GPS119">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <link rel="icon" type="image/png" href="/icon-192.png">

    {{-- Pretendard — 한글 최적화 본문 폰트. app.css 의 --font-app 과 짝. --}}
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $bodyClass }} bg-ink-50 font-app text-ink-950 antialiased">
@if ($bare)
    {{ $slot }}
@else
    @php
        // 하단 탭 바는 로그인 사용자에게만 의미가 있다(모든 탭 목적지가 auth 라우트).
        $showTabs = auth()->check();
    @endphp

    <div @class(['mx-auto max-w-2xl', 'pb-28' => $showTabs, 'pb-10' => ! $showTabs])>
        @if ($heading)
            <x-ui.page-header :heading="$heading" :back="$back">
                @isset($actions)
                    <x-slot:actions>{{ $actions }}</x-slot:actions>
                @endisset
            </x-ui.page-header>
        @else
            <header class="flex items-center justify-between px-5 pb-2 pt-6"
                    style="padding-top: calc(1.5rem + env(safe-area-inset-top))">
                <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">
                        <x-ui.icon name="bolt-outline" class="h-4 w-4" />
                    </span>
                    <span class="text-base font-extrabold text-ink-950">GPS<span class="text-brand-600">119</span></span>
                </a>

                @isset($actions)
                    <div class="flex items-center gap-1">{{ $actions }}</div>
                @endisset
            </header>
        @endif

        <main @class(['pt-4', 'px-5' => $padded])>
            {{ $slot }}
        </main>
    </div>

    @if ($showTabs)
        <x-ui.tab-bar :active="$tab" />
    @endif
@endif

@php
    // 위치 공유를 셸 레벨로 올린다. 예전에는 활동/지령 화면 «안에서만» 송신해서,
    // 그 화면을 떠나는 순간 관제 지도의 좌표가 얼어붙었다.
    //
    // 그 두 화면은 자기 sharer 를 직접 소유하고 화면에 상태를 그린다. 셸이 하나를 더
    // 띄우면 같은 좌표를 두 번 보내게 되므로 여기서는 비켜선다. (마운트 순서에 기대는
    // 대신 라우트로 판정한다 — 순서 경합은 언젠가 반드시 진다.)
    $ownsLocationShare = request()->routeIs('events.active') || request()->routeIs('events.dispatch');
    $shellSharing = (! $bare && auth()->check() && ! $ownsLocationShare)
        ? auth()->user()->sharingParticipation()
        : null;
@endphp

@if ($shellSharing)
    <script type="module">
        {{-- 캐시 버스팅 — public/ 원본 서빙이라 해시가 안 붙는다. 활동 화면과 «같은 이유». --}}
        import { createLocationSharer } from '/js/components/locationShare.js?v={{ @filemtime(public_path('js/components/locationShare.js')) ?: time() }}';

        // 본인이 이미 켜 둔 공유만 이어받는다(resume 은 PATCH 를 보내지 않는다).
        // 끄는 것은 활동 화면의 토글에서만 — 셸은 동의를 만들지 않는다.
        const sharer = createLocationSharer({ projectId: {{ $shellSharing->project_id }} });
        sharer.resume();
        window.__shellLocationShare = sharer;
    </script>
@endif
</body>
</html>
