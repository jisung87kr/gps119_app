{{--
    발급 계정의 첫 로그인 셋업 (ADR-0009). 비밀번호 변경 + 필수 동의를 한 화면에서.
    약관 절의 문구·구조는 register.blade.php / consent.blade.php 와 같아야 한다.
--}}
<x-layouts.app title="GPS119 - 초기 설정" bare>
    <x-ui.auth-shell subtitle="처음 로그인입니다 — 비밀번호를 정하고 시작하세요">
        <p class="text-sm leading-relaxed text-ink-600">
            관리자가 발급한 계정입니다. <b class="text-ink-900">본인만 쓰는 비밀번호</b>로 바꾼 뒤
            아래 약관에 동의하면 서비스를 이용할 수 있습니다.
        </p>

        <form class="mt-6 space-y-4" action="{{ route('account.setup.store') }}" method="POST">
            @csrf

            <x-ui.field label="새 비밀번호" for="password" name="password">
                <x-ui.input id="password" name="password" type="password" autocomplete="new-password" required autofocus
                            placeholder="••••••••" :error="$errors->has('password')" />
            </x-ui.field>

            <x-ui.field label="새 비밀번호 확인" for="password_confirmation" name="password_confirmation">
                <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                            autocomplete="new-password" required placeholder="••••••••" />
            </x-ui.field>

            <fieldset class="!mt-6 space-y-3 rounded-2xl border border-ink-200 p-4">
                <legend class="px-1 text-sm font-bold text-ink-900">약관 동의</legend>

                @foreach ($required as $consent)
                    <label class="flex items-start gap-3 text-sm leading-relaxed text-ink-600">
                        <input type="checkbox" name="consents[]" value="{{ $consent->value }}" required
                               @checked(in_array($consent->value, old('consents', []), true))
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

            <x-ui.button type="submit" size="xl">비밀번호 설정하고 시작하기</x-ui.button>
        </form>

        <form action="{{ route('logout') }}" method="POST" class="mt-3">
            @csrf
            <x-ui.button type="submit" variant="ghost">로그아웃</x-ui.button>
        </form>
    </x-ui.auth-shell>
</x-layouts.app>
