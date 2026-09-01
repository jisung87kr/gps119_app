{{--
    소셜 가입자 등 «회원가입 폼을 거치지 않은» 사용자에게 필수 동의를 받는다.
    문구·구조는 register.blade.php 의 약관 절과 같아야 한다 — 두 벌로 갈리면
    한쪽만 고쳐진다.
--}}
<x-layouts.app title="GPS119 - 약관 동의" bare>
    <x-ui.auth-shell subtitle="서비스 이용에 필요한 동의를 받습니다">
        <p class="text-sm leading-relaxed text-ink-600">
            GPS119 는 행사 중 참가자의 위치를 상황실에 전달합니다.
            아래 항목에 동의해야 서비스를 이용할 수 있습니다.
        </p>

        <form class="mt-6 space-y-4" action="{{ route('consent.store') }}" method="POST">
            @csrf

            <fieldset class="space-y-3 rounded-2xl border border-ink-200 p-4">
                <legend class="px-1 text-sm font-bold text-ink-900">약관 동의</legend>

                @foreach (\App\Enums\ConsentType::required() as $consent)
                    <label class="flex items-start gap-3 text-sm leading-relaxed text-ink-600">
                        <input type="checkbox" name="consents[]" value="{{ $consent->value }}" required
                               class="mt-0.5 h-5 w-5 shrink-0 rounded border-ink-300 text-brand-600 focus:ring-brand-200">
                        <span>
                            <span class="font-bold text-ink-900">[필수]</span>
                            <a href="{{ route($consent->routeName()) }}" target="_blank" rel="noopener"
                               class="font-bold text-brand-600 underline underline-offset-2">{{ $consent->label() }}</a>에
                            동의합니다.
                        </span>
                    </label>
                @endforeach

                @error('consents')
                    <p class="text-sm font-bold text-danger-600">{{ $message }}</p>
                @enderror
            </fieldset>

            <x-ui.button type="submit" size="xl">동의하고 시작하기</x-ui.button>
        </form>

        <form action="{{ route('consent.decline') }}" method="POST" class="mt-3">
            @csrf
            <x-ui.button type="submit" variant="ghost">동의하지 않고 나가기</x-ui.button>
        </form>
    </x-ui.auth-shell>
</x-layouts.app>
