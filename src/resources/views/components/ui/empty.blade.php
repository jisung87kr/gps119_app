{{--
    빈 상태 — 목록에 표시할 항목이 없을 때. x-ui.list 안에 넣어 쓴다.
--}}
@props(['icon' => 'document'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2.5 px-5 py-12 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-300">
        <x-ui.icon :name="$icon" class="h-6 w-6" />
    </span>
    <p class="text-sm font-medium text-ink-400">{{ $slot }}</p>
</div>
