{{--
    이메일 인증 안내 — FortifyServiceProvider 가 verifyEmailView 로 등록해 두고도
    파일이 없던 화면. 현재 config/fortify.php 에서 Features::emailVerification() 이
    주석 처리돼 라우트가 없지만, 기능을 켜면 바로 동작하도록 만들어 둔다.
--}}
<x-layouts.app title="GPS119 - 이메일 인증" bare>
    <x-ui.auth-shell subtitle="이메일 인증을 완료해 주세요">
        @if (session('status') === 'verification-link-sent')
            <x-ui.alert tone="success" class="mb-5">
                인증 메일을 다시 보냈습니다. 메일함을 확인해 주세요.
            </x-ui.alert>
        @endif

        <p class="text-base leading-relaxed text-ink-600">
            가입하신 이메일로 인증 링크를 보냈습니다. 링크를 눌러 인증을 마치면
            서비스를 이용할 수 있습니다.
        </p>

        <div class="mt-6 space-y-2.5">
            <form action="{{ route('verification.send') }}" method="POST">
                @csrf
                <x-ui.button type="submit" size="xl">인증 메일 다시 보내기</x-ui.button>
            </form>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <x-ui.button type="submit" variant="secondary">로그아웃</x-ui.button>
            </form>
        </div>
    </x-ui.auth-shell>
</x-layouts.app>
