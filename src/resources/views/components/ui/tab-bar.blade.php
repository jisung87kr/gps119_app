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
        $routeName === 'dashboard', str_starts_with($routeName, 'dispatches.') => 'home',
        $routeName === 'control', str_starts_with($routeName, 'events.') => 'work',
        str_starts_with($routeName, 'request.') => 'work',
        str_starts_with($routeName, 'profile.') => 'profile',
        default => null,
    };

    // 🔑 탭은 «세 개 고정»이고 가운데 자리만 역할에 따라 바뀐다 (현장 피드백 #6).
    //    개수를 바꾸면 같은 사람이 행사마다 다른 탭 수를 보게 되어 근육 기억이 깨진다.
    //
    //    판정은 «지금 활성 행사에서의 역할»이다 — 행사가 끝나면 구급대원도 평범한
    //    사용자로 돌아간다. 시스템 롤은 일반/관리자 둘뿐이라 여기서 볼 것이 없다.
    $user = auth()->user();
    $eventRole = $user?->activeEventRole();
    $isDispatchSide = (bool) $user?->usesDispatchHome();

    // 홈은 「그 사람의 일」이 있는 곳이다. 구급 쪽은 신고 목록이 아니라 출동 현황이다.
    $homeUrl = $isDispatchSide ? route('dispatches.index') : route('dashboard');

    if ($eventRole === \App\Enums\EventRole::CONTROLLER) {
        $middle = ['key' => 'work', 'label' => '관제', 'icon' => 'pin', 'url' => route('control')];
    } elseif ($isDispatchSide) {
        // 지령을 «받는» 화면은 행사 스코프다. 행사가 정확히 하나일 때만 직행한다 —
        // 여럿일 때 잘못된 현장을 여는 비용이 탭 한 번보다 훨씬 크다.
        $only = $user->eventParticipations()
            ->where('status', \App\Enums\ParticipantStatus::ACTIVE)
            ->whereHas('project', fn ($q) => $q->active())
            ->get()
            ->filter(fn ($p) => $p->role->isDispatchCandidate());

        $middle = $only->count() === 1
            ? ['key' => 'work', 'label' => '지령', 'icon' => 'ambulance', 'url' => route('events.dispatch', $only->first()->project_id)]
            : ['key' => 'work', 'label' => '행사', 'icon' => 'pin', 'url' => route('events.join')];
    } else {
        // 참가 중인 행사가 하나면 «그 행사의» 신고 화면으로. 화면에 행사 이름이 떠야
        // 신고자가 자기 신고가 어디로 가는지 안다.
        $currentEvent = $user?->currentEvent();
        $middle = [
            'key' => 'work', 'label' => '구조요청', 'icon' => 'ambulance',
            'url' => $currentEvent ? route('request.create.project', $currentEvent->slug) : route('request.create'),
        ];
    }

    $tabs = [
        ['key' => 'home', 'label' => '홈', 'icon' => 'home', 'url' => $homeUrl],
        $middle,
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
