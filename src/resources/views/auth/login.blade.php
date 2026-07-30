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

        <p class="mt-8 text-center text-base text-ink-500">
            계정이 없으신가요?
            <a href="{{ route('register') }}" class="font-extrabold text-brand-600 underline underline-offset-2">지금 회원가입</a>
        </p>
    </x-ui.auth-shell>
</x-layouts.app>
