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

    <div id="app" v-cloak class="w-full h-screen relative flex flex-col" style="padding-bottom: 340px">
        <map-loader @scripts-loaded="initMap"></map-loader>
        <intro-screen :show="showIntro" title="응급상황 위치공유 서비스"></intro-screen>
        
        <div class="flex-1 w-full map-container-shadow">
            <map-container ref="mapContainer"></map-container>
        </div>

        <div class="bg-white fixed left-0 bottom-0 right-0 p-5 pb-[calc(1.25rem+var(--safe-bottom))] z-[99] bottom-sheet border-t border-gray-100">
            <div class="bottom-sheet-handle"></div>
            
            <location-button :loading="loading" @get-location="getLocation"></location-button>
            
            <div class="max-w-md mx-auto">
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
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
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
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div ref="search_address_element" class="w-full"></div>
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-3">
                    <div class="flex flex-col items-center">
                        <button class="emergency-btn bg-red-500 hover:bg-red-600 w-full aspect-square flex items-center justify-center rounded-2xl shadow-lg shadow-red-200" @click="shareLocation('accident')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </button>
                        <span class="mt-2.5 font-bold text-red-600 text-xs tracking-tight">사고발생</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <button class="emergency-btn bg-amber-500 hover:bg-amber-600 w-full aspect-square flex items-center justify-center rounded-2xl shadow-lg shadow-amber-200" @click="shareLocation('fault')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                            </svg>
                        </button>
                        <span class="mt-2.5 font-bold text-amber-600 text-xs tracking-tight">차량고장</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <button class="emergency-btn bg-slate-600 hover:bg-slate-700 w-full aspect-square flex items-center justify-center rounded-2xl shadow-lg shadow-slate-200" @click="shareLocation('other')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                        </button>
                        <span class="mt-2.5 font-bold text-slate-600 text-xs tracking-tight">기타문의</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <a href="tel:010-4794-0119" class="emergency-btn bg-red-600 hover:bg-red-700 w-full aspect-square flex items-center justify-center rounded-2xl shadow-xl shadow-red-300 ring-4 ring-red-50 animate-pulse">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </a>
                        <span class="mt-2.5 font-black text-red-700 text-xs tracking-tight">긴급전화</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="module">
        import createRequestMapApp from '/js/components/RequestMapApp.js';

        const { createApp } = Vue;

        createApp(createRequestMapApp()).mount('#app');
    </script>
</x-layouts.app>
