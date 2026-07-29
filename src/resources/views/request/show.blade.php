<x-layouts.app>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        .bottom-sheet {
            border-top-left-radius: 1.5rem;
            border-top-right-radius: 1.5rem;
            box-shadow: 0 -10px 25px -5px rgba(0, 0, 0, 0.1), 0 -8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .bottom-sheet-handle {
            width: 40px;
            height: 4px;
            background-color: #e5e7eb;
            border-radius: 2px;
            margin: 0 auto 1rem auto;
        }
        .nav-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .nav-btn:active {
            transform: scale(0.95);
        }
        .map-container-shadow {
            box-shadow: inset 0 0 20px rgba(0,0,0,0.05);
        }
    </style>

    <div id="app" class="w-full h-[calc(100dvh-3.5rem)] relative">
        <map-loader @scripts-loaded="initMap"></map-loader>
        <intro-screen :show="showIntro" title="요청 위치 확인 중..."></intro-screen>

        <!-- 지도가 화면 전체를 채우고 시트가 그 위에 떠 있음 (접으면 지도가 드러남) -->
        <div class="absolute inset-0 map-container-shadow">
            <map-container ref="mapContainer"></map-container>
        </div>

        <div id="bottom-sheet" class="bg-white fixed left-0 bottom-0 right-0 p-5 z-[99] bottom-sheet border-t border-gray-100 transition-transform duration-300 ease-out"
             :class="sheetExpanded ? 'translate-y-0' : 'translate-y-[calc(100%_-_2.75rem)]'">
            <div class="flex justify-center cursor-pointer -mt-2 pt-2 pb-1" @click="toggleSheet" role="button" aria-label="패널 펼치기/접기">
                <div class="bottom-sheet-handle !mb-0"></div>
            </div>

            <location-button :loading="loading" @get-location="getMyLocation"></location-button>

            <!-- FE-3.4 신고자 상태추적 (행사 신고만) -->
            <div v-if="isProjectRequest" class="max-w-7xl w-full mx-auto mb-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-slate-800">내 신고 상태</h3>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-medium"
                              :class="statusWs==='ws' ? 'text-green-600' : (statusWs==='polling' ? 'text-amber-600' : 'text-gray-400')">
                            <span class="w-1.5 h-1.5 rounded-full" :class="statusWs==='ws' ? 'bg-green-500' : (statusWs==='polling' ? 'bg-amber-500' : 'bg-gray-400')"></span>
                            @{{ statusWs==='ws' ? '실시간' : (statusWs==='polling' ? '갱신 지연중' : '연결중') }}
                        </span>
                    </div>

                    <!-- 취소 -->
                    <div v-if="isCancelled" class="text-sm text-slate-500 py-2">요청이 취소되었습니다.</div>

                    <!-- 3단계 타임라인: 접수 → 구조 진행중 → 완료 -->
                    <div v-else class="flex items-center">
                        <template v-for="(label, i) in ['접수','구조 진행중','완료']" :key="i">
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                                     :class="i <= stageIndex ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-400'">
                                    <span v-if="i < stageIndex">✓</span><span v-else>@{{ i+1 }}</span>
                                </div>
                                <span class="text-[11px] mt-1" :class="i <= stageIndex ? 'text-slate-800 font-semibold' : 'text-slate-400'">@{{ label }}</span>
                            </div>
                            <div v-if="i < 2" class="flex-1 h-0.5 mx-1" :class="i < stageIndex ? 'bg-blue-600' : 'bg-slate-200'"></div>
                        </template>
                    </div>

                    <!-- 담당자 + 전화 (배정 전 상황실, 배정 후 담당자) -->
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <p v-if="paramedicName" class="text-sm text-slate-700 mb-2">담당 구급대원: <b>@{{ paramedicName }}</b></p>
                        <a :href="'tel:'+callPhone" class="block w-full text-center py-3 text-sm font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700">
                            📞 @{{ callLabel }}
                        </a>
                        <p class="text-[11px] text-slate-400 text-center mt-2">취소가 필요하면 상황실로 전화해 주세요.</p>
                    </div>
                </div>
            </div>

            <div class="max-w-7xl w-full mx-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <location-info
                        :latitude="requestLat"
                        :longitude="requestLong"
                        :address="requestAddress"
                        title="구조 요청지"
                        bg-color="red"
                        icon="location"
                        class="mb-0"
                    ></location-info>
                    <location-info
                        :latitude="myLat"
                        :longitude="myLong"
                        :address="myAddress"
                        title="내 현재 위치"
                        bg-color="blue"
                        icon="clock"
                        class="mb-0"
                    ></location-info>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:gap-4">
                    <button class="nav-btn group bg-white border-2 border-red-500 text-red-600 p-3.5 rounded-2xl shadow-sm flex items-center justify-center gap-2 hover:bg-red-50" @click="showRequestLocation">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="text-sm font-bold">요청지 보기</span>
                    </button>
                    <button class="nav-btn group bg-white border-2 border-blue-500 text-blue-600 p-3.5 rounded-2xl shadow-sm flex items-center justify-center gap-2 hover:bg-blue-50" @click="showMyLocation">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm font-bold">내 위치 보기</span>
                    </button>
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
