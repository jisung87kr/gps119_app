{{--
    텍스트 입력 — src/tmp/login.html / tmp/dispatch.html 입력 스타일 기준.
    포커스 시 테두리만 brand-600 으로 바뀌고 링은 쓰지 않는다.
--}}
@props(['error' => false])

<input {{ $attributes->merge([
    'class' => 'w-full rounded-2xl border-2 px-4 py-3.5 text-base text-ink-950 placeholder:text-ink-400 focus:outline-none '
        .($error ? 'border-danger-200 focus:border-danger-600' : 'border-ink-200 focus:border-brand-600'),
]) }}>
