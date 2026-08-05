{{-- 상시 신고 (비행사) — 마크업은 request/_map-screen 공용. --}}
<x-layouts.app title="GPS119 - 구조 요청" bare
              body-class="h-[100dvh] overflow-hidden overscroll-none">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    @include('request._map-screen', ['project' => null])

    <script type="module">
        import createRequestMapApp from '/js/components/RequestMapApp.js';

        const { createApp } = Vue;

        createApp(createRequestMapApp({
            contactPhone: @json(auth()->user()->phone),
        })).mount('#app');
    </script>
</x-layouts.app>
