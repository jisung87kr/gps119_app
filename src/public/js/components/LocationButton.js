// 현재 위치 재조회 버튼 — src/tmp/dispatch.html 의 지도 위 플로팅 버튼 기준.
// 흰 원 + brand-600 아이콘 + ink-200 링. 바텀시트 위쪽에 떠 있다.
export default {
    name: 'LocationButton',
    props: {
        loading: {
            type: Boolean,
            default: false
        }
    },
    methods: {
        handleClick() {
            this.$emit('get-location');
        }
    },
    template: `
        <button
            type="button"
            @click="handleClick"
            :disabled="loading"
            aria-label="현재 위치 다시 가져오기"
            class="absolute right-4 top-[-60px] z-30 flex h-12 w-12 items-center justify-center rounded-full bg-white text-brand-600 shadow-lg ring-1 ring-ink-200 transition-colors active:bg-ink-50"
        >
            <svg
                v-if="!loading"
                class="h-[22px] w-[22px]"
                viewBox="0 0 24 24"
                fill="currentColor"
                aria-hidden="true"
            >
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z" />
            </svg>

            <svg
                v-else
                class="h-[22px] w-[22px] animate-spin"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                aria-hidden="true"
            >
                <path d="M12 3a9 9 0 1 0 9 9" />
            </svg>
        </button>
    `
};
