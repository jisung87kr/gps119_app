{{--
    섹션 — 18px bold 제목 + 본문. 제목 옆 보조 수치는 meta 로 넣는다.
    (예: "처리 중인 요청 2건" → title="처리 중인 요청" meta="2건")
--}}
@props(['title' => null, 'meta' => null])

<section {{ $attributes }}>
    @if ($title)
        <h2 class="px-1 text-lg font-bold text-ink-950">
            {{ $title }}
            @if ($meta)
                <span class="font-normal text-ink-400">{{ $meta }}</span>
            @endif
        </h2>
    @endif

    <div @class(['mt-3' => (bool) $title])>
        {{ $slot }}
    </div>
</section>
