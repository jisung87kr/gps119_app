{{-- 비밀번호 찾기 — 가입 연락처로 재설정 링크 전송. --}}
<x-layouts.app title="GPS119 - 비밀번호 찾기" bare>
    <x-ui.auth-shell subtitle="가입한 연락처로 재설정 링크를 보내드립니다">
        @if (session('status'))
            <x-ui.alert tone="success" class="mb-5">{{ session('status') }}</x-ui.alert>
        @endif

        <form class="space-y-4" action="{{ route('password.email') }}" method="POST">
            @csrf

            <x-ui.field label="연락처" for="phone" name="phone">
                <x-ui.input id="phone" name="phone" type="tel" autocomplete="tel" required autofocus
                            value="{{ old('phone') }}" placeholder="010-1234-5678"
                            :error="$errors->has('phone')" />
            </x-ui.field>

            <x-ui.button type="submit" size="xl">비밀번호 재설정 링크 전송</x-ui.button>
        </form>

        <p class="mt-8 text-center text-base text-ink-500">
            <a href="{{ route('login') }}" class="font-extrabold text-brand-600 underline underline-offset-2">
                로그인으로 돌아가기
            </a>
        </p>
    </x-ui.auth-shell>
</x-layouts.app>
