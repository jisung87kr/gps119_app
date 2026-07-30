{{--
    통계(KPI) 카드 — src/tmp/dashboard.html "KPI grid" 기준.
    값은 숫자만 넘기고 단위는 unit 으로 붙인다.
--}}
@props(['label', 'value', 'unit' => '건'])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-ink-200 bg-white p-5']) }}>
    <p class="text-sm font-bold text-ink-500">{{ $label }}</p>
    <p class="mt-1 text-3xl font-extrabold text-ink-950">{{ $value }}{{ $unit }}</p>
</div>
