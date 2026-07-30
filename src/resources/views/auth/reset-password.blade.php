{{-- 비밀번호 재설정 — 메일/문자 링크로 진입. --}}
<x-layouts.app title="GPS119 - 비밀번호 재설정" bare>
    <x-ui.auth-shell subtitle="새로운 비밀번호를 설정해 주세요">
        <form class="space-y-4" action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <x-ui.field label="연락처" for="phone" name="phone">
                <x-ui.input id="phone" name="phone" type="tel" autocomplete="tel" required
                            value="{{ old('phone', $request->phone) }}" placeholder="010-1234-5678"
                            :error="$errors->has('phone')" />
            </x-ui.field>

            <x-ui.field label="새 비밀번호" for="password" name="password">
                <x-ui.input id="password" name="password" type="password" autocomplete="new-password" required
                            placeholder="••••••••" :error="$errors->has('password')" />
            </x-ui.field>

            <x-ui.field label="비밀번호 확인" for="password_confirmation" name="password_confirmation">
                <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                            autocomplete="new-password" required placeholder="••••••••"
                            :error="$errors->has('password_confirmation')" />
            </x-ui.field>

            <x-ui.button type="submit" size="xl" class="!mt-6">비밀번호 재설정</x-ui.button>
        </form>

        <p class="mt-8 text-center text-base text-ink-500">
            <a href="{{ route('login') }}" class="font-extrabold text-brand-600 underline underline-offset-2">
                로그인으로 돌아가기
            </a>
        </p>
    </x-ui.auth-shell>
</x-layouts.app>
