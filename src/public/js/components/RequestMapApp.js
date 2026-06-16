import MapLoader from '/js/components/MapLoader.js';
import IntroScreen from '/js/components/IntroScreen.js';
import LocationButton from '/js/components/LocationButton.js';
import LocationInfo from '/js/components/LocationInfo.js';
import MapContainer from '/js/components/MapContainer.js';
import { reverseGeocode, getCurrentPositionOnce, showGeolocationError } from '/js/components/mapHelpers.js';

/**
 * 구조요청 위치공유 화면(create / create-project)의 공유 Vue 앱 구성.
 *
 * @param {Object} options
 * @param {number|string|null} options.projectId - 프로젝트 단위 요청일 때만 전달. 전달 시 요청 생성 API에 project_id 포함.
 * @returns {Object} createApp()에 그대로 넘길 수 있는 Vue 앱 옵션
 */
export default function createRequestMapApp(options = {}) {
    const projectId = options.projectId ?? null;

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
                projectId,
                lat: '33.450701',
                long: '126.570667',
                mapObject: null,
                marker: null,
                addressPostcode: '',
                address: '',
                addressExtra: '',
                findAddress: false,
                showIntro: true,
                loading: false
            };
        },
        mounted() {
            setTimeout(() => {
                this.getLocation();
                this.showIntro = false;
            }, 1000);
        },
        methods: {
            initMap() {
                const mapElement = document.getElementById('map');
                this.mapObject = new kakao.maps.Map(mapElement, {
                    center: new kakao.maps.LatLng(this.lat, this.long),
                    level: 5,
                });

                this.marker = new kakao.maps.Marker({
                    position: new kakao.maps.LatLng(this.lat, this.long),
                    map: this.mapObject
                });

                const mapTypeControl = new kakao.maps.MapTypeControl();
                this.mapObject.addControl(mapTypeControl, kakao.maps.ControlPosition.TOPRIGHT);

                const zoomControl = new kakao.maps.ZoomControl();
                this.mapObject.addControl(zoomControl, kakao.maps.ControlPosition.RIGHT);

                kakao.maps.event.addListener(this.mapObject, 'click', (mouseEvent) => {
                    this.addMarker(mouseEvent.latLng);
                    this.lat = mouseEvent.latLng.getLat();
                    this.long = mouseEvent.latLng.getLng();
                    this.latLongToAddress(this.long, this.lat);
                });

                this.latLongToAddress(this.long, this.lat);
            },
            async latLongToAddress(long, lat) {
                const address = await reverseGeocode(long, lat);
                if (address) {
                    this.address = address;
                }
            },
            setMap(address) {
                const geocoder = new kakao.maps.services.Geocoder();
                geocoder.addressSearch(address, (results, status) => {
                    if (status === kakao.maps.services.Status.OK) {
                        const result = results[0];
                        this.lat = result.y;
                        this.long = result.x;
                        const coords = new kakao.maps.LatLng(this.lat, this.long);
                        this.mapObject.relayout();
                        this.mapObject.setCenter(coords);
                        this.marker.setPosition(coords);
                    }
                });
            },
            addMarker(position) {
                this.marker.setMap(null);
                this.marker = new kakao.maps.Marker({
                    position: position
                });
                this.marker.setMap(this.mapObject);
            },
            execDaumPostcode() {
                this.findAddress = true;
                const currentScroll = Math.max(document.body.scrollTop, document.documentElement.scrollTop);
                new daum.Postcode({
                    oncomplete: (data) => {
                        let addr = '';
                        let extraAddr = '';

                        if (data.userSelectedType === 'R') {
                            addr = data.roadAddress;
                        } else {
                            addr = data.jibunAddress;
                        }

                        if (data.userSelectedType === 'R') {
                            if (data.bname !== '' && /[동|로|가]$/g.test(data.bname)) {
                                extraAddr += data.bname;
                            }
                            if (data.buildingName !== '' && data.apartment === 'Y') {
                                extraAddr += (extraAddr !== '' ? ', ' + data.buildingName : data.buildingName);
                            }
                            if (extraAddr !== '') {
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
                    onresize: (size) => {
                        this.$refs.search_address_element.style.height = size.height + 'px';
                    },
                    width: '100%',
                    height: '100%'
                }).embed(this.$refs.search_address_element);
            },
            shareLocation(type) {
                if (!confirm('위치공유를 하시겠습니까?')) {
                    return false;
                }

                if (!this.address || !this.lat || !this.long) {
                    alert('위치정보가 정확하지 않습니다. 다시 시도해주세요.');
                    return false;
                }

                const params = {
                    latitude: this.lat,
                    longitude: this.long,
                    address: this.address,
                    description: type,
                };
                if (this.projectId) {
                    params.project_id = this.projectId;
                }

                axios.post('/api/requests', params).then(res => {
                    if (res.data.success) {
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
                const moveLatLon = new kakao.maps.LatLng(lat, long);
                this.mapObject.setCenter(moveLatLon);
            },
            getLocation() {
                this.loading = true;
                getCurrentPositionOnce()
                    .then((position) => {
                        this.showPosition(position);
                    })
                    .catch((error) => {
                        if (error.message === 'UNSUPPORTED') {
                            // 기존 동작 보존: 미지원 브라우저 분기는 loading을 초기화하지 않음
                            alert("지원하지 않는 브라우저 입니다.");
                        } else {
                            this.showError(error);
                            this.loading = false;
                        }
                    });
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
                showGeolocationError(error);
            }
        }
    };
}
