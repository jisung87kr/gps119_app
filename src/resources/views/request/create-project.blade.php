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
        .emergency-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .emergency-btn:active {
            transform: scale(0.92);
        }
        .map-container-shadow {
            box-shadow: inset 0 0 20px rgba(0,0,0,0.05);
        }
    </style>

    <div id="app" class="w-full h-[calc(100dvh-3.5rem)] relative">
        <map-loader @scripts-loaded="initMap"></map-loader>
        <intro-screen :show="showIntro" title="응급상황 위치공유 서비스"></intro-screen>

        <!-- Project Badge -->
        <div class="absolute top-4 left-4 z-[50] flex items-center">
            <div class="bg-blue-600 text-white px-4 py-2 rounded-full shadow-lg flex items-center gap-2 border border-blue-400">
                <div class="w-2 h-2 bg-blue-200 rounded-full animate-pulse"></div>
                <span class="text-xs font-bold uppercase tracking-widest">{{ $project->name }}</span>
            </div>
        </div>

        <!-- 지도는 전체 화면을 채우고 시트가 그 위에 떠 있음 (접으면 지도가 드러남) -->
        <div class="absolute inset-0 map-container-shadow">
            <map-container ref="mapContainer"></map-container>
        </div>

        <div id="bottom-sheet" class="bg-white fixed left-0 bottom-0 right-0 p-5 z-[99] bottom-sheet border-t border-gray-100 transition-transform duration-300 ease-out"
             :class="sheetExpanded ? 'translate-y-0' : 'translate-y-[calc(100%_-_2.75rem)]'">
            <div class="flex justify-center cursor-pointer -mt-2 pt-2 pb-1" @click="toggleSheet" role="button" aria-label="패널 펼치기/접기">
                <div class="bottom-sheet-handle !mb-0"></div>
            </div>

            <location-button :loading="loading" @get-location="getLocation"></location-button>
            
            <div class="max-w-7xl w-full mx-auto">
                <div class="mb-2 px-1">
                    <h1 class="text-lg font-bold text-gray-900 leading-tight">{{ $project->name }}</h1>
                    <p class="text-xs text-gray-500 mt-1">{{ $project->description ?? '위치를 공유하여 구조를 요청하세요.' }}</p>
                </div>

                <location-info
                    :latitude="lat"
                    :longitude="long"
                    :address="address"
                    title="현재 발송 위치"
                    bg-color="gray"
                ></location-info>

                <div class="mb-5">
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-blue-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" placeholder="다른 위치로 검색하려면 클릭하세요"
                               class="w-full border border-gray-200 rounded-2xl pl-11 pr-4 py-3.5 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:outline-none transition-all duration-200 bg-gray-50/50 shadow-sm text-sm font-medium"
                               id="address"
                               name="address"
                               readonly
                               v-model="address"
                               @click="execDaumPostcode">
                    </div>
                    
                    <div class="fixed inset-0 z-[100] bg-white overflow-auto p-4" v-show="findAddress">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b">
                            <h2 class="text-lg font-bold">주소 검색</h2>
                            <button @click="findAddress=false" class="p-2 hover:bg-gray-100 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div ref="search_address_element" class="w-full"></div>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-2">
                    <button class="emergency-btn bg-red-500 hover:bg-red-600 flex flex-col items-center justify-center gap-1 py-2.5 rounded-xl shadow-md shadow-red-200" @click="shareLocation('accident')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <span class="text-[11px] font-bold text-white tracking-tight">사고발생</span>
                    </button>
                    <button class="emergency-btn bg-amber-500 hover:bg-amber-600 flex flex-col items-center justify-center gap-1 py-2.5 rounded-xl shadow-md shadow-amber-200" @click="shareLocation('fault')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                        </svg>
                        <span class="text-[11px] font-bold text-white tracking-tight">차량고장</span>
                    </button>
                    <button class="emergency-btn bg-slate-600 hover:bg-slate-700 flex flex-col items-center justify-center gap-1 py-2.5 rounded-xl shadow-md shadow-slate-200" @click="shareLocation('other')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span class="text-[11px] font-bold text-white tracking-tight">기타문의</span>
                    </button>
                    <a href="tel:010-4794-0119" class="emergency-btn bg-red-600 hover:bg-red-700 flex flex-col items-center justify-center gap-1 py-2.5 rounded-xl shadow-md shadow-red-300 ring-2 ring-red-100 animate-pulse">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <span class="text-[11px] font-black text-white tracking-tight">긴급전화</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <script type="module">
        import createRequestMapApp from '/js/components/RequestMapApp.js';

        const { createApp } = Vue;

        createApp(createRequestMapApp({ projectId: {{ $project->id }} })).mount('#app');
    </script>
</x-layouts.app>
