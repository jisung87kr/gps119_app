{{--
    소셜 로그인 중복 계정 안내 — 미로그인 상태에서도 뜨므로 인증 껍데기를 쓴다.
--}}
<x-layouts.app title="GPS119 - 이미 등록된 계정" bare>
    <x-ui.auth-shell subtitle="이미 등록된 계정입니다">
        <x-ui.card class="text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-warning-50 text-warning-600">
                <x-ui.icon name="alert-circle" class="h-8 w-8" />
            </span>
            <p class="mt-4 text-lg font-extrabold text-ink-950">가입을 진행할 수 없습니다</p>
            <p class="mt-2 break-keep text-base leading-relaxed text-ink-500">
                다른 소셜 계정으로 가입하셨거나 같은 정보의 계정이 이미 있습니다.
                기존 계정으로 로그인해 주세요.
            </p>
        </x-ui.card>

        <div class="mt-6 space-y-3">
            <x-ui.button :href="route('login')">로그인 화면으로</x-ui.button>
            <x-ui.button :href="route('password.request')" variant="secondary">비밀번호 찾기</x-ui.button>
        </div>
    </x-ui.auth-shell>
</x-layouts.app>
