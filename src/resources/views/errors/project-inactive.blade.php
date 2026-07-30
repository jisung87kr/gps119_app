{{--
    비활성 행사 QR 진입 안내 — 종료·시작 전·비활성 행사의 QR 을 스캔한 경우.
    일반(상시) 구조요청으로 유도하는 것이 이 화면의 목적이다.
--}}
<x-layouts.app title="GPS119 - 종료된 행사" heading="행사 안내" :back="route('dashboard')">
    <div class="space-y-6">
        <div class="text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-ink-100 text-ink-400">
                <x-ui.icon name="clock" class="h-8 w-8" />
            </span>
            <h1 class="mt-4 break-keep text-[26px] font-extrabold leading-snug tracking-tight text-ink-950">
                지금은 입장할 수 없는 행사입니다
            </h1>
        </div>

        <x-ui.card>
            <p class="text-base font-bold text-ink-950">{{ $project->name }}</p>
            @if ($project->description)
                <p class="mt-1 break-keep text-sm leading-relaxed text-ink-500">{{ $project->description }}</p>
            @endif

            <div class="mt-4 rounded-xl bg-ink-50 px-4 py-3 text-sm leading-relaxed text-ink-600">
                @if ($project->status === 'completed' && $project->end_date->isPast())
                    {{ $project->end_date->format('Y년 m월 d일') }}에 종료된 행사입니다.
                @elseif ($project->status === 'pending')
                    {{ $project->start_date->format('Y년 m월 d일') }}에 시작하는 행사입니다.
                @else
                    현재 비활성 상태인 행사입니다.
                @endif
            </div>
        </x-ui.card>

        <div class="space-y-3">
            <x-ui.button :href="route('request.create')">일반 구조요청으로</x-ui.button>
            <x-ui.button :href="route('dashboard')" variant="secondary">홈으로</x-ui.button>
        </div>

        <x-ui.alert tone="warning">
            생명이 위급한 상황이면 이 화면을 닫고 119로 바로 전화해 주세요.
        </x-ui.alert>
    </div>
</x-layouts.app>
