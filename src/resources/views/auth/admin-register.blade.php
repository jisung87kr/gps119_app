{{--
    관리자 회원가입 — 관리자 백오피스는 리뉴얼 범위 밖이지만 이 화면은 인증 껍데기를
    공유하는 로그인/회원가입 계열이라 함께 이식했다(옛 셸에 남으면 혼자 어긋난다).
--}}
<x-layouts.app title="GPS119 - 관리자 회원가입" bare>
    <x-ui.auth-shell subtitle="관리자 계정 생성">
        <form class="space-y-4" method="POST" action="{{ route('admin.register') }}">
            @csrf

            <x-ui.field label="이름" for="name" name="name">
                <x-ui.input id="name" name="name" type="text" required autofocus
                            value="{{ old('name') }}" placeholder="이름을 입력하세요"
                            :error="$errors->has('name')" />
            </x-ui.field>

            <x-ui.field label="이메일" for="email" name="email"
                        hint="관리자는 이메일로 로그인합니다.">
                <x-ui.input id="email" name="email" type="email" autocomplete="email" required
                            value="{{ old('email') }}" placeholder="admin@example.com"
                            :error="$errors->has('email')" />
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

            <x-ui.alert tone="brand" class="!mt-6">
                관리자 계정은 이메일로 로그인하며 시스템 관리 권한을 가집니다.
            </x-ui.alert>

            <x-ui.button type="submit" size="xl">관리자 계정 생성</x-ui.button>
        </form>

        <div class="mt-8 space-y-1.5 text-center text-base text-ink-500">
            <p>
                이미 계정이 있으신가요?
                <a href="{{ route('login') }}" class="font-extrabold text-brand-600 underline underline-offset-2">로그인하기</a>
            </p>
            <p>
                일반 사용자 계정을 만드시나요?
                <a href="{{ route('register') }}" class="font-extrabold text-brand-600 underline underline-offset-2">일반 회원가입</a>
            </p>
        </div>
    </x-ui.auth-shell>
</x-layouts.app>
