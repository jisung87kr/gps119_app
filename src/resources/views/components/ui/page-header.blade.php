{{--
    서브페이지 헤더 — src/tmp/dispatch.html(뒤로가기 + 중앙 타이틀) /
    tmp/profile.html(좌측 타이틀) 두 변형을 합친 것.

    back 을 주면 뒤로가기 + 중앙 정렬 타이틀, 없으면 좌측 정렬 타이틀.
    우측 액션은 $actions 슬롯으로 넣는다.
--}}
@props(['heading' => null, 'back' => null])

{{-- 고정 높이(h-16)에 패딩을 더하면 border-box 라 내용이 눌린다 → min-h-16 --}}
<header style="padding-top: env(safe-area-inset-top)"
        {{ $attributes->merge(['class' => 'sticky top-0 z-30 flex min-h-16 items-center gap-1 border-b border-ink-200 bg-white px-4']) }}>
    @if ($back)
        <a href="{{ $back }}" aria-label="뒤로"
           class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-ink-900 active:bg-ink-100">
            <x-ui.icon name="chevron-left" class="h-[22px] w-[22px]" :stroke-width="2.2" />
        </a>
    @endif

    <span @class([
        'min-w-0 flex-1 truncate text-lg font-extrabold text-ink-950',
        'text-center' => (bool) $back,
        'px-1' => ! $back,
    ])>{{ $heading }}</span>

    @if (isset($actions))
        <div class="flex shrink-0 items-center gap-1">{{ $actions }}</div>
    @elseif ($back)
        {{-- 타이틀 중앙 정렬을 유지하기 위한 좌측 버튼과 동일 폭 스페이서 --}}
        <span class="h-11 w-11 shrink-0" aria-hidden="true"></span>
    @endif
</header>
