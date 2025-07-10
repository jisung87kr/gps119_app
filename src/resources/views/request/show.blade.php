<x-layouts.app>
    <script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=509c2656c00fa9af4782197a888763f6&libraries=services,clusterer,drawing?autoload=false"></script>
    <div class="w-full h-screen" x-data="showData" style="padding-bottom: 200px">
        <div class="bg-blue-800 fixed left-0 top-0 right-0 bottom-0 z-[9999] flex items-center justify-center"
             id="intro"
             x-show="showIntro"
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-105"
        >
            <div class="w-full text-center text-white px-8">
                <div class="mb-6">
                    <div class="w-20 h-20 mx-auto mb-4 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>
                <div class="text-3xl md:text-4xl font-bold mb-2 tracking-tight">GPS119</div>
                <div class="text-xl md:text-2xl font-light mb-4 opacity-90">(주)바른인명구조단</div>
                <div class="text-sm md:text-base opacity-75">요청 위치 확인</div>
                <div class="mt-8">
                    <div class="w-8 h-8 mx-auto border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                </div>
            </div>
        </div>
        <div id="map"
             x-ref="map"
             class="w-full h-full"></div>
        <div class="bg-white fixed left-0 bottom-0 right-0 p-4 md:p-6 z-[99] shadow-2xl border-t border-gray-200">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 absolute right-4 top-[-60px] rounded-2xl cursor-pointer p-3 shadow-lg transform transition-all duration-200 hover:scale-105 hover:shadow-xl" @click.prevent="getMyLocation">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="icon icon-tabler icon-tabler-focus-2" width="28" height="28" viewBox="0 0 24 24" stroke-width="2" stroke="#ffffff" fill="none" stroke-linecap="round" stroke-linejoin="round"
                     x-show="loading == false">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <circle cx="12" cy="12" r=".5" fill="currentColor" />
                    <path d="M12 12m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                    <path d="M12 3l0 2" />
                    <path d="M3 12l2 0" />
                    <path d="M12 19l0 2" />
                    <path d="M19 12l2 0" />
                </svg>

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="icon icon-tabler icon-tabler-loader-2 animate-spin" width="28" height="28" viewBox="0 0 24 24" stroke-width="2" stroke="#ffffff" fill="none" stroke-linecap="round" stroke-linejoin="round"
                     x-show="loading == true">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M12 3a9 9 0 1 0 9 9" />
                </svg>
            </div>

            <!-- 요청 위치 정보 -->
            <div class="bg-red-50 rounded-xl p-4 mb-4 border border-red-200">
                <div class="flex items-center gap-2 mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <h3 class="text-sm font-bold text-red-800">요청 위치</h3>
                </div>
                <div class="text-sm text-red-700 mb-2" x-text="requestAddress"></div>
                <div class="flex gap-4 text-xs text-red-600">
                    <div class="flex items-center gap-1">
                        <span class="font-medium">위도:</span>
                        <span x-text="requestLat" class="font-mono"></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="font-medium">경도:</span>
                        <span x-text="requestLong" class="font-mono"></span>
                    </div>
                </div>
            </div>

            <!-- 내 위치 정보 -->
            <div class="bg-blue-50 rounded-xl p-4 mb-4 border border-blue-200">
                <div class="flex items-center gap-2 mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-sm font-bold text-blue-800">내 위치</h3>
                </div>
                <div class="text-sm text-blue-700 mb-2" x-text="myAddress"></div>
                <div class="flex gap-4 text-xs text-blue-600">
                    <div class="flex items-center gap-1">
                        <span class="font-medium">위도:</span>
                        <span x-text="myLat" class="font-mono"></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="font-medium">경도:</span>
                        <span x-text="myLong" class="font-mono"></span>
                    </div>
                </div>
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
    <script>
        const showData = {
            requestLat: '{{ $request->latitude ?? "33.450701" }}',
            requestLong: '{{ $request->longitude ?? "126.570667" }}',
            requestAddress: '{{ $request->address ?? "요청 위치를 확인 중입니다..." }}',
            myLat: '33.450701',
            myLong: '126.570667',
            myAddress: '현재 위치를 확인 중입니다...',
            mapObject: null,
            requestMarker: null,
            myMarker: null,
            showIntro: true,
            loading: false,
            init(){
                this.initMap();
                this.getMyLocation();
                setTimeout(() => {
                    this.showIntro = false;
                }, 1000);
            },
            initMap(){
                this.mapObject = new daum.maps.Map(this.$refs.map, {
                    center: new daum.maps.LatLng(this.requestLat, this.requestLong),
                    level: 5,
                });

                // 요청 위치 마커 (빨간색)
                this.requestMarker = new daum.maps.Marker({
                    position: new daum.maps.LatLng(this.requestLat, this.requestLong),
                    map: this.mapObject,
                    image: new daum.maps.MarkerImage(
                        'data:image/svg+xml;base64,' + btoa(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="#dc2626">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                        `),
                        new daum.maps.Size(32, 32),
                        { offset: new daum.maps.Point(16, 32) }
                    )
                });

                // 내 위치 마커 (파란색) - 초기에는 표시하지 않음
                this.myMarker = new daum.maps.Marker({
                    position: new daum.maps.LatLng(this.myLat, this.myLong),
                    image: new daum.maps.MarkerImage(
                        'data:image/svg+xml;base64,' + btoa(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="#2563eb">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                        `),
                        new daum.maps.Size(32, 32),
                        { offset: new daum.maps.Point(16, 32) }
                    )
                });

                // 컨트롤러
                let mapTypeControl = new kakao.maps.MapTypeControl();
                this.mapObject.addControl(mapTypeControl, kakao.maps.ControlPosition.TOPRIGHT);

                // 줌
                let zoomControl = new kakao.maps.ZoomControl();
                this.mapObject.addControl(zoomControl, kakao.maps.ControlPosition.RIGHT);

                this.setBounds();
            },
            getMyLocation() {
                this.loading = true;
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition((position) => {
                        this.myLat = position.coords.latitude;
                        this.myLong = position.coords.longitude;
                        this.myMarker.setPosition(new daum.maps.LatLng(this.myLat, this.myLong));
                        this.myMarker.setMap(this.mapObject);
                        this.getAddressFromCoords(this.myLong, this.myLat, 'my');
                        this.loading = false;
                        this.setBounds();
                    }, (error) => {
                        this.showError(error);
                        this.loading = false;
                    });
                } else {
                    alert("지원하지 않는 브라우저 입니다.");
                    this.loading = false;
                }
            },
            getAddressFromCoords(long, lat, type) {
                let geocoder = new daum.maps.services.Geocoder();
                geocoder.coord2Address(long, lat, (result, status) => {
                    if (status === kakao.maps.services.Status.OK) {
                        const address = result[0].road_address && result[0].road_address.address_name ?
                            result[0].road_address.address_name : result[0].address.address_name;
                        if (type === 'my') {
                            this.myAddress = address;
                        }
                    }
                });
            },
            showRequestLocation() {
                this.mapObject.setCenter(new daum.maps.LatLng(this.requestLat, this.requestLong));
                this.mapObject.setLevel(3);
            },
            showMyLocation() {
                if (this.myLat && this.myLong) {
                    this.mapObject.setCenter(new daum.maps.LatLng(this.myLat, this.myLong));
                    this.mapObject.setLevel(3);
                }
            },
            showError(error) {
                let message = '';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message = "사용자가 위치 정보 요청을 거부했습니다.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = "위치 정보를 사용할 수 없습니다.";
                        break;
                    case error.TIMEOUT:
                        message = "사용자 위치 정보를 가져오는 요청이 시간 초과되었습니다.";
                        break;
                    case error.UNKNOWN_ERROR:
                        message = "알 수 없는 오류가 발생했습니다.";
                        break;
                }
                alert(message);
            },
            setBounds() {
                let markers = [this.requestMarker, this.myMarker];
                let bounds = new kakao.maps.LatLngBounds();
                for (let i = 0; i < markers.length; i++) {
                    bounds.extend(markers[i].getPosition());
                }
                this.mapObject.setBounds(bounds);
            },
        }
    </script>
</x-layouts.app>
