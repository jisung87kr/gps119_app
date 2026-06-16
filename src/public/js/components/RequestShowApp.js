import MapLoader from '/js/components/MapLoader.js';
import IntroScreen from '/js/components/IntroScreen.js';
import LocationButton from '/js/components/LocationButton.js';
import LocationInfo from '/js/components/LocationInfo.js';
import MapContainer from '/js/components/MapContainer.js';
import {
    reverseGeocode,
    getCurrentPositionOnce,
    showGeolocationError,
    wgs84ToWCONGNAMUL,
    buildBigMapButton,
    buildRequestInfoWindowContent
} from '/js/components/mapHelpers.js';

/**
 * 요청 위치 확인(show) 화면의 Vue 앱 구성. 읽기 전용.
 * 요청 마커(빨강) + 내 위치 마커(파랑) 두 개를 표시하고 두 마커가 모두 보이도록 맞춘다.
 *
 * @param {Object} options
 * @param {{latitude:string|number, longitude:string|number, address:string}} options.request - Blade에서 주입한 요청 좌표/주소
 * @returns {Object} createApp()에 넘길 Vue 앱 옵션
 */
export default function createRequestShowApp(options = {}) {
    const request = options.request ?? {};

    return {
        components: {
            MapLoader,
            IntroScreen,
            LocationButton,
            LocationInfo,
            MapContainer
        },
        data() {
            return {
                requestLat: request.latitude ?? '33.450701',
                requestLong: request.longitude ?? '126.570667',
                requestAddress: request.address ?? '요청 위치를 확인 중입니다...',
                myLat: '33.450701',
                myLong: '126.570667',
                myAddress: '현재 위치를 확인 중입니다...',
                mapObject: null,
                requestMarker: null,
                myMarker: null,
                showIntro: true,
                loading: false
            };
        },
        methods: {
            async initMap() {
                const mapElement = document.getElementById('map');
                this.mapObject = new kakao.maps.Map(mapElement, {
                    center: new kakao.maps.LatLng(this.requestLat, this.requestLong),
                    level: 8,
                });

                // 요청 위치 마커 (빨간색)
                this.requestMarker = new kakao.maps.Marker({
                    position: new kakao.maps.LatLng(this.requestLat, this.requestLong),
                    map: this.mapObject,
                    image: new kakao.maps.MarkerImage(
                        'data:image/svg+xml;base64,' + btoa(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="#dc2626">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                        `),
                        new kakao.maps.Size(32, 32),
                        { offset: new kakao.maps.Point(16, 32) }
                    )
                });

                // 좌표 변환에 성공했을 때만 '큰지도보기' 버튼을 노출 (빈 좌표 URL 방지)
                let bigMapButton = '';
                try {
                    const wcongnamul = await wgs84ToWCONGNAMUL(this.requestLat, this.requestLong);
                    bigMapButton = buildBigMapButton(wcongnamul);
                } catch (e) {
                    console.warn('좌표 변환 실패 - 큰지도보기 버튼을 숨깁니다.', e);
                }

                const infowindow = new kakao.maps.InfoWindow({
                    position: this.requestMarker.position,
                    content: buildRequestInfoWindowContent({
                        requestLat: this.requestLat,
                        requestLong: this.requestLong,
                        bigMapButton
                    }),
                });
                infowindow.open(this.mapObject, this.requestMarker);

                // 내 위치 마커 (파란색) - 초기에는 표시하지 않음
                this.myMarker = new kakao.maps.Marker({
                    position: new kakao.maps.LatLng(this.myLat, this.myLong),
                    image: new kakao.maps.MarkerImage(
                        'data:image/svg+xml;base64,' + btoa(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="#2563eb">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                        `),
                        new kakao.maps.Size(32, 32),
                        { offset: new kakao.maps.Point(16, 32) }
                    )
                });

                // 컨트롤러
                const mapTypeControl = new kakao.maps.MapTypeControl();
                this.mapObject.addControl(mapTypeControl, kakao.maps.ControlPosition.TOPRIGHT);

                const zoomControl = new kakao.maps.ZoomControl();
                this.mapObject.addControl(zoomControl, kakao.maps.ControlPosition.RIGHT);

                this.setBounds();

                // 지도 준비 후 내 위치 탐색 → 1초 뒤 인트로 숨김 (기존 init() 순서 보존)
                this.getMyLocation();
                setTimeout(() => {
                    this.showIntro = false;
                }, 1000);
            },
            async getMyLocation() {
                this.loading = true;
                try {
                    const position = await getCurrentPositionOnce();
                    this.myLat = position.coords.latitude;
                    this.myLong = position.coords.longitude;
                    this.myMarker.setPosition(new kakao.maps.LatLng(this.myLat, this.myLong));
                    this.myMarker.setMap(this.mapObject);
                    const address = await reverseGeocode(this.myLong, this.myLat);
                    if (address) {
                        this.myAddress = address;
                    }
                    this.loading = false;
                    this.setBounds();
                } catch (error) {
                    if (error.message === 'UNSUPPORTED') {
                        alert("지원하지 않는 브라우저 입니다.");
                    } else {
                        showGeolocationError(error);
                    }
                    this.loading = false;
                }
            },
            showRequestLocation() {
                this.mapObject.setCenter(new kakao.maps.LatLng(this.requestLat, this.requestLong));
                this.mapObject.setLevel(3);
            },
            showMyLocation() {
                if (this.myLat && this.myLong) {
                    this.mapObject.setCenter(new kakao.maps.LatLng(this.myLat, this.myLong));
                    this.mapObject.setLevel(3);
                }
            },
            setBounds() {
                const markers = [this.requestMarker, this.myMarker];
                const bounds = new kakao.maps.LatLngBounds();
                for (let i = 0; i < markers.length; i++) {
                    bounds.extend(markers[i].getPosition());
                }
                this.mapObject.setBounds(bounds);
                this.mapObject.setLevel(7); // 줌 레벨 설정
            }
        }
    };
}
