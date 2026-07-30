{{--
    폼 필드 — 라벨 + 입력 + 에러 메시지. 입력은 기본 슬롯에 x-ui.input 을 넣는다.

    for  : 라벨 for 속성 (입력 id 와 맞출 것)
    name : 주면 $errors 에서 해당 필드 에러를 자동 노출
    hint : 입력 아래 보조 설명
--}}
@props(['label' => null, 'for' => null, 'name' => null, 'hint' => null])

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="mb-1.5 block text-base font-bold text-ink-900">
            {{ $label }}
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="mt-1.5 text-sm text-ink-400">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="mt-1.5 text-sm font-bold text-danger-600">{{ $message }}</p>
        @enderror
    @endif
</div>
