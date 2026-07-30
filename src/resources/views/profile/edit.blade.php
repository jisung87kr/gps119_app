{{--
    내 정보 수정. 소셜 계정은 이름을 연동 사이트에서 관리하므로 읽기 전용.
    비밀번호 변경은 별도 화면(profile/edit-password)으로 분리했다 —
    이 화면에 폼 두 개를 겹쳐 두던 구조를 정리.
--}}
<x-layouts.app title="GPS119 - 내 정보 수정" heading="내 정보 수정" :back="route('profile.show')" tab="profile">
    @php $user = auth()->user(); @endphp

    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <x-ui.field label="이름" for="name" name="name"
                        :hint="$user->provider ? '소셜 계정 정보는 연동된 사이트에서 변경할 수 있습니다.' : null">
                <x-ui.input id="name" name="name" type="text" required
                            value="{{ old('name', $user->name) }}"
                            :readonly="(bool) $user->provider"
                            :class="$user->provider ? 'cursor-not-allowed bg-ink-50 text-ink-500' : ''"
                            :error="$errors->has('name')" />
            </x-ui.field>

            <x-ui.field label="연락처" for="phone" name="phone"
                        hint="구조대가 직접 전화하는 번호입니다.">
                <x-ui.input id="phone" name="phone" type="tel" required
                            value="{{ old('phone', $user->phone) }}" placeholder="010-0000-0000"
                            :error="$errors->has('phone')" />
            </x-ui.field>

            <div class="space-y-3 pt-3">
                <x-ui.button type="submit">저장</x-ui.button>
                <x-ui.button :href="route('profile.show')" variant="secondary">취소</x-ui.button>
            </div>
        </form>

        @if (! $user->provider)
            <x-ui.section title="보안">
                <x-ui.list>
                    <x-ui.list-item :href="route('profile.password.edit')" icon="key"
                                    icon-tone="neutral" icon-size="sm" title="비밀번호 변경" />
                </x-ui.list>
            </x-ui.section>
        @endif
    </div>
</x-layouts.app>
