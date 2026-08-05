<!DOCTYPE html>
<html lang="ko" class="h-full">
<head>
    <meta charset="UTF-8">
    {{--
        lg 이상은 3단 관제, 미만은 지도 전체화면 + 바텀시트(ControlApp 의 isMobile 분기).
        viewport-fit=cover 는 시트 하단 safe-area 를 위해 필요하다.
        (예전 width=1280 고정은 미디어쿼리를 원천 차단해 모바일 분기가 불가능했다)
    --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>실시간 관제 - GPS119</title>

    {{-- 관제 전용 Vite 번들 SPA (FE-2.1) — app.css로 컴파일된 Tailwind 유틸 로드 --}}
    @vite(['resources/css/app.css', 'resources/js/control/main.js'])

    <style>html,body{height:100%;margin:0;overflow:hidden;overscroll-behavior:none;}</style>
</head>
<body class="h-full bg-gray-100">
    {{-- Vue 가 마운트할 루트. 활성 행사 목록을 data-projects 로 전달(1개면 자동선택) --}}
    <div id="control-app"
         data-projects='@json($projects)'
         data-selected="{{ $selectedId ?? '' }}"
         data-back-url="{{ $backUrl ?? '' }}"></div>
</body>
</html>
