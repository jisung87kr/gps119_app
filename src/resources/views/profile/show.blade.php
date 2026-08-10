{{--
    프로필 — src/tmp/profile.html 기준.
    셸에서 푸터가 사라졌으므로 사업자 정보(법적 표기)를 이 화면 하단으로 옮겼다.
--}}
<x-layouts.app title="GPS119 - 프로필" heading="프로필" tab="profile">
    @php
        $user = auth()->user();
        $recentRequests = $user->requests()->latest()->limit(5)->get();
    @endphp

    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
        @endif

        {{-- 계정 카드 --}}
        <x-ui.card>
            <div class="flex items-center gap-4">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                    <x-ui.icon name="user" class="h-[30px] w-[30px]" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xl font-extrabold text-ink-950">{{ $user->name }}</p>
                    <p class="mt-0.5 text-base text-ink-500">{{ $user->formatted_phone }}</p>
                </div>
                <x-ui.button :href="route('profile.edit')" variant="secondary" size="sm">수정</x-ui.button>
            </div>

            @if ($user->email)
                <div class="mt-4 flex items-center justify-between rounded-xl bg-ink-50 px-4 py-3">
                    <p class="text-sm font-bold text-ink-500">이메일</p>
                    <p class="truncate pl-3 text-sm font-bold text-ink-900">{{ $user->email }}</p>
                </div>
            @endif

            <div class="mt-2.5 flex items-center justify-between rounded-xl bg-ink-50 px-4 py-3">
                <p class="text-sm font-bold text-ink-500">가입일</p>
                <p class="text-sm font-bold text-ink-900">{{ $user->created_at->format('Y년 m월 d일') }}</p>
            </div>

            @if ($user->hasRole('rescuer'))
                <div class="mt-2.5 flex items-center justify-between rounded-xl bg-ink-50 px-4 py-3">
                    <p class="text-sm font-bold text-ink-500">계정 권한</p>
                    <x-ui.badge tone="brand" size="sm" icon="ambulance">구조대원</x-ui.badge>
                </div>
            @endif

            {{-- 소셜 연동 상태 --}}
            @if ($user->provider)
                <div class="mt-4 flex items-center gap-3 border-t border-ink-100 pt-4">
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold',
                        'bg-[#03C75A] text-white' => $user->provider === 'naver',
                        'bg-[#FEE500] text-[#3C1E1E]' => $user->provider === 'kakao',
                    ])>{{ mb_strtoupper(mb_substr($user->provider, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="text-base font-bold text-ink-950">{{ ucfirst($user->provider) }} 계정 연동됨</p>
                        <p class="mt-0.5 text-sm text-ink-400">소셜 계정으로 로그인 중입니다.</p>
                    </div>
                </div>
            @endif
        </x-ui.card>

        {{--
            관리자 전환 진입점. 관리자는 로그인하면 /admin/dashboard 로 바로 가지만,
            사용자 화면(홈·신고·프로필)으로 넘어오면 돌아갈 길이 없었다.
            반대 방향(관리 셸 → 사용자 페이지)은 admin 레이아웃의 아바타 드롭다운에 있다.
        --}}
        @if ($user->hasRole('admin'))
            <x-ui.section title="관리">
                <x-ui.list>
                    <x-ui.list-item :href="route('admin.dashboard')" icon="shield"
                                    title="관리자 화면"
                                    meta="실시간 관제 · 회원 · 행사 · 통계" />
                </x-ui.list>
            </x-ui.section>
        @endif

        {{-- 활동 통계 --}}
        <div @class(['grid gap-3', 'grid-cols-2' => $user->hasRole('rescuer')])>
            <x-ui.stat label="총 구조 요청" :value="$user->requests()->count()" />
            @if ($user->hasRole('rescuer'))
                <x-ui.stat label="처리 완료"
                           :value="$user->assignedRequests()->where('status', \App\Enums\RequestStatus::COMPLETED->value)->count()" />
            @endif
        </div>

        {{-- 최근 구조 요청 내역 --}}
        <x-ui.section title="최근 구조 요청 내역">
            <x-ui.list>
                @forelse ($recentRequests as $request)
                    <x-ui.list-item :href="route('request.show', $request)" icon="pin"
                                    :title="$request->address ?? '위치 정보 없음'"
                                    :meta="$request->created_at->format('Y.m.d H:i')"
                                    :chevron="false">
                        <x-slot:trailing>
                            <x-ui.badge :tone="$request->status->badgeTone()"
                                        :icon="$request->status->badgeIcon()" size="sm">
                                {{ $request->status->label() }}
                            </x-ui.badge>
                        </x-slot:trailing>
                    </x-ui.list-item>
                @empty
                    <x-ui.empty icon="pin">아직 구조 요청 내역이 없습니다.</x-ui.empty>
                @endforelse
            </x-ui.list>
        </x-ui.section>

        {{-- 설정 --}}
        <x-ui.section title="설정">
            <x-ui.list>
                {{--
                    웹 푸시 토글. 상태 판단·전환은 전부 resources/js/push-toggle.js 가 한다
                    (브라우저 권한과 서버 등록이 어긋나면 «켜져 있는데 안 온다»가 되므로
                     화면이 자체 판단을 갖지 않게 한다).
                --}}
                <div data-push-section class="flex items-center gap-3 px-4 py-3.5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-900">
                        <x-ui.icon name="bell" class="h-[17px] w-[17px]" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-base font-bold text-ink-950">알림 받기</span>
                        <span data-push-state class="mt-0.5 block text-sm text-ink-400">확인 중…</span>
                    </span>
                    <button type="button" data-push-toggle hidden
                            class="shrink-0 rounded-lg border border-ink-200 px-3 py-1.5 text-sm font-bold text-ink-900 disabled:opacity-50"></button>
                </div>

                @if (! $user->provider)
                    <x-ui.list-item :href="route('profile.password.edit')" icon="key"
                                    icon-tone="neutral" icon-size="sm" title="비밀번호 변경" />
                @endif
                <x-ui.list-item :href="route('profile.edit')" icon="user"
                                icon-tone="neutral" icon-size="sm" title="내 정보 수정" />
                <x-ui.list-item :href="route('legal.privacy')" icon="shield"
                                icon-tone="neutral" icon-size="sm" title="개인정보처리방침" />
                <x-ui.list-item :href="route('legal.location-terms')" icon="pin"
                                icon-tone="neutral" icon-size="sm" title="위치기반서비스 이용약관" />
            </x-ui.list>
        </x-ui.section>

        {{-- 계정 액션 --}}
        <div class="space-y-3 pt-2">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">로그아웃</x-ui.button>
            </form>
            <x-ui.button :href="route('profile.delete')" variant="ghost">회원 탈퇴</x-ui.button>
        </div>

        {{-- 사업자 정보 — 셸 푸터 제거에 따라 이관된 법적 표기 --}}
        <div class="border-t border-ink-100 pt-6 text-center">
            <p class="text-xs text-ink-400">업체명: 세이브미 | 사업자번호: 852-08-02915</p>
            <p class="mt-1 text-xs text-ink-400">&copy; {{ date('Y') }} GPS119. All rights reserved.</p>
            <p class="mt-1 text-xs text-ink-400">
                Made by <a href="https://indigo404.com" class="text-brand-600 underline underline-offset-2">indigo404.com</a>
            </p>
        </div>
    </div>
</x-layouts.app>
