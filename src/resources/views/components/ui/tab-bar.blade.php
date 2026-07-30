{{--
    고정 하단 탭 바 — src/tmp/design-system.html "하단 탭 바" 기준.
    활성 탭은 brand-600, 비활성은 ink-400.

    활성 탭은 라우트명으로 자동 판별하고 active 프롭으로 강제 지정할 수 있다.
    참고: 행사(운영 인력) 화면은 탭에 없다 — 대시보드의 "내 행사" 섹션으로 진입한다.
--}}
@props(['active' => null])

@php
    $routeName = Route::currentRouteName() ?? '';

    $current = $active ?? match (true) {
        $routeName === 'dashboard' => 'home',
        str_starts_with($routeName, 'request.') => 'request',
        str_starts_with($routeName, 'profile.') => 'profile',
        default => null,
    };

    $tabs = [
        ['key' => 'home', 'label' => '홈', 'icon' => 'home', 'url' => route('dashboard')],
        ['key' => 'request', 'label' => '구조요청', 'icon' => 'ambulance', 'url' => route('request.create')],
        ['key' => 'profile', 'label' => '프로필', 'icon' => 'user', 'url' => route('profile.show')],
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-ink-200 bg-white"
     style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="mx-auto flex max-w-2xl">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['url'] }}"
               @if ($current === $tab['key']) aria-current="page" @endif
               @class([
                   'flex flex-1 flex-col items-center gap-1 py-3',
                   'text-brand-600' => $current === $tab['key'],
                   'text-ink-400' => $current !== $tab['key'],
               ])>
                <x-ui.icon :name="$tab['icon']" class="h-[22px] w-[22px]" />
                <span class="text-xs font-bold">{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
