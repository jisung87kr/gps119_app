<x-layouts.app>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    <!-- Project Info Banner -->
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-6 shadow-lg">
        <div class="max-w-md mx-auto">
            <div class="flex items-center gap-3 mb-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h2 class="text-xl font-bold">{{ $project->name }}</h2>
            </div>
            @if($project->description)
                <p class="text-blue-50 text-sm">{{ $project->description }}</p>
            @endif
            <div class="mt-3 flex items-center gap-2 text-sm text-blue-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>{{ $project->start_date->format('Y.m.d') }} - {{ $project->end_date->format('Y.m.d') }}</span>
            </div>
        </div>
    </div>

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
        import MapLoader from '/js/components/MapLoader.js';
        import IntroScreen from '/js/components/IntroScreen.js';
        import LocationButton from '/js/components/LocationButton.js';
        import LocationInfo from '/js/components/LocationInfo.js';
        import MapContainer from '/js/components/MapContainer.js';

        const { createApp } = Vue;

        createApp({
            components: {
                MapLoader,
                IntroScreen,
                LocationButton,
                LocationInfo,
                MapContainer
            },
            data() {
                return {
                    projectId: {{ $project->id }},
                    lat: '33.450701',
                    long: '126.570667',
                    mapObject: null,
                    marker: null,
                    addressPostcode: '',
                    address: '',
                    addressExtra: '',
                    findAddress: false,
                    infowindow: null,
                    showIntro: true,
                    loading: false
                }
            },
            mounted() {
                setTimeout(() => {
                    this.getLocation();
                    this.showIntro = false;
                }, 1000);
            },
            methods: {
            initMap(){
                const mapElement = document.getElementById('map');
                this.mapObject = new kakao.maps.Map(mapElement, {
                    center: new kakao.maps.LatLng(this.lat, this.long),
                    level: 5,
                });

                this.marker = new kakao.maps.Marker({
                    position: new kakao.maps.LatLng(this.lat, this.long),
                    map: this.mapObject
                });

                this.infowindow = new kakao.maps.InfoWindow({zindex:1});

                let mapTypeControl = new kakao.maps.MapTypeControl();
                this.mapObject.addControl(mapTypeControl, kakao.maps.ControlPosition.TOPRIGHT);

                let zoomControl = new kakao.maps.ZoomControl();
                this.mapObject.addControl(zoomControl, kakao.maps.ControlPosition.RIGHT);

                kakao.maps.event.addListener(this.mapObject, 'click', (mouseEvent) => {
                    this.addMarker(mouseEvent.latLng);
                    this.lat = mouseEvent.latLng.Ma;
                    this.long = mouseEvent.latLng.La;
                    this.latLongToAddress(this.long, this.lat);
                });

                this.latLongToAddress(this.long, this.lat);
            },
            latLongToAddress(long, lat){
                let geocoder = new kakao.maps.services.Geocoder();
                geocoder.coord2Address(long, lat, (result, status) => {
                    if (status === kakao.maps.services.Status.OK) {
                        this.address = result[0].road_address && result[0].road_address.address_name ? result[0].road_address.address_name : result[0].address.address_name;
                    }
                });
            },
            setMap(address){
                this.initMap();
                let geocoder = new kakao.maps.services.Geocoder();
                geocoder.addressSearch(address, (results, status) => {
                    if (status === kakao.maps.services.Status.OK) {
                        let result = results[0];
                        this.lat = result.y;
                        this.long = result.x;
                        let coords = new kakao.maps.LatLng(this.lat, this.long);
                        this.mapObject.relayout();
                        this.mapObject.setCenter(coords);
                        this.marker.setPosition(coords)
                    }
                });
            },
            addMarker(position){
                this.marker.setMap(null);
                this.marker = new kakao.maps.Marker({
                    position: position
                });
                this.marker.setMap(this.mapObject);
            },
            execDaumPostcode(){
                this.findAddress = true;
                var currentScroll = Math.max(document.body.scrollTop, document.documentElement.scrollTop);
                new daum.Postcode({
                    oncomplete: (data) => {
                        var addr = '';
                        var extraAddr = '';

                        if (data.userSelectedType === 'R') {
                            addr = data.roadAddress;
                        } else {
                            addr = data.jibunAddress;
                        }

                        if(data.userSelectedType === 'R'){
                            if(data.bname !== '' && /[동|로|가]$/g.test(data.bname)){
                                extraAddr += data.bname;
                            }
                            if(data.buildingName !== '' && data.apartment === 'Y'){
                                extraAddr += (extraAddr !== '' ? ', ' + data.buildingName : data.buildingName);
                            }
                            if(extraAddr !== ''){
                                extraAddr = ' (' + extraAddr + ')';
                            }
                            this.addressExtra = extraAddr;
                        } else {
                            this.addressExtra = '';
                        }

                        this.addressPostcode = data.zonecode;
                        this.address = addr;
                        this.findAddress = false;
                        document.body.scrollTop = currentScroll;
                        this.setMap(this.address);
                    },
                    onresize : (size) => {
                        this.$refs.search_address_element.style.height = size.height+'px';
                    },
                    width : '100%',
                    height : '100%'
                }).embed(this.$refs.search_address_element);
            },
            shareLocation(type){
                if(!confirm('위치공유를 하시겠습니까?')){
                    return false;
                }

                if(!this.address || !this.lat || !this.long){
                    alert('위치정보가 정확하지 않습니다. 다시 시도해주세요.');
                    return false;
                }

                var params = {
                    latitude: this.lat,
                    longitude: this.long,
                    address: this.address,
                    description: type,
                    project_id: this.projectId
                }

                axios.post('/api/requests', params).then(res => {
                    if(res.data.success){
                        alert('위치정보가 공유되었습니다.');
                    } else {
                        alert('위치정보 공유에 실패했습니다. 다시 시도해주세요.');
                    }
                }).catch(err => {
                    console.error(err);
                    alert('위치정보 공유에 실패했습니다. 다시 시도해주세요.');
                });
            },
            setCenter(lat, long) {
                let moveLatLon = new kakao.maps.LatLng(lat, long);
                this.mapObject.setCenter(moveLatLon);
            },
            getLocation() {
                this.loading = true;
                var options = {
                    enableHighAccuracy: true,
                    timeout: 5000,
                    maximumAge: 0
                };

                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition((position) => {
                        this.showPosition(position);
                    }, (error) => {
                        this.showError(error);
                        this.loading = false;
                    }, options);
                } else {
                    alert("지원하지 않는 브라우저 입니다.");
                }
            },
            showPosition(position) {
                this.lat = position.coords.latitude;
                this.long = position.coords.longitude;
                this.addMarker(new kakao.maps.LatLng(this.lat, this.long));
                this.latLongToAddress(this.long, this.lat);
                this.setCenter(this.lat, this.long);
                this.loading = false;
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
            }
            }
        }).mount('#app');
    </script>
</x-layouts.app>
