{{-- 행사 QR 신고 진입점 — 마크업은 request/_map-screen 공용. --}}
<x-layouts.app :title="'GPS119 - '.$project->name" bare
              body-class="h-[100dvh] overflow-hidden overscroll-none">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    @include('request._map-screen', ['project' => $project])

    <script type="module">
        import createRequestMapApp from '/js/components/RequestMapApp.js';

        const { createApp } = Vue;

        createApp(createRequestMapApp({
            projectId: {{ $project->id }},
            contactPhone: @json(auth()->user()->phone),
            emergencyTel: @json(data_get($project->settings, 'emergency_tel', '010-4794-0119')),
        })).mount('#app');
    </script>
</x-layouts.app>
