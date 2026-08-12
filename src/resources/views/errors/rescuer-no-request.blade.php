{{--
    구조대 계정의 신고 작성 차단 (2026-08-12 현장 결정, 피드백 #4).

    「구조대로 회원정보가 되어있는 계정은 '구조요청' 삭제 — 단순 지령만 받을 수 있게」.
    메뉴에서 숨기는 게 아니라 기능 자체를 막는 결정이었다.

    🔑 그래서 이 화면은 «막다른 길»이면 안 된다. 구급대원 본인이 코스에서 쓰러지는 것은
       이 도메인의 실제 사고 유형이고, 그때 그 사람은 앱을 다시 배울 수 없다.
       전화 두 개를 이 화면의 주 액션으로 둔다.
--}}
<x-layouts.app title="GPS119 - 구조대 계정" heading="구조요청" :back="route('dispatches.index')">
    <div class="space-y-6">
        <div class="text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                <x-ui.icon name="ambulance" class="h-8 w-8" />
            </span>
            <h1 class="mt-4 break-keep text-[26px] font-extrabold leading-snug tracking-tight text-ink-950">
                구조대 계정입니다
            </h1>
            <p class="mt-2 break-keep text-base leading-relaxed text-ink-500">
                이 계정은 상황실의 지령을 받는 용도입니다.
                구조요청 접수는 일반 참가자 계정에서 이루어집니다.
            </p>
        </div>

        @isset($project)
            <x-ui.card>
                <p class="text-sm font-bold text-ink-500">들어오려던 행사</p>
                <p class="mt-1 text-base font-bold text-ink-950">{{ $project->name }}</p>
            </x-ui.card>
        @endisset

        <x-ui.alert tone="warning">
            <b>본인이 도움이 필요한 상황이라면</b> 아래로 전화해 주세요.
            이 계정으로는 신고를 올릴 수 없습니다.
        </x-ui.alert>

        <div class="space-y-3">
            <x-ui.button href="tel:119" variant="danger" size="xl">
                <x-ui.icon name="phone" class="h-6 w-6" />
                119 전화
            </x-ui.button>
            <x-ui.button :href="'tel:'.$controlTel" variant="secondary">
                <x-ui.icon name="phone" class="h-5 w-5" />
                상황실 전화 ({{ $controlTel }})
            </x-ui.button>
            <x-ui.button :href="route('dispatches.index')" variant="ghost">지령 화면으로</x-ui.button>
        </div>
    </div>
</x-layouts.app>
