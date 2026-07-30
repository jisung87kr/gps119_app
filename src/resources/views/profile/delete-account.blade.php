{{--
    회원 탈퇴 — 되돌릴 수 없는 작업이므로 이 화면에서는 danger 를 쓴다.
    (팔레트 규칙상 레드는 긴급 전용이지만, 영구 삭제도 같은 급의 경고다)
--}}
<x-layouts.app title="GPS119 - 회원 탈퇴" heading="회원 탈퇴" :back="route('profile.show')" tab="profile">
    @php $user = auth()->user(); @endphp

    <div class="space-y-6">
        <x-ui.alert tone="danger">
            계정을 영구적으로 삭제합니다. 이 작업은 되돌릴 수 없습니다.
        </x-ui.alert>

        <x-ui.card>
            <p class="text-base font-bold text-ink-950">삭제되는 내용</p>
            <ul class="mt-2 list-inside list-disc space-y-1.5 text-sm leading-relaxed text-ink-600">
                <li>모든 개인 데이터와 구조 요청 기록이 영구 삭제됩니다.</li>
                <li>삭제된 데이터는 어떤 방법으로도 복구할 수 없습니다.</li>
                <li>같은 연락처로 재가입은 가능하나 이전 데이터는 연동되지 않습니다.</li>
            </ul>
        </x-ui.card>

        {{-- 삭제 대상 확인 — 이름은 길 수 있어 통계 카드 대신 라벨/값 행으로 --}}
        <x-ui.card>
            <div class="flex items-center justify-between rounded-xl bg-ink-50 px-4 py-3">
                <p class="text-sm font-bold text-ink-500">계정</p>
                <p class="truncate pl-3 text-sm font-bold text-ink-900">{{ $user->name }}</p>
            </div>
            <div class="mt-2.5 flex items-center justify-between rounded-xl bg-ink-50 px-4 py-3">
                <p class="text-sm font-bold text-ink-500">구조 요청 기록</p>
                <p class="text-sm font-bold text-ink-900">{{ $user->requests()->count() }}건</p>
            </div>
        </x-ui.card>

        <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-4">
            @csrf
            @method('DELETE')

            <x-ui.field label="본인 확인 비밀번호" for="password" name="password">
                <x-ui.input id="password" name="password" type="password" autocomplete="current-password"
                            required placeholder="현재 비밀번호를 입력하세요"
                            :error="$errors->has('password')" />
            </x-ui.field>

            <label for="confirm_delete" class="flex items-start gap-3 text-sm leading-snug text-ink-600">
                <input id="confirm_delete" name="confirm_delete" type="checkbox" required
                       class="mt-0.5 h-5 w-5 shrink-0 rounded border-ink-300 text-danger-600 focus:ring-danger-200">
                모든 내용을 확인했으며, 데이터 영구 삭제에 동의합니다.
            </label>

            <div class="space-y-3 pt-3">
                <x-ui.button type="submit" variant="danger"
                             onclick="return confirm('정말로 계정을 삭제하시겠습니까?')">
                    계정 영구 삭제
                </x-ui.button>
                <x-ui.button :href="route('profile.show')" variant="secondary">취소</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.app>
