{{--
    사용자 화면 아이콘 세트 — src/tmp/design-system.html "아이콘 세트" 섹션 기준.
    색은 currentColor 를 따르므로 부모에 text-* 를 건다.
    (기본: ink-900 / 위치 관련: brand-600 / 긴급 관련: danger-600)

    사용: <x-ui.icon name="pin" class="h-5 w-5" />
    class 를 주면 기본 크기(h-5 w-5)를 "대체"한다. merge 로 합치면
    h-5 w-5 h-3 w-3 처럼 충돌 유틸이 같이 출력돼 CSS 순서에 좌우되므로 쓰지 않는다.
--}}
@props(['name', 'strokeWidth' => 2])

@php
    // 선(stroke) 아이콘 — 나머지는 면(fill) 아이콘.
    $strokeIcons = ['bolt-outline', 'chevron-left', 'chevron-right', 'chevron-down', 'clock', 'plus'];

    $paths = [
        // 브랜드 마크 (로고) — 선/면 두 버전
        'bolt-outline' => 'M13 2 3 14h7l-1 8 10-12h-7l1-8Z',
        'bolt' => 'M13 2 3 14h7l-1 8 10-12h-7l1-8Z',

        // 내비게이션
        'home' => 'M12 3 2 12h3v8h6v-6h2v6h6v-8h3L12 3Z',
        'ambulance' => 'M3 6a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v3h2.4a1 1 0 0 1 .9.55L19 13v3h-2a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H3a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm10 2h-2v2h-2v2h2v2h2v-2h2v-2h-2V8Z',
        'user' => 'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-8 2.24-8 5v1a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-1c0-2.76-3.58-5-8-5Z',
        'bell' => 'M12 2a6 6 0 0 0-6 6v3.09c0 .85-.32 1.68-.9 2.31L3.35 15.3A1 1 0 0 0 4 17h16a1 1 0 0 0 .65-1.7l-1.75-1.9a3.5 3.5 0 0 1-.9-2.31V8a6 6 0 0 0-6-6Zm0 20a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3Z',

        // 위치
        'pin' => 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z',

        // 상황 유형 (RequestType 과 1:1)
        'accident' => 'M12 2 1 21h22L12 2Zm1 14h-2v-2h2Zm0-4h-2V8h2Z',
        'breakdown' => 'M21.7 4.3a1 1 0 0 0-1.4 0l-2.6 2.6-1.6-1.6 2.6-2.6a1 1 0 0 0 0-1.4 6 6 0 0 0-7.8 7.8L2.3 17.7a1.5 1.5 0 0 0 2.1 2.1L12 12.3l-.4.4a6 6 0 0 0 7.8-7.8Z',
        'chat' => 'M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H10l-4.2 4.2A.8.8 0 0 1 4.4 21V17H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z',
        'phone' => 'M6.6 10.8a15.9 15.9 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24 11 11 0 0 0 3.5.56 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.6 21 3 13.4 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11 11 0 0 0 .56 3.5 1 1 0 0 1-.24 1L6.6 10.8Z',

        // 설정·계정
        'key' => 'M14 2a6 6 0 0 0-5.7 8L2 16.3V20a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-1h1a1 1 0 0 0 1-1v-1h1a1 1 0 0 0 .7-.3l1.7-1.7A6 6 0 1 0 14 2Zm2 6.5a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z',
        'document' => 'M6 2a1 1 0 0 0-1 1v18a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8l-6-6H6Zm7 1.5L18.5 9H13V3.5ZM8 13h8v1.5H8V13Zm0 3.5h8V18H8v-1.5Z',
        'logout' => 'M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5v-2H5V5h5V3Zm6.6 3.6L15.2 8l3 3H8v2h10.2l-3 3 1.4 1.4L22 12l-5.4-5.4Z',
        // 관리자 진입점 — 방패(운영 권한). 관제 셸의 brand 마크와 겹치지 않게 별도 형태.
        'shield' => 'M12 2 4 5v6c0 4.7 3.4 9.1 8 10.4 4.6-1.3 8-5.7 8-10.4V5l-8-3Zm-1 13-3.2-3.2 1.4-1.4L11 12.2l4.8-4.8 1.4 1.4L11 15Z',

        // 상태
        'check-circle' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.6 14.6-4-4L7.8 11l2.6 2.6 5.8-5.8L17.6 9.2Z',
        'alert-circle' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-2h2v2Zm0-4h-2V7h2v6Z',
        'x-circle' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm4.2 12.8-1.4 1.4L12 13.4l-2.8 2.8-1.4-1.4L10.6 12 7.8 9.2l1.4-1.4L12 10.6l2.8-2.8 1.4 1.4L13.4 12l2.8 2.8Z',

        // 선 아이콘
        'clock' => 'M12 7v5l3 2',
        'chevron-right' => 'm9 6 6 6-6 6',
        'chevron-left' => 'm15 18-6-6 6-6',
        'chevron-down' => 'm6 9 6 6 6-6',
        'plus' => 'M12 5v14M5 12h14',
    ];

    $path = $paths[$name] ?? null;
    $isStroke = in_array($name, $strokeIcons, true);

    $sizeClass = $attributes->get('class') ?: 'h-5 w-5';
    $rest = $attributes->except('class');
@endphp

@if ($path)
    @if ($isStroke)
        <svg class="{{ $sizeClass }}" {{ $rest }} viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            {{-- clock 은 원 + 시계바늘 2요소 --}}
            @if ($name === 'clock')
                <circle cx="12" cy="12" r="9" />
            @endif
            <path d="{{ $path }}" />
        </svg>
    @else
        <svg class="{{ $sizeClass }}" {{ $rest }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="{{ $path }}" />
        </svg>
    @endif
@endif
