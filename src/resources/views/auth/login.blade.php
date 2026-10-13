{{-- 로그인 — src/tmp/login.html 기준. 일반 사용자는 연락처, 관리자는 이메일. --}}
<x-layouts.app title="GPS119 - 로그인" bare>
    <x-ui.auth-shell>
        <form class="space-y-4" action="{{ route('login') }}" method="POST">
            @csrf

            <x-ui.field label="연락처 또는 이메일" for="phone" name="phone"
                        hint="일반 사용자는 연락처로, 관리자는 이메일로 로그인하세요.">
                <x-ui.input id="phone" name="phone" type="text" autocomplete="username" required autofocus
                            value="{{ old('phone') }}"
                            placeholder="010-1234-5678 또는 admin@example.com"
                            :error="$errors->has('phone')" />
            </x-ui.field>

            <x-ui.field label="비밀번호" for="password" name="password">
                <x-ui.input id="password" name="password" type="password" autocomplete="current-password" required
                            placeholder="••••••••" :error="$errors->has('password')" />
            </x-ui.field>

            <div class="flex items-center justify-between pt-1 text-sm">
                <label for="remember" class="flex items-center gap-2 text-ink-600">
                    <input id="remember" name="remember" type="checkbox"
                           class="h-5 w-5 rounded border-ink-300 text-brand-600 focus:ring-brand-200">
                    로그인 상태 유지
                </label>
                <a href="{{ route('password.request') }}" class="font-bold text-brand-600 underline underline-offset-2">
                    비밀번호 찾기
                </a>
            </div>

            <x-ui.button type="submit" size="xl">로그인</x-ui.button>
        </form>

        <div class="my-7 flex items-center gap-3">
            <span class="h-px flex-1 bg-ink-100"></span>
            <span class="text-sm font-medium text-ink-400">간편 로그인</span>
            <span class="h-px flex-1 bg-ink-100"></span>
        </div>

        {{-- 네이버 브랜드 컬러는 고정값이라 팔레트를 따르지 않는다 --}}
        <a href="{{ route('login.social', 'naver') }}"
           class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[#03C75A] py-4 text-base font-extrabold text-white active:brightness-95">
            <span class="flex h-5 w-5 items-center justify-center rounded-sm bg-white text-xs font-extrabold text-[#03C75A]">N</span>
            네이버로 시작
        </a>

        {{-- Sign in with Apple — iOS 앱(플러그인이 든 빌드)에서만 JS 가 보이게 한다 (심사 4.8).
             Apple 휴먼 인터페이스 지침: 검정 바탕·흰 로고, 다른 로그인 버튼과 같은 크기 이상. --}}
        <button type="button" data-apple-signin hidden
                class="mt-3 flex w-full items-center justify-center gap-2 rounded-2xl bg-black py-4 text-base font-extrabold text-white active:brightness-90 disabled:opacity-60">
            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 814 1000" fill="currentColor"><path d="M788 341c-6 4-108 62-108 190 0 149 131 201 135 203-1 3-21 72-69 142-43 62-88 124-156 124s-86-40-164-40c-77 0-104 41-167 41s-106-57-156-127C45 791 0 666 0 548c0-191 124-292 246-292 65 0 119 43 160 43 39 0 100-45 174-45 28 0 129 3 196 99zM558 159c31-37 53-88 53-139 0-7-1-14-2-20-50 2-110 34-146 76-28 32-55 83-55 135 0 8 1 16 2 18 3 1 9 2 14 2 45 0 102-30 134-72z"/></svg>
            Apple로 계속하기
        </button>

        <p class="mt-8 text-center text-base text-ink-500">
            계정이 없으신가요?
            <a href="{{ route('register') }}" class="font-extrabold text-brand-600 underline underline-offset-2">지금 회원가입</a>
        </p>

        {{-- 위치정보를 다루는 서비스라 «가입 전에» 볼 수 있어야 한다. --}}
        <p class="mt-6 text-center text-sm leading-relaxed text-ink-400">
            <a href="{{ route('legal.privacy') }}" class="underline underline-offset-2">개인정보처리방침</a>
            <span class="px-1.5 text-ink-300">·</span>
            <a href="{{ route('legal.location-terms') }}" class="underline underline-offset-2">위치기반서비스 이용약관</a>
        </p>
    </x-ui.auth-shell>
</x-layouts.app>
