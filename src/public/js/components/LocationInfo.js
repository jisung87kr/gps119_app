// 현재 발송 위치 표시 — src/tmp/dispatch.html 바텀시트 상단 기준.
// 카드로 감싸지 않고 "라벨 + 주소" 두 줄로 얇게 둔다. 좌표는 GPS 정확도를
// 확인할 수 있어야 하는 도메인이라 보조 텍스트로 남긴다.
export default {
    name: 'LocationInfo',
    props: {
        latitude: {
            type: [String, Number],
            default: ''
        },
        longitude: {
            type: [String, Number],
            default: ''
        },
        address: {
            type: String,
            default: ''
        },
        title: {
            type: String,
            default: '위치 정보'
        },
        // 핀 아이콘 색. 구조 요청지처럼 긴급 대상 위치는 danger, 그 외는 brand.
        tone: {
            type: String,
            default: 'brand'
        }
    },
    computed: {
        hasCoords() {
            return this.latitude !== '' && this.longitude !== '';
        },

        // 🔑 소수점 5자리 ≈ 1m. 원본은 14자리까지 찍혀서 시트에서 «읽을 수 없는 숫자»가
        //    한 줄을 통째로 먹었다. 좌표를 남기는 이유는 「위치가 잡혔다」는 신호와
        //    GPS 정확도 확인이지, 값을 정밀하게 읽으라는 게 아니다.
        //    (신고에 저장·전송되는 값은 그대로 원본이다 — 여기는 표시만 줄인다.)
        shortCoords() {
            const round = (v) => Number.parseFloat(v).toFixed(5);

            return `${round(this.latitude)}, ${round(this.longitude)}`;
        },
        pinClass() {
            return this.tone === 'danger' ? 'text-danger-600' : 'text-brand-600';
        }
    },
    template: `
        <div class="px-5 pb-2 pt-1">
            <p class="flex items-center gap-1.5 text-sm font-bold text-ink-400">
                <svg class="h-[15px] w-[15px]" :class="pinClass" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z" />
                </svg>
                {{ title }}
            </p>

            <p v-if="address" class="mt-1 break-keep text-lg font-extrabold leading-snug text-ink-950">
                {{ address }}
            </p>
            <p v-else class="mt-1 text-lg font-extrabold leading-snug text-ink-400">
                위치 정보를 가져오는 중…
            </p>

            <p v-if="hasCoords" class="mt-1 font-mono text-xs text-ink-400">
                {{ shortCoords }}
            </p>
        </div>
    `
};
