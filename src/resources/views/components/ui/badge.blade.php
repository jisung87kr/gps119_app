{{--
    상태 배지 — src/tmp/design-system.html "상태 표시" 기준.
    흰 배경 + ink-200 테두리를 기본으로 하고 색은 아이콘·텍스트에만 쓴다.
    긴급(filled)만 배경까지 채운다.

    tone  : neutral | warning | success | danger | brand
    size  : md(14px, 기본) | sm(12px)
    filled: 배경 채우기(tone-50) + 테두리 제거 — 긴급 전용
    icon  : x-ui.icon 이름
--}}
@props([
    'tone' => 'neutral',
    'size' => 'md',
    'filled' => false,
    'icon' => null,
])

@php
    $textTones = [
        'neutral' => 'text-ink-900',
        'warning' => 'text-warning-600',
        'success' => 'text-success-600',
        'danger' => 'text-danger-600',
        'brand' => 'text-brand-600',
    ];

    $fillTones = [
        'neutral' => 'bg-ink-100',
        'warning' => 'bg-warning-50',
        'success' => 'bg-success-50',
        'danger' => 'bg-danger-50',
        'brand' => 'bg-brand-50',
    ];

    $sizeClasses = $size === 'sm'
        ? 'gap-1 px-2.5 py-1 text-xs'
        : 'gap-1.5 px-3 py-1.5 text-sm';

    $iconSize = $size === 'sm' ? 'h-3 w-3' : 'h-[15px] w-[15px]';

    $surface = $filled
        ? ($fillTones[$tone] ?? $fillTones['neutral'])
        : 'border border-ink-200';

    $classes = trim(
        'inline-flex shrink-0 items-center rounded-full font-bold '
        .$sizeClasses.' '.$surface.' '.($textTones[$tone] ?? $textTones['neutral'])
    );
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" :class="$iconSize" />
    @endif
    {{ $slot }}
</span>
