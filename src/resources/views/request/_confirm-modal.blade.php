{{--
    주소확인 모달 + 접수완료 (반드시 #app 안에서 include — Vue 마운트 루트 내부).
    시안이 없는 화면이라 design-system.html 어휘로 파생했다.
--}}

{{-- "이 위치가 맞습니까?" --}}
<div v-if="confirmOpen" class="fixed inset-0 z-[120] flex items-end justify-center bg-ink-950/40 sm:items-center"
     v-on:click.self="closeConfirm">
    <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white sm:max-w-md sm:rounded-3xl">
        <div class="flex h-16 items-center justify-between border-b border-ink-200 px-5">
            <h2 class="text-lg font-extrabold text-ink-950">이 위치가 맞습니까?</h2>
            <button type="button" v-on:click="closeConfirm" aria-label="닫기"
                    class="-mr-2 flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-ink-400 active:bg-ink-100">
                <x-ui.icon name="x-circle" class="h-6 w-6" />
            </button>
        </div>

        <div class="p-5">
            {{-- 지도 미리보기 --}}
            <div id="confirm-map" class="mb-4 h-40 w-full overflow-hidden rounded-2xl bg-ink-100"></div>

            {{-- 핵심: 주소 확인 --}}
            <p class="flex items-center gap-1.5 text-sm font-bold text-ink-400">
                <x-ui.icon name="pin" class="h-[15px] w-[15px] text-brand-600" />
                신고될 위치
            </p>
            <p class="mt-1 break-keep text-lg font-extrabold leading-snug text-ink-950">
                @{{ address || '주소를 확인하지 못했습니다 (좌표로 신고됩니다)' }}
            </p>
            <p class="mt-1 font-mono text-xs text-ink-400">
                @{{ Number(lat).toFixed(5) }}, @{{ Number(long).toFixed(5) }}
            </p>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <x-ui.button variant="secondary" vue-click="execDaumPostcode">주소 다시 검색</x-ui.button>
                <x-ui.button variant="secondary" vue-click="correctOnMap">지도에서 보정</x-ui.button>
            </div>

            <x-ui.alert v-if="submitError" tone="danger" class="mt-4">@{{ submitError }}</x-ui.alert>

            <div class="mt-4 space-y-2.5">
                {{--
                    신고 확정은 이 화면에서 가장 강한 액션.
                    :disabled 를 Vue 로 묶어야 해서 x-ui.button 대신 클래스를 직접 쓴다
                    (컴포넌트의 disabled 는 서버 렌더 시점 값이라 submitting 을 못 받는다).
                --}}
                <button type="button" v-on:click="confirmSubmit" :disabled="submitting"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-danger-600 py-4 text-base font-bold text-white shadow-sm transition-colors active:bg-danger-700 disabled:cursor-not-allowed disabled:bg-ink-100 disabled:text-ink-400 disabled:shadow-none">
                    @{{ submitting ? '전송 중…' : (typeLabel(requestType) + ' 신고') }}
                </button>
                <x-ui.button variant="secondary" vue-click="closeConfirm">취소</x-ui.button>
            </div>
        </div>
    </div>
</div>

{{-- 접수 완료 --}}
<div v-if="successOpen" class="fixed inset-0 z-[120] flex items-center justify-center bg-ink-950/40 px-5">
    <div class="w-full max-w-sm rounded-3xl bg-white p-6 text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success-50 text-success-600">
            <x-ui.icon name="check-circle" class="h-8 w-8" />
        </span>
        <h2 class="mt-4 text-lg font-extrabold text-ink-950">신고가 접수되었습니다</h2>
        <p class="mt-1 text-base text-ink-500">구조대가 위치를 확인하고 있습니다.</p>

        {{-- href 를 Vue 로 묶어야 해서 x-ui.button 대신 클래스를 직접 쓴다 --}}
        <div class="mt-6 space-y-2.5">
            <a v-if="createdRequestId" :href="`/requests/${createdRequestId}`"
               class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-600 py-4 text-base font-bold text-white shadow-sm active:bg-brand-700">
                내 신고 상태 보기
            </a>
            <a :href="'tel:'+emergencyTel"
               class="inline-flex w-full items-center justify-center rounded-2xl border-2 border-ink-200 bg-white py-4 text-base font-bold text-ink-900 active:bg-ink-50">
                상황실 전화
            </a>
        </div>
    </div>
</div>
