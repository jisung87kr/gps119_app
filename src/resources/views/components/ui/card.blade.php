{{--
    기본 카드 — rounded-2xl + ink-200 테두리 + 흰 배경.
    padded=false 로 주면 내부 패딩 없이 껍데기만 (목록/이미지 등).
--}}
@props(['padded' => true])

<div {{ $attributes->merge([
    'class' => 'rounded-2xl border border-ink-200 bg-white'.($padded ? ' p-5' : ''),
]) }}>
    {{ $slot }}
</div>
