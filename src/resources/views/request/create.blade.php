<x-layouts.app>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <div id="app" class="w-full h-screen" style="padding-bottom: 200px">
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

                //마커를 미리 생성
                this.marker = new kakao.maps.Marker({
                    position: new kakao.maps.LatLng(this.lat, this.long),
                    map: this.mapObject
                });

                this.infowindow = new kakao.maps.InfoWindow({zindex:1});

                //컨트롤러
                let mapTypeControl = new kakao.maps.MapTypeControl();
                this.mapObject.addControl(mapTypeControl, kakao.maps.ControlPosition.TOPRIGHT);

                // 줌
                let zoomControl = new kakao.maps.ZoomControl();
                this.mapObject.addControl(zoomControl, kakao.maps.ControlPosition.RIGHT);

                kakao.maps.event.addListener(this.mapObject, 'click', (mouseEvent) => {
                    this.addMarker(mouseEvent.latLng);
                    this.lat = mouseEvent.latLng.getLat();
                    this.long = mouseEvent.latLng.getLng();
                    this.latLongToAddress(this.long, this.lat);
                });

                this.latLongToAddress(this.long, this.lat);
            },
            latLongToAddress(long, lat){
                const numLong = parseFloat(long);
                const numLat = parseFloat(lat);
                if (isNaN(numLong) || isNaN(numLat)) {
                    console.warn('좌표 값이 올바르지 않아 주소 변환을 건너뜁니다.', long, lat);
                    return;
                }
                let geocoder = new kakao.maps.services.Geocoder();
                geocoder.coord2Address(numLong, numLat, (result, status) => {
                    if (status === kakao.maps.services.Status.OK) {
                        this.address = result[0].road_address && result[0].road_address.address_name ? result[0].road_address.address_name : result[0].address.address_name;
                        var detailAddr = !!result[0].road_address ? '<div>도로명주소 : ' + result[0].road_address.address_name + '</div>' : '';
                        detailAddr += '<div>지번 주소 : ' + result[0].address.address_name + '</div>';

                        var content = '<div class="bAddr" style="width: 400px; height: 100px; padding: 10px">' +
                            '<span class="title">법정동 주소정보</span>' +
                            detailAddr +
                            '</div>';


                        // 인포윈도우에 클릭한 위치에 대한 법정동 상세 주소정보를 표시합니다
                        // this.infowindow.setContent(content);
                        // this.infowindow.open(this.mapObject, this.marker);
                    }
                });
            },
            setMap(address){
                let geocoder = new kakao.maps.services.Geocoder();
                geocoder.addressSearch(address, (results, status) => {
                    // 정상적으로 검색이 완료됐으면
                    if (status === kakao.maps.services.Status.OK) {
                        let result = results[0]; //첫번째 결과의 값을 활용
                        this.lat = result.y;
                        this.long = result.x;
                        // 해당 주소에 대한 좌표를 받아서
                        let coords = new kakao.maps.LatLng(this.lat, this.long);
                        // 지도를 보여준다.
                        this.mapObject.relayout();
                        // 지도 중심을 변경한다.
                        this.mapObject.setCenter(coords);
                        // 마커를 결과값으로 받은 위치로 옮긴다.
                        this.marker.setPosition(coords)
                    }
                });
            },
            addMarker(position){
                console.log(position);
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
                        // 검색결과 항목을 클릭했을때 실행할 코드를 작성하는 부분.

                        // 각 주소의 노출 규칙에 따라 주소를 조합한다.
                        // 내려오는 변수가 값이 없는 경우엔 공백('')값을 가지므로, 이를 참고하여 분기 한다.
                        var addr = ''; // 주소 변수
                        var extraAddr = ''; // 참고항목 변수

                        //사용자가 선택한 주소 타입에 따라 해당 주소 값을 가져온다.
                        if (data.userSelectedType === 'R') { // 사용자가 도로명 주소를 선택했을 경우
                            addr = data.roadAddress;
                        } else { // 사용자가 지번 주소를 선택했을 경우(J)
                            addr = data.jibunAddress;
                        }

                        // 사용자가 선택한 주소가 도로명 타입일때 참고항목을 조합한다.
                        if(data.userSelectedType === 'R'){
                            // 법정동명이 있을 경우 추가한다. (법정리는 제외)
                            // 법정동의 경우 마지막 문자가 "동/로/가"로 끝난다.
                            if(data.bname !== '' && /[동|로|가]$/g.test(data.bname)){
                                extraAddr += data.bname;
                            }
                            // 건물명이 있고, 공동주택일 경우 추가한다.
                            if(data.buildingName !== '' && data.apartment === 'Y'){
                                extraAddr += (extraAddr !== '' ? ', ' + data.buildingName : data.buildingName);
                            }
                            // 표시할 참고항목이 있을 경우, 괄호까지 추가한 최종 문자열을 만든다.
                            if(extraAddr !== ''){
                                extraAddr = ' (' + extraAddr + ')';
                            }
                            // 조합된 참고항목을 해당 필드에 넣는다.
                            this.addressExtra = extraAddr;

                        } else {
                            this.addressExtra = '';
                        }

                        // 우편번호와 주소 정보를 해당 필드에 넣는다.
                        this.addressPostcode = data.zonecode;
                        this.address = addr;
                        // 커서를 상세주소 필드로 이동한다.
                        //this.$refs.address_detail.focus();

                        // iframe을 넣은 element를 안보이게 한다.
                        // (autoClose:false 기능을 이용한다면, 아래 코드를 제거해야 화면에서 사라지지 않는다.)
                        this.findAddress = false;

                        // 우편번호 찾기 화면이 보이기 이전으로 scroll 위치를 되돌린다.
                        document.body.scrollTop = currentScroll;

                        this.setMap(this.address);
                    },
                    // 우편번호 찾기 화면 크기가 조정되었을때 실행할 코드를 작성하는 부분. iframe을 넣은 element의 높이값을 조정한다.
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
                }

                axios.post('/api/requests', params).then(res => {
                    if(res.data.success){
                        alert('위치정보가 공유되었습니다. ');
                    } else {
                        alert('위치정보 공유에 실패했습니다. 다시 시도해주세요.');
                    }
                }).catch(err => {
                    console.error(err);
                    alert('위치정보 공유에 실패했습니다. 다시 시도해주세요.');
                });
            },
            copyText(text){
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed'; // Prevent scrolling to bottom of page in MS Edge.
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
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
