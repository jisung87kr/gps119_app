<!DOCTYPE html>
<html lang="ko" class="h-full">
<head>
    <meta charset="UTF-8">
    {{-- PC 대화면 전용 관제: 모바일 축소 분기 없음(최소 1280px) --}}
    <meta name="viewport" content="width=1280">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>실시간 관제 - GPS119</title>

    {{-- 기존 페이지와 동일한 Tailwind CDN(유틸 클래스 보장) --}}
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp,container-queries"></script>

    {{-- 관제 전용 Vite 번들 SPA (FE-2.1) --}}
    @vite('resources/js/control/main.js')

    <style>html,body{height:100%;margin:0;overflow:hidden;}</style>
</head>
<body class="h-full bg-gray-100">
    {{-- Vue 가 마운트할 루트. 활성 행사 목록을 data-projects 로 전달(1개면 자동선택) --}}
    <div id="control-app" data-projects='@json($projects)'></div>
</body>
</html>
