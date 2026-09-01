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

            {{--
                🔴 **위치기반서비스 약관은 개인정보처리방침과 «분리»해서 받는다.**
                   위치정보법은 개인위치정보 수집에 별도 동의를 요구한다 — 한 체크박스로
                   묶으면 동의를 받은 것으로 보지 않는다.
                🔑 링크는 «새 탭»으로 연다. 폼을 떠나면 입력이 날아가서, 사용자는
                   결국 안 읽고 체크한다.
            --}}
            <fieldset class="!mt-6 space-y-3 rounded-2xl border border-ink-200 p-4">
                <legend class="px-1 text-sm font-bold text-ink-900">약관 동의</legend>

                @foreach (\App\Enums\ConsentType::required() as $consent)
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
