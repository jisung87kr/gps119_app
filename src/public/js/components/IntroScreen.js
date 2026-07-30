// 진입 스플래시 — 지도/스크립트 로딩 동안 노출. 레퍼런스에는 없는 화면이라
// design-system.html 의 로고 마크(brand-600 + 번개)와 타이포로 파생했다.
export default {
    name: 'IntroScreen',
    props: {
        show: {
            type: Boolean,
            default: true
        },
        title: {
            type: String,
            default: '응급상황 위치공유 서비스'
        }
    },
    template: `
        <transition
            enter-active-class="transition ease-out duration-500"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-700"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-[9999] flex items-center justify-center overflow-hidden bg-brand-600"
            >
                <div class="relative z-10 w-full px-8 text-center text-white">
                    <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-white/15 ring-1 ring-white/25">
                        <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z" />
                        </svg>
                    </span>

                    <h1 class="mt-6 text-[32px] font-extrabold tracking-tight">
                        GPS<span class="text-brand-200">119</span>
                    </h1>
                    <p class="mt-1.5 text-base font-medium text-white/80">(주)바른인명구조단</p>

                    <span class="mx-auto mt-5 block h-0.5 w-10 rounded-full bg-white/30"></span>

                    <p class="mt-5 text-sm font-medium text-white/70">{{ title }}</p>

                    <div class="mt-10 flex justify-center gap-1.5">
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-white" style="animation-delay: 0s"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-white" style="animation-delay: 0.1s"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-white" style="animation-delay: 0.2s"></span>
                    </div>
                </div>
            </div>
        </transition>
    `
};
