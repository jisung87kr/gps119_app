{{-- 비밀번호 변경. --}}
<x-layouts.app title="GPS119 - 비밀번호 변경" heading="비밀번호 변경" :back="route('profile.show')" tab="profile">
    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <x-ui.field label="현재 비밀번호" for="current_password" name="current_password">
                <x-ui.input id="current_password" name="current_password" type="password"
                            autocomplete="current-password" required placeholder="현재 사용 중인 비밀번호"
                            :error="$errors->has('current_password')" />
            </x-ui.field>

            <x-ui.field label="새 비밀번호" for="password" name="password"
                        hint="8자 이상, 영문·숫자·특수문자를 섞으면 더 안전합니다.">
                <x-ui.input id="password" name="password" type="password" autocomplete="new-password"
                            required placeholder="••••••••" :error="$errors->has('password')" />
            </x-ui.field>

            <x-ui.field label="새 비밀번호 확인" for="password_confirmation" name="password_confirmation">
                <x-ui.input id="password_confirmation" name="password_confirmation" type="password"
                            autocomplete="new-password" required placeholder="••••••••"
                            :error="$errors->has('password_confirmation')" />
            </x-ui.field>

            <div class="space-y-3 pt-3">
                <x-ui.button type="submit">비밀번호 변경</x-ui.button>
                <x-ui.button :href="route('profile.show')" variant="secondary">취소</x-ui.button>
            </div>
        </form>

        <x-ui.card>
            <div class="flex gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <x-ui.icon name="key" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-base font-bold text-ink-950">안전한 비밀번호 설정</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm leading-relaxed text-ink-500">
                        <li>최소 8자 이상으로 설정하세요.</li>
                        <li>대소문자·숫자·특수문자를 섞으면 더 안전합니다.</li>
                        <li>다른 사이트와 같은 비밀번호는 피하세요.</li>
                    </ul>
                </div>
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
