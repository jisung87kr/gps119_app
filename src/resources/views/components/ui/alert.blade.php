{{--
    인라인 알림 — 플래시 메시지·검증 에러 요약용.
    배지와 달리 블록 요소이고 배경을 채운다.

    tone : success | danger | warning | brand
--}}
@props(['tone' => 'brand', 'icon' => null])

@php
    $tones = [
        'success' => 'bg-success-50 text-success-600',
        'danger' => 'bg-danger-50 text-danger-600',
        'warning' => 'bg-warning-50 text-warning-600',
        'brand' => 'bg-brand-50 text-brand-600',
    ];

    $defaultIcons = [
        'success' => 'check-circle',
        'danger' => 'alert-circle',
        'warning' => 'alert-circle',
        'brand' => 'alert-circle',
    ];

    $glyph = $icon ?? $defaultIcons[$tone] ?? null;
@endphp

<div {{ $attributes->merge([
    'class' => 'flex items-start gap-2.5 rounded-2xl px-4 py-3.5 text-sm font-bold '.($tones[$tone] ?? $tones['brand']),
]) }}>
    @if ($glyph)
        <x-ui.icon :name="$glyph" class="mt-px h-[18px] w-[18px] shrink-0" />
    @endif
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
