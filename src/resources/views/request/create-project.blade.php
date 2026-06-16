<x-layouts.app>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>


    <div id="app" class="w-full h-screen" style="padding-bottom: 200px; padding-top: 0;">
        <map-loader @scripts-loaded="initMap"></map-loader>
        <intro-screen :show="showIntro" title="응급상황 위치공유 서비스"></intro-screen>
        <map-container ref="mapContainer"></map-container>
        <div class="bg-white fixed left-0 bottom-0 right-0 p-4 md:p-6 z-[99] shadow-2xl border-t border-gray-200">
            <location-button :loading="loading" @get-location="getLocation"></location-button>
            <location-info
                :latitude="lat"
                :longitude="long"
                :address="address"
                title="현재 위치"
                bg-color="gray"
            ></location-info>
            <div>
                <div class="mb-4">
                    <input type="text" placeholder="📍 위치검색 (클릭하여 주소 찾기)"
                           class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 mb-3 focus:border-blue-500 focus:outline-none transition-colors duration-200 bg-white shadow-sm"
                           id="address"
                           name="address"
                           readonly
                           v-model="address"
                           @click="execDaumPostcode">
                    <div class="col-span-12 relative border-2 border-gray-200 pt-6 bg-white overflow-auto max-h-[400px] rounded-xl shadow-lg" v-show="findAddress">
                        <div ref="search_address_element">
                            <img src="//t1.daumcdn.net/postcode/resource/images/close.png" id="btnFoldWrap" style="cursor:pointer;position:absolute;right:0px;top:-1px;z-index:1" @click="findAddress=false" alt="접기 버튼">
                        </div>
                    </div>
                </div>
                <div class="w-full">
                    <div class="grid grid-cols-4 gap-3">
                        <div class="text-center">
                            <button class="bg-red-500 hover:bg-red-600 w-full text-center p-3 md:p-4 rounded-2xl shadow-lg transform transition-all duration-200 hover:scale-105 hover:shadow-xl active:scale-95" @click="shareLocation('accident')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto icon icon-tabler icon-tabler-alert-hexagon" width="32" height="32" viewBox="0 0 24 24" stroke-width="2" stroke="#ffffff" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M19.875 6.27c.7 .398 1.13 1.143 1.125 1.948v7.284c0 .809 -.443 1.555 -1.158 1.948l-6.75 4.27a2.269 2.269 0 0 1 -2.184 0l-6.75 -4.27a2.225 2.225 0 0 1 -1.158 -1.948v-7.285c0 -.809 .443 -1.554 1.158 -1.947l6.75 -3.98a2.33 2.33 0 0 1 2.25 0l6.75 3.98h-.033z" />
                                    <path d="M12 8v4" />
                                    <path d="M12 16h.01" />
                                </svg>
                            </button>
                            <div class="mt-2 font-bold text-red-600 text-sm">사고</div>
                        </div>
                        <div class="text-center">
                            <button class="bg-amber-500 hover:bg-amber-600 w-full text-center p-3 md:p-4 rounded-2xl shadow-lg transform transition-all duration-200 hover:scale-105 hover:shadow-xl active:scale-95" @click="shareLocation('fault')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto icon icon-tabler icon-tabler-forbid-2" width="32" height="32" viewBox="0 0 24 24" stroke-width="2" stroke="#ffffff" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                    <path d="M9 15l6 -6" />
                                </svg>
                            </button>
                            <div class="mt-2 font-bold text-amber-600 text-sm">고장</div>
                        </div>
                        <div class="text-center">
                            <button class="bg-slate-600 hover:bg-slate-700 w-full text-center p-3 md:p-4 rounded-2xl shadow-lg transform transition-all duration-200 hover:scale-105 hover:shadow-xl active:scale-95" @click="shareLocation('other')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto icon icon-tabler icon-tabler-message-circle-2" width="32" height="32" viewBox="0 0 24 24" stroke-width="2" stroke="#ffffff" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M3 20l1.3 -3.9a9 8 0 1 1 3.4 2.9l-4.7 1" />
                                </svg>
                            </button>
                            <div class="mt-2 font-bold text-slate-600 text-sm">기타</div>
                        </div>
                        <div class="text-center">
                            <a href="tel:010-4794-0119" class="bg-red-600 hover:bg-red-700 w-full text-center p-3 md:p-4 block rounded-2xl shadow-lg transform transition-all duration-200 hover:scale-105 hover:shadow-xl active:scale-95 emergency-glow button-pulse">
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto icon icon-tabler icon-tabler-phone-call" width="32" height="32" viewBox="0 0 24 24" stroke-width="2" stroke="#ffffff" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2" />
                                    <path d="M15 7a2 2 0 0 1 2 2" />
                                    <path d="M15 3a6 6 0 0 1 6 6" />
                                </svg>
                            </a>
                            <div class="mt-2 font-bold text-red-700 text-sm">긴급전화</div>
                        </div>
                    </div>
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
