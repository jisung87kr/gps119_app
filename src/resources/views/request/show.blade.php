{{--
    신고 상태 추적 — 전체화면 지도 + 바텀시트. 시안이 없는 화면이라
    design-system.html 어휘로 파생했다(신고 화면과 같은 시트 구조).
--}}
<x-layouts.app title="GPS119 - 신고 상태" bare
              body-class="h-[100dvh] overflow-hidden overscroll-none">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    {{-- 다른 사용자 화면과 같은 폭(max-w-2xl 중앙 정렬) --}}
    <div id="app" class="mx-auto flex h-[100dvh] max-w-2xl flex-col overflow-hidden bg-ink-50">
        <map-loader v-on:scripts-loaded="initMap"></map-loader>
        <intro-screen :show="showIntro" title="요청 위치 확인 중..."></intro-screen>

        <x-ui.page-header heading="내 신고 상태" :back="route('dashboard')" class="shrink-0" />

        <div class="relative flex-1">
            <map-container ref="mapContainer"></map-container>

            {{-- 바깥 겹은 overflow 없음(위치 버튼이 시트 위로 떠야 함), 스크롤은 안쪽 --}}
            <div id="bottom-sheet"
                 class="absolute inset-x-0 z-20 transition-transform duration-300 ease-out"
                 {{-- 접힘 노출 높이 = 핸들 행(h-11=44px) + border-t(1px).
                     🔴 안전영역은 여기가 아니라 시트 «위치»(bottom)로 준다.
                        노출 높이에 더하면 시트가 올라오는 게 아니라 «더 많이 보여서»
                        핸들 아래 내용까지 드러나고, 그게 다시 내비바에 걸린다
                        (실기기에서 그렇게 나왔다, 2026-09-01). --}}
                 :class="sheetExpanded ? 'bottom-0 translate-y-0'
                              : 'bottom-[var(--safe-bottom)] translate-y-[calc(100%_-_2.8125rem)]'">

                <location-button :loading="loading" v-on:get-location="getMyLocation"></location-button>

                <div class="max-h-[70dvh] overflow-y-auto rounded-t-3xl border-t border-ink-200 bg-white pb-[var(--safe-bottom)] shadow-[0_-8px_24px_-8px_rgba(0,0,0,0.15)]">
                    <div class="flex h-11 cursor-pointer items-center justify-center" v-on:click="toggleSheet"
                         role="button" aria-label="패널 펼치기/접기">
                        <span class="h-1.5 w-12 rounded-full bg-ink-200"></span>
                    </div>

                    {{-- 진행 상태 (행사 신고만) --}}
                    <div v-if="isProjectRequest" class="px-5 pb-4 pt-1">
                        <div class="rounded-2xl border border-ink-200 bg-white p-5">
                            <div class="mb-4 flex items-center justify-between">
                                <h2 class="text-lg font-bold text-ink-950">진행 상태</h2>
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold"
                                      :class="statusWs==='ws' ? 'text-success-600' : (statusWs==='polling' ? 'text-warning-600' : 'text-ink-400')">
                                    <span class="h-1.5 w-1.5 rounded-full"
                                          :class="statusWs==='ws' ? 'bg-success-500' : (statusWs==='polling' ? 'bg-warning-500' : 'bg-ink-300')"></span>
                                    @{{ statusWs==='ws' ? '실시간' : (statusWs==='polling' ? '갱신 지연중' : '연결중') }}
                                </span>
                            </div>

                            <p v-if="isCancelled" class="py-2 text-base text-ink-500">요청이 취소되었습니다.</p>

                            {{-- 3단계 타임라인: 접수 → 구조 진행중 → 완료 --}}
                            <div v-else class="flex items-center">
                                <template v-for="(label, i) in ['접수','구조 진행중','완료']" :key="i">
                                    <div class="flex shrink-0 flex-col items-center">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                                              :class="i <= stageIndex ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-400'">
                                            <svg v-if="i < stageIndex" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                                 stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m5 13 4 4L19 7" />
                                            </svg>
                                            <span v-else>@{{ i+1 }}</span>
                                        </span>
                                        <span class="mt-1.5 text-xs font-bold"
                                              :class="i <= stageIndex ? 'text-ink-950' : 'text-ink-400'">@{{ label }}</span>
                                    </div>
                                    <span v-if="i < 2" class="mx-1 h-0.5 flex-1"
                                          :class="i < stageIndex ? 'bg-brand-600' : 'bg-ink-200'"></span>
                                </template>
                            </div>

                            {{-- 담당자 + 전화 (배정 전 상황실, 배정 후 담당자) --}}
                            <div class="mt-5 border-t border-ink-100 pt-4">
                                <p v-if="paramedicName" class="mb-3 text-base text-ink-700">
                                    담당 구급대원 <b class="font-bold text-ink-950">@{{ paramedicName }}</b>
                                </p>
                                <a :href="'tel:'+callPhone"
                                   class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand-600 py-4 text-base font-bold text-white shadow-sm active:bg-brand-700">
                                    <x-ui.icon name="phone" class="h-5 w-5" />
                                    @{{ callLabel }}
                                </a>
                                {{-- 취소: 배정 «전»에만 본인이 할 수 있다. 배정 후에는 대원이
                                     이미 이동 중이라 상황실 판단을 거친다(서버가 422로 막는다). --}}
                                <template v-if="canCancel">
                                    <button v-if="!confirmingCancel" type="button" @click="requestCancelConfirm"
                                            class="mt-2.5 w-full rounded-2xl border border-ink-200 bg-white py-3 text-sm font-bold text-ink-500 active:bg-ink-50">
                                        구조요청 취소
                                    </button>
                                    <div v-else class="mt-2.5 rounded-2xl border border-danger-200 bg-danger-50 p-4">
                                        <p class="text-sm font-bold text-danger-700">정말 취소할까요?</p>
                                        <p class="mt-1 text-sm text-danger-600">취소하면 되돌릴 수 없습니다.</p>
                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            <button type="button" @click="dismissCancelConfirm" :disabled="cancelling"
                                                    class="rounded-xl border border-ink-200 bg-white py-3 text-sm font-bold text-ink-600 active:bg-ink-50 disabled:opacity-50">
                                                아니오
                                            </button>
                                            <button type="button" @click="submitCancel" :disabled="cancelling"
                                                    class="rounded-xl bg-danger-600 py-3 text-sm font-bold text-white active:bg-danger-700 disabled:opacity-50">
                                                @{{ cancelling ? '취소 중...' : '네, 취소합니다' }}
                                            </button>
                                        </div>
                                    </div>
                                    <p v-if="cancelError" class="mt-2 text-center text-sm font-bold text-danger-600">@{{ cancelError }}</p>
                                </template>
                                <p v-else class="mt-2.5 text-center text-sm text-ink-400">
                                    취소가 필요하면 상황실로 전화해 주세요.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- 위치 두 곳: 요청지(danger 핀) / 내 위치(brand 핀) --}}
                    <div class="divide-y divide-ink-100 border-y border-ink-100">
                        <location-info :latitude="requestLat" :longitude="requestLong" :address="requestAddress"
                                       title="구조 요청지" tone="danger"></location-info>
                        <location-info :latitude="myLat" :longitude="myLong" :address="myAddress"
                                       title="내 현재 위치" tone="brand"></location-info>
                    </div>

                    <div class="grid grid-cols-2 gap-3 px-5 pb-5 pt-4">
                        <x-ui.button variant="secondary" vue-click="showRequestLocation">
                            <x-ui.icon name="pin" class="h-5 w-5 text-danger-600" />
                            요청지 보기
                        </x-ui.button>
                        <x-ui.button variant="secondary" vue-click="showMyLocation">
                            <x-ui.icon name="pin" class="h-5 w-5 text-brand-600" />
                            내 위치 보기
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="module">
        import createRequestShowApp from '/js/components/RequestShowApp.js';

        const { createApp } = Vue;

        createApp(createRequestShowApp({
            request: {
                latitude:  '{{ $request->latitude ?? "33.450701" }}',
                longitude: '{{ $request->longitude ?? "126.570667" }}',
                address:   '{{ $request->address ?? "요청 위치를 확인 중입니다..." }}',
                id: {{ $request->id }},
                status: @json($request->status->value),
                projectId: {{ $request->project_id ?? 'null' }},
                userId: {{ $request->user_id }},
                paramedicName: @json(optional(optional($request->activeDispatch)->paramedic)->name),
                paramedicPhone: @json(optional(optional($request->activeDispatch)->paramedic)->phone),
                controlTel: @json(data_get(optional($request->project)->settings, 'emergency_tel', '010-4794-0119')),
            }
        })).mount('#app');
    </script>
</x-layouts.app>
