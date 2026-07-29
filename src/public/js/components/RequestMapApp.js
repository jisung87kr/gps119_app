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

    // RequestType(SPEC-02c) 라벨 — 백엔드 enum 미러
    const TYPE_LABELS = { accident: '사고', breakdown: '고장', other: '기타', emergency: '긴급전화' };

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
                loading: false,
                sheetExpanded: true,
                // FE-3.1 주소확인 모달 + 유형
                typeLabels: TYPE_LABELS,
                confirmOpen: false,
                requestType: null,    // accident | breakdown | other
                submitting: false,
                submitError: '',
                staticMap: null,
                successOpen: false,
                createdRequestId: null,
                contactPhone: options.contactPhone || null,
                emergencyTel: options.emergencyTel || '010-4794-0119',
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
                        // 모달이 열려 있으면 미리보기도 갱신
                        if (this.confirmOpen) this.$nextTick(() => this.initStaticPreview());
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
            // FE-3.1: 유형별 신고 진입. 긴급전화는 모달 없이 즉시 통화, 나머지는 주소확인 모달.
            typeLabel(t) { return this.typeLabels[t] || t; },

            emergencyCall() {
                // 행사 구조본부 번호(projects.settings) 또는 기본 안내번호
                window.location.href = 'tel:' + this.emergencyTel;
            },

            openConfirm(type) {
                if (!this.lat || !this.long) {
                    alert('위치정보가 정확하지 않습니다. 잠시 후 다시 시도해주세요.');
                    return;
                }
                this.requestType = type;
                this.submitError = '';
                this.confirmOpen = true;
                // 모달 미니 지도 미리보기(정적 지도, 두 번째 동적 지도 인스턴스 회피)
                this.$nextTick(() => this.initStaticPreview());
            },

            initStaticPreview() {
                const el = document.getElementById('confirm-map');
                if (!el || !(window.kakao && kakao.maps && kakao.maps.StaticMap)) return;
                el.innerHTML = '';
                try {
                    this.staticMap = new kakao.maps.StaticMap(el, {
                        center: new kakao.maps.LatLng(this.lat, this.long),
                        level: 3,
                        marker: [{ position: new kakao.maps.LatLng(this.lat, this.long) }],
                    });
                } catch (e) {
                    // 미리보기 실패해도 주소 텍스트로 신고 진행 가능(막힘 방지)
                    console.warn('미리보기 지도 생성 실패', e);
                }
            },

            closeConfirm() {
                this.confirmOpen = false;
                this.requestType = null;
                this.submitError = '';
            },

            // 모달에서 [지도에서 위치 보정] → 모달 닫고 본 지도에서 탭으로 보정(기존 흐름 재사용)
            correctOnMap() {
                this.closeConfirm();
            },

            async confirmSubmit() {
                if (this.submitting) return;
                if (!this.lat || !this.long) {
                    this.submitError = '위치정보가 정확하지 않습니다.';
                    return;
                }
                this.submitting = true;
                this.submitError = '';

                const params = {
                    type: this.requestType,
                    latitude: this.lat,
                    longitude: this.long,
                    address: this.address || null,
                };
                if (this.projectId) params.project_id = this.projectId;
                if (this.contactPhone) params.contact_phone = this.contactPhone;

                try {
                    const res = await window.axios.post('/api/requests', params,
                        { headers: { Accept: 'application/json' } });
                    if (res.data && res.data.success) {
                        this.createdRequestId = res.data.data?.id ?? null;
                        this.confirmOpen = false;
                        this.successOpen = true;
                    } else {
                        this.submitError = '전송에 실패했습니다. 다시 시도해주세요.';
                    }
                } catch (err) {
                    console.error(err);
                    this.submitError = '전송에 실패했습니다. 다시 시도해주세요.';
                } finally {
                    this.submitting = false;
                }
            },
            setCenter(lat, long) {
                const moveLatLon = new kakao.maps.LatLng(lat, long);
                this.mapObject.setCenter(moveLatLon);
                this.applySheetOffset();
            },
            // 바텀시트가 지도 하단을 가리는 만큼 중심을 위로 올려 마커가 보이게 한다.
            applySheetOffset() {
                if (!this.mapObject) return;
                const sheet = document.getElementById('bottom-sheet');
                const covered = this.sheetExpanded && sheet ? sheet.offsetHeight : 0;
                if (covered > 0) {
                    // 가려지는 높이의 절반만큼 지도 내용을 위로 이동
                    this.mapObject.panBy(0, covered / 2);
                }
            },
            toggleSheet() {
                this.sheetExpanded = !this.sheetExpanded;
                // 시트 슬라이드(0.3s) 후 지도 타일을 다시 그린다 (Kakao relayout)
                if (this.mapObject) {
                    const center = this.mapObject.getCenter();
                    setTimeout(() => {
                        this.mapObject.relayout();
                        this.mapObject.setCenter(center);
                    }, 320);
                }
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
