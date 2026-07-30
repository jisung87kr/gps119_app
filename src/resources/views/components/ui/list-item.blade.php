{{--
    목록 행 — src/tmp/design-system.html "목록 메뉴 행" / tmp/dashboard.html / tmp/profile.html 기준.

    href 를 주면 <a>(누름 피드백 active:bg-ink-50), 없으면 <div>.
    icon      : 좌측 아이콘 이름 (x-ui.icon)
    iconTone  : brand(bg-brand-50/text-brand-600) | neutral(bg-ink-100/text-ink-900)
    iconSize  : md(원형 44px — 데이터 행) | sm(사각 36px — 설정 메뉴 행)
    title     : 본문 1행 (truncate)
    meta      : 본문 2행 보조 텍스트
    chevron   : 우측 > 표시 (href 가 있으면 기본 true)

    본문을 직접 구성하려면 title/meta 대신 기본 슬롯을 쓴다.
    우측에 배지 등을 붙이려면 $trailing 슬롯을 쓴다.
--}}
@props([
    'href' => null,
    'icon' => null,
    'iconTone' => 'brand',
    'iconSize' => 'md',
    'title' => null,
    'meta' => null,
    'chevron' => null,
])

@php
    $tag = $href ? 'a' : 'div';
    $showChevron = $chevron ?? (bool) $href;

    $iconTones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'neutral' => 'bg-ink-100 text-ink-900',
        'danger' => 'bg-danger-50 text-danger-600',
    ];

    $iconBox = $iconSize === 'sm'
        ? 'h-9 w-9 rounded-lg'
        : 'h-11 w-11 rounded-full';

    $iconGlyph = $iconSize === 'sm' ? 'h-[17px] w-[17px]' : 'h-5 w-5';

    $rowClasses = 'flex items-center gap-3 px-5 py-4'.($href ? ' active:bg-ink-50' : '');
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => $rowClasses]) }}>

    @if ($icon)
        <span class="flex shrink-0 items-center justify-center {{ $iconBox }} {{ $iconTones[$iconTone] ?? $iconTones['brand'] }}">
            <x-ui.icon :name="$icon" :class="$iconGlyph" />
        </span>
    @endif

    <div class="min-w-0 flex-1">
        @if (filled(trim($slot)))
            {{ $slot }}
        @else
            <p class="truncate text-base font-bold text-ink-950">{{ $title }}</p>
            @if ($meta)
                <p class="mt-0.5 text-sm text-ink-400">{{ $meta }}</p>
            @endif
        @endif
    </div>

    @isset($trailing)
        <div class="flex shrink-0 items-center gap-2">{{ $trailing }}</div>
    @endisset

    @if ($showChevron)
        <x-ui.icon name="chevron-right" class="h-5 w-5 shrink-0 text-ink-300" />
    @endif
</{{ $tag }}>
