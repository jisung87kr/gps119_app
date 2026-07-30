{{--
    목록 컨테이너 — x-ui.list-item 을 감싼다. 행 사이는 ink-100 구분선.
--}}
<div {{ $attributes->merge([
    'class' => 'divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-200 bg-white',
]) }}>
    {{ $slot }}
</div>
