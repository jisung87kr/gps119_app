{{-- 회원가입 — 로그인과 같은 인증 껍데기. 일반 사용자는 연락처로 가입한다. --}}
<x-layouts.app title="GPS119 - 회원가입" bare>
    <x-ui.auth-shell subtitle="구조 요청을 위해 연락처를 등록해 주세요">
        <form class="space-y-4" action="{{ route('register') }}" method="POST">
            @csrf

            <x-ui.field label="연락처" for="phone" name="phone"
                        hint="구조대가 직접 전화하는 번호입니다.">
                <x-ui.input id="phone" name="phone" type="tel" autocomplete="tel" required autofocus
                            value="{{ old('phone') }}" placeholder="010-1234-5678"
                            :error="$errors->has('phone')" />
            </x-ui.field>

            <x-ui.field label="비밀번호" for="password" name="password">
                <x-ui.input id="password" name="password" type="password" autocomplete="new-password" required
                            placeholder="••••••••" :error="$errors->has('password')" />
            </x-ui.field>

            <x-ui.field label="비밀번호 확인" for="password_confirmation" name="password_confirmation">
                <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                            autocomplete="new-password" required placeholder="••••••••"
                            :error="$errors->has('password_confirmation')" />
            </x-ui.field>

            <x-ui.button type="submit" size="xl" class="!mt-6">회원가입</x-ui.button>
        </form>

        <div class="my-7 flex items-center gap-3">
            <span class="h-px flex-1 bg-ink-100"></span>
            <span class="text-sm font-medium text-ink-400">간편 가입</span>
            <span class="h-px flex-1 bg-ink-100"></span>
        </div>

        {{-- 네이버 브랜드 컬러는 고정값이라 팔레트를 따르지 않는다 --}}
        <a href="{{ route('login.social', 'naver') }}"
           class="flex w-full items-center justify-center gap-2 rounded-2xl bg-[#03C75A] py-4 text-base font-extrabold text-white active:brightness-95">
            <span class="flex h-5 w-5 items-center justify-center rounded-sm bg-white text-xs font-extrabold text-[#03C75A]">N</span>
            네이버로 시작
        </a>

        <p class="mt-8 text-center text-base text-ink-500">
            이미 계정이 있으신가요?
            <a href="{{ route('login') }}" class="font-extrabold text-brand-600 underline underline-offset-2">로그인하기</a>
        </p>
    </x-ui.auth-shell>
</x-layouts.app>
