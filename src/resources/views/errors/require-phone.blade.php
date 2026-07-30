{{--
    연락처 미등록 안내 — 신고 동선의 이탈 지점(routes/web.php 의 /requests/create*).
    구조대가 직접 전화하는 도메인이라 전화번호 없이는 신고를 받을 수 없다.
--}}
<x-layouts.app title="GPS119 - 연락처 등록 필요" heading="연락처 등록" :back="route('dashboard')">
    <div class="space-y-6">
        <div class="text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-warning-50 text-warning-600">
                <x-ui.icon name="phone" class="h-8 w-8" />
            </span>
            <h1 class="mt-4 break-keep text-[26px] font-extrabold leading-snug tracking-tight text-ink-950">
                연락처가 필요합니다
            </h1>
            <p class="mt-2 break-keep text-base leading-relaxed text-ink-500">
                구조대가 위치를 확인한 뒤 직접 전화를 드립니다.
                신고를 보내려면 먼저 전화번호를 등록해 주세요.
            </p>
        </div>

        @isset($project)
            <x-ui.card>
                <p class="text-sm font-bold text-ink-500">입장하려던 행사</p>
                <p class="mt-1 text-base font-bold text-ink-950">{{ $project->name }}</p>
            </x-ui.card>
        @endisset

        <div class="space-y-3">
            <x-ui.button :href="route('profile.edit')">연락처 등록하기</x-ui.button>
            <x-ui.button :href="route('dashboard')" variant="secondary">홈으로</x-ui.button>
        </div>

        <x-ui.alert tone="brand">
            등록한 연락처는 구조 요청 시에만 사용되며, 개인정보보호법에 따라 보호됩니다.
        </x-ui.alert>
    </div>
</x-layouts.app>
