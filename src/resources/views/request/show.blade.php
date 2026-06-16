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

    <div id="app" class="w-full h-screen relative flex flex-col" style="padding-bottom: 280px">
        <map-loader @scripts-loaded="initMap"></map-loader>
        <intro-screen :show="showIntro" title="요청 위치 확인 중..."></intro-screen>
        
        <div class="flex-1 w-full map-container-shadow">
            <map-container ref="mapContainer"></map-container>
        </div>

        <div class="bg-white fixed left-0 bottom-0 right-0 p-5 z-[99] bottom-sheet border-t border-gray-100">
            <div class="bottom-sheet-handle"></div>
            
            <location-button :loading="loading" @get-location="getMyLocation"></location-button>

            <div class="max-w-md mx-auto">
                <div class="grid grid-cols-2 gap-3 mb-4">
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

                <div class="grid grid-cols-2 gap-4">
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
            }
        })).mount('#app');
    </script>
</x-layouts.app>
