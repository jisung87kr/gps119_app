{{--
    버튼 — src/tmp/design-system.html "버튼" 섹션 기준.
    주요 액션은 brand-600 로 통일하고 danger 는 긴급 전용으로만 쓴다.

    variant : primary | danger | secondary | ghost
    size    : xl(전폭·18px) | lg(전폭·16px, 기본) | sm(인라인 pill)
    href 를 주면 <a>, 없으면 <button type="{{ $type }}">.

    vueClick: Vue 클릭 핸들러 식. Blade 컴포넌트 태그의 속성값에 {{ }} 를 쓰면
    태그 파서가 깨지므로(속성 안에 raw PHP 가 남는다) 속성 대신 프롭으로 받는다.
--}}
@props([
    'variant' => 'primary',
    'size' => 'lg',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'vueClick' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 text-center transition-colors';

    $sizes = [
        'xl' => 'w-full rounded-2xl py-4 text-lg font-extrabold',
        'lg' => 'w-full rounded-2xl py-4 text-base font-bold',
        'sm' => 'shrink-0 rounded-xl px-3.5 py-2.5 text-sm font-bold',
    ];

    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-sm active:bg-brand-700',
        'danger' => 'bg-danger-600 text-white shadow-sm active:bg-danger-700',
        'secondary' => 'border-2 border-ink-200 bg-white text-ink-900 active:bg-ink-50',
        'ghost' => 'text-ink-400 underline underline-offset-2',
    ];

    // ghost 는 pill/전폭 패딩이 어울리지 않아 사이즈 클래스를 얇게 덮어쓴다.
    $sizeClasses = $variant === 'ghost'
        ? ($size === 'sm' ? 'text-sm font-medium' : 'w-full py-2 text-sm font-medium')
        : $sizes[$size] ?? $sizes['lg'];

    $variantClasses = $disabled
        ? 'cursor-not-allowed bg-ink-100 text-ink-400'
        : $variants[$variant] ?? $variants['primary'];

    $classes = trim("{$base} {$sizeClasses} {$variantClasses}");
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled)
            @if ($vueClick) v-on:click="{{ $vueClick }}" @endif
            {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
