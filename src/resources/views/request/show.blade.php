<x-layouts.app>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <div id="app" class="w-full h-screen" style="padding-bottom: 200px">
        <map-loader @scripts-loaded="initMap"></map-loader>
        <intro-screen :show="showIntro" title="요청 위치 확인"></intro-screen>
        <map-container ref="mapContainer"></map-container>
        <div class="bg-white fixed left-0 bottom-0 right-0 p-4 md:p-6 z-[99] shadow-2xl border-t border-gray-200">
            <location-button :loading="loading" @get-location="getMyLocation"></location-button>

            <div class="grid grid-cols-2 gap-3">
                <location-info
                    :latitude="requestLat"
                    :longitude="requestLong"
                    :address="requestAddress"
                    title="요청 위치"
                    bg-color="red"
                    icon="location"
                ></location-info>
                <location-info
                    :latitude="myLat"
                    :longitude="myLong"
                    :address="myAddress"
                    title="내 위치"
                    bg-color="blue"
                    icon="clock"
                ></location-info>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <button class="bg-red-500 hover:bg-red-600 text-white p-3 rounded-xl shadow-lg transform transition-all duration-200 hover:scale-105 hover:shadow-xl active:scale-95" @click="showRequestLocation">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-1 w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <div class="text-sm font-medium">요청 위치</div>
                </button>
                <button class="bg-blue-500 hover:bg-blue-600 text-white p-3 rounded-xl shadow-lg transform transition-all duration-200 hover:scale-105 hover:shadow-xl active:scale-95" @click="showMyLocation">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-1 w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm font-medium">내 위치</div>
                </button>
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
