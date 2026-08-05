{{--
    빠른 실행 그리드 셀 — src/tmp/design-system.html "빠른 실행 그리드 (2x2)" 기준.
    신고 화면의 상황 버튼(사고발생/차량고장/기타문의/긴급전화)에 쓴다.

    2단계 구분:
      tone="danger-solid" 긴급전화 — 즉시 통화 (danger-600 채움)
      tone="danger-soft"  사고발생 — 접수 요청 (danger-50 배경 + danger-200 테두리)
      tone="neutral"      차량고장·기타문의 — 색 없이 중립
--}}
{{--
    vueClick: Vue 클릭 핸들러 식. Blade 컴포넌트 태그의 속성값에 {{ }} 를 쓰면
    태그 파서가 깨지므로(속성 안에 raw PHP 가 남는다) 속성 대신 프롭으로 받는다.
    :vue-click="..." 처럼 PHP 식으로 넘길 것.
--}}
@props([
    'tone' => 'neutral',
    'icon' => null,
    'href' => null,
    'type' => 'button',
    'vueClick' => null,
])

@php
    $tones = [
        'danger-solid' => 'bg-danger-600 text-white shadow-sm active:bg-danger-700',
        'danger-soft' => 'border-2 border-danger-200 bg-danger-50 text-danger-600 active:bg-danger-100',
        'neutral' => 'border-2 border-ink-200 bg-white text-ink-900 active:bg-ink-50',
    ];

    $classes = 'flex flex-col items-center justify-center gap-1 rounded-2xl py-3.5 transition-colors '
        .($tones[$tone] ?? $tones['neutral']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-[22px] w-[22px]" />
        @endif
        <span class="text-sm font-extrabold">{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}"
            @if ($vueClick) v-on:click="{{ $vueClick }}" @endif
            {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-[22px] w-[22px]" />
        @endif
        <span class="text-sm font-extrabold">{{ $slot }}</span>
    </button>
@endif
