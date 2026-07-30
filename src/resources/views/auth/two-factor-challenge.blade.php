{{--
    2단계 인증 확인 — config/fortify.php 에서 twoFactorAuthentication 이 켜져 있어
    GET /two-factor-challenge 라우트가 살아 있는데도 뷰가 없어 500 이 나던 화면.

    Fortify 는 code / recovery_code 중 채워진 값을 검증하므로 두 입력을 한 폼에
    같이 두면 JS 없이 전환 없이 처리된다.
--}}
<x-layouts.app title="GPS119 - 2단계 인증" bare>
    <x-ui.auth-shell subtitle="2단계 인증 코드를 입력해 주세요">
        @if ($errors->any())
            <x-ui.alert tone="danger" class="mb-5">{{ $errors->first() }}</x-ui.alert>
        @endif

        <form class="space-y-4" action="{{ route('two-factor.login') }}" method="POST">
            @csrf

            <x-ui.field label="인증 코드" for="code" name="code"
                        hint="인증 앱에 표시된 6자리 숫자입니다.">
                <x-ui.input id="code" name="code" type="text" inputmode="numeric"
                            autocomplete="one-time-code" autofocus placeholder="000000"
                            :error="$errors->has('code')" />
            </x-ui.field>

            <div class="my-6 flex items-center gap-3">
                <span class="h-px flex-1 bg-ink-100"></span>
                <span class="text-sm font-medium text-ink-400">인증 앱을 쓸 수 없다면</span>
                <span class="h-px flex-1 bg-ink-100"></span>
            </div>

            <x-ui.field label="복구 코드" for="recovery_code" name="recovery_code"
                        hint="2단계 인증 설정 시 저장해 둔 코드 하나를 입력하세요.">
                <x-ui.input id="recovery_code" name="recovery_code" type="text"
                            autocomplete="one-time-code" placeholder="xxxxxxxx-xxxxxxxx"
                            :error="$errors->has('recovery_code')" />
            </x-ui.field>

            <x-ui.button type="submit" size="xl" class="!mt-6">인증하기</x-ui.button>
        </form>

        <p class="mt-8 text-center text-base text-ink-500">
            <a href="{{ route('login') }}" class="font-extrabold text-brand-600 underline underline-offset-2">
                로그인으로 돌아가기
            </a>
        </p>
    </x-ui.auth-shell>
</x-layouts.app>
