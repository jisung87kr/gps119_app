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
    buildRequestInfoWindowContent,
    buildReopenButtonContent
} from '/js/components/mapHelpers.js';

/**
 * HTML 문자열을 단일 DOM 요소로 변환. CustomOverlay에 이벤트 리스너를 붙이기 위해 사용.
 * @param {string} html
 * @returns {HTMLElement}
 */
function htmlToElement(html) {
    const template = document.createElement('div');
    template.innerHTML = html.trim();
    return template.firstElementChild;
}

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
                // FE-3.4 신고자 상태추적
                requestId: request.id ?? null,
                projectId: request.projectId ?? null,
                userId: request.userId ?? null,
                reqStatus: request.status ?? 'pending',
                paramedicName: request.paramedicName ?? null,
                paramedicPhone: request.paramedicPhone ?? null,
                controlTel: request.controlTel ?? '010-4794-0119',
                statusWs: 'connecting', // connecting | ws | polling
                showStageDetail: false,
                // 🔑 내 위치는 «모른다»로 시작한다. 예전엔 제주 좌표(33.45, 126.57)가
                //    기본값이었는데, setBounds 가 그 «가짜 점»까지 포함해 버려서
                //    요청지가 강원도면 지도가 강원↔제주를 다 담는 축척으로 열렸다.
                //    (실제로 보고된 증상 — 요청 마커가 화면 밖으로 나갔다.)
                //    빈 문자열이면 LocationInfo 의 hasCoords 가 false 라 좌표 줄도 숨는다.
                myLat: '',
                myLong: '',
                myLocated: false,
                myAddress: '현재 위치를 확인 중입니다...',
                mapObject: null,
                requestMarker: null,
                myMarker: null,
                infoOverlay: null,
                reopenOverlay: null,
                overlayVisible: true,
                sheetExpanded: true,
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

                // 요청 정보 카드. CustomOverlay는 마커보다 항상 위 레이어라 핀이 카드를 파고들지 않는다.
                // yAnchor: 1 → 카드(+하단 여백) 바닥이 마커 위치에 정렬되어 카드가 마커 위로 떠 보인다.
                // 정보 카드 오버레이. 콘텐츠를 DOM 요소로 만들어 닫기(✕) 버튼에 리스너를 연결한다.
                const cardEl = htmlToElement(buildRequestInfoWindowContent({
                    requestLat: this.requestLat,
                    requestLong: this.requestLong,
                    bigMapButton
                }));
                const closeBtn = cardEl.querySelector('[data-overlay-close]');
                if (closeBtn) {
                    closeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.closeOverlay();
                    });
                }
                this.infoOverlay = new kakao.maps.CustomOverlay({
                    position: this.requestMarker.getPosition(),
                    content: cardEl,
                    xAnchor: 0.5,
                    yAnchor: 1,
                    zIndex: 5,
                    clickable: true,
                });
                this.infoOverlay.setMap(this.mapObject);

                // 닫힌 상태에서 다시 여는 '요청 정보' 핀 버튼 (초기에는 숨김)
                const reopenEl = htmlToElement(buildReopenButtonContent());
                const openBtn = reopenEl.querySelector('[data-overlay-open]');
                if (openBtn) {
                    openBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.openOverlay();
                    });
                }
                this.reopenOverlay = new kakao.maps.CustomOverlay({
                    position: this.requestMarker.getPosition(),
                    content: reopenEl,
                    xAnchor: 0.5,
                    yAnchor: 1,
                    zIndex: 5,
                    clickable: true,
                });

                // 요청 마커 클릭 시에도 카드 표시/숨김 토글
                kakao.maps.event.addListener(this.requestMarker, 'click', () => {
                    this.toggleOverlay();
                });

                // 내 위치 마커 (파란색) - 초기에는 표시하지 않음.
                // 위치를 아직 모르므로 요청지를 «자리표시» 좌표로 둔다. 지도에 올리지 않고
                // setBounds 도 myLocated 전에는 이 마커를 보지 않는다(가짜 점 방지).
                this.myMarker = new kakao.maps.Marker({
                    position: new kakao.maps.LatLng(this.requestLat, this.requestLong),
                    zIndex: 1,
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
                    this.myLocated = true;   // 이때부터 setBounds 가 내 위치를 포함한다
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
                this.applySheetOffset();
                // 요청지로 이동하면 정보 카드를 다시 보여준다.
                this.openOverlay();
            },
            // 정보 카드 열기: 카드 표시 + 열기 핀 숨김
            openOverlay() {
                if (!this.infoOverlay) return;
                this.overlayVisible = true;
                this.infoOverlay.setMap(this.mapObject);
                if (this.reopenOverlay) this.reopenOverlay.setMap(null);
            },
            // 정보 카드 닫기: 카드 숨김 + 열기 핀 표시
            closeOverlay() {
                if (!this.infoOverlay) return;
                this.overlayVisible = false;
                this.infoOverlay.setMap(null);
                if (this.reopenOverlay) this.reopenOverlay.setMap(this.mapObject);
            },
            // 카드 표시/숨김 토글 (마커 클릭으로 호출)
            toggleOverlay() {
                if (this.overlayVisible) {
                    this.closeOverlay();
                } else {
                    this.openOverlay();
                }
            },
            // 바텀시트가 지도 하단을 가리는 만큼 중심을 위로 올려 마커가 보이게 한다.
            // setBounds/포커스처럼 중심을 절대값으로 새로 잡은 직후에만 호출(반복 호출 시 누적 방지).
            // 시트가 지금 지도 하단을 가리는 높이(px). 접힘 상태는 핸들만 남으므로 0으로 본다.
            sheetCoveredPx() {
                const sheet = document.getElementById('bottom-sheet');
                return this.sheetExpanded && sheet ? sheet.offsetHeight : 0;
            },
            applySheetOffset() {
                if (!this.mapObject) return;
                const covered = this.sheetCoveredPx();
                if (covered > 0) {
                    this.mapObject.panBy(0, covered / 2);
                }
            },
            // 바텀시트 펼치기/접기.
            //
            // 🔑 «가려진 높이가 바뀐 만큼만» 중심을 되돌린다. 예전엔 접기 전 중심을 그대로
            //    복원했는데, 그 중심에는 펼침 상태에서 넣어 둔 보정(panBy(0, covered/2))이
            //    이미 들어 있었다. 그래서 접어서 지도가 다 보이게 되면 마커가 위로 치우쳐
            //    「엉뚱한 곳을 보고 있는」 것처럼 됐다. 보정은 «넣었으면 빼야» 한다.
            toggleSheet() {
                const before = this.sheetCoveredPx();
                this.sheetExpanded = !this.sheetExpanded;
                if (!this.mapObject) return;

                // 트랜지션(duration-300)이 끝난 뒤 계산해야 offsetHeight 가 맞는다.
                setTimeout(() => {
                    if (!this.mapObject) return;
                    const after = this.sheetCoveredPx();
                    this.mapObject.relayout();
                    const delta = (after - before) / 2;
                    if (delta !== 0) this.mapObject.panBy(0, delta);
                }, 320);
            },
            // 내 위치를 아직 모르면 «먼저 찾는다». 예전엔 기본값(제주)이 들어 있어
            // 항상 참이었지만, 이제 모를 때는 빈 값이라 그냥 두면 버튼이 조용히
            // 아무 일도 하지 않는다 — 눌렀는데 반응이 없는 건 고장으로 읽힌다.
            async showMyLocation() {
                if (!this.myLocated) {
                    await this.getMyLocation();
                    if (!this.myLocated) return;   // 권한 거부 등 — 오류는 getMyLocation 이 안내한다
                }
                this.mapObject.setCenter(new kakao.maps.LatLng(this.myLat, this.myLong));
                this.mapObject.setLevel(3);
                this.applySheetOffset();
            },
            // 보여야 하는 점들이 모두 들어오도록 지도를 맞춘다.
            //
            // 🔑 «아는 점만» 넣는다. 내 위치를 모르는 동안 myMarker 를 포함시키면
            //    자리표시 좌표까지 담느라 축척이 터무니없이 넓어진다.
            // 🔑 setBounds 가 «계산한 레벨을 덮어쓰지 않는다». 예전엔 바로 뒤에
            //    setLevel(7) 이 있어서, 두 점이 아무리 가까워도 항상 같은 축척으로
            //    벌어졌다 — 맞춰 놓고 도로 풀어 버리는 코드였다.
            setBounds() {
                if (!this.mapObject || !this.requestMarker) return;

                const points = [this.requestMarker.getPosition()];
                if (this.myLocated && this.myMarker) {
                    points.push(this.myMarker.getPosition());
                }

                if (points.length === 1) {
                    this.mapObject.setCenter(points[0]);
                    this.mapObject.setLevel(4);
                } else {
                    const bounds = new kakao.maps.LatLngBounds();
                    points.forEach((p) => bounds.extend(p));
                    this.mapObject.setBounds(bounds);
                    // 두 점이 거의 겹칠 때 과도하게 확대되는 것만 막는다.
                    if (this.mapObject.getLevel() < 3) this.mapObject.setLevel(3);
                }

                this.applySheetOffset();
            },

            // ── FE-3.4 신고자 상태추적 ───────────────────────────
            statusLabel(s) {
                return ({ pending: '접수', in_progress: '구조 진행중', completed: '구조 완료', cancelled: '요청 취소' })[s] || s;
            },

            // 담당자 배정 수신 시 이름/전화 갱신 + 상태 갱신
            _onStatusUpdated(payload) {
                if (!payload) return;
                if (payload.status) this.reqStatus = payload.status;
                if (payload.dispatch) {
                    this.paramedicName = payload.dispatch.paramedic_name || this.paramedicName;
                    this.paramedicPhone = payload.dispatch.paramedic_phone || this.paramedicPhone;
                }
            },

            async _subscribeStatus() {
                // 행사 신고만 실시간 추적(project_id 있을 때)
                if (!this.projectId || !this.userId) return;
                const echo = await this._waitForEcho();
                if (!echo) { this.statusWs = 'polling'; this._startStatusPolling(); return; }

                echo.private(`event.${this.projectId}.requester.${this.userId}`)
                    .listen('.request.status.updated', (e) => this._onStatusUpdated(e));

                const conn = echo.connector?.pusher?.connection;
                if (conn) {
                    conn.bind('state_change', ({ current }) => {
                        if (current === 'connected') { this.statusWs = 'ws'; this._stopStatusPolling(); }
                        else if (['unavailable', 'failed', 'disconnected'].includes(current)) { this._startStatusPolling(); }
                    });
                    if (conn.state === 'connected') this.statusWs = 'ws';
                } else { this._startStatusPolling(); }
            },

            _waitForEcho() {
                return new Promise((resolve) => {
                    if (window.Echo) return resolve(window.Echo);
                    let w = 0;
                    const t = setInterval(() => {
                        w += 100;
                        if (window.Echo) { clearInterval(t); resolve(window.Echo); }
                        else if (w >= 3000) { clearInterval(t); resolve(null); }
                    }, 100);
                });
            },

            _startStatusPolling() {
                if (this._statusTimer || !this.requestId) return;
                this.statusWs = 'polling';
                this._statusTimer = setInterval(async () => {
                    try {
                        const res = await window.axios.get(`/api/requests/${this.requestId}`, { headers: { Accept: 'application/json' } });
                        const d = res.data?.data;
                        if (d && d.status) this.reqStatus = d.status;
                    } catch (e) { /* 조용히 재시도 */ }
                }, 15000);
            },
            _stopStatusPolling() {
                if (this._statusTimer) { clearInterval(this._statusTimer); this._statusTimer = null; }
            },
        },

        computed: {
            isProjectRequest() { return !!this.projectId; },
            stageIndex() {
                return ({ pending: 0, in_progress: 1, completed: 2 })[this.reqStatus] ?? 0;
            },
            isCancelled() { return this.reqStatus === 'cancelled'; },
            // 배정 전 = 상황실, 배정 후 = 담당자
            callPhone() { return this.paramedicPhone || this.controlTel; },
            callLabel() { return this.paramedicPhone ? `담당자(${this.paramedicName || '구급대'})에게 전화` : '상황실에 전화'; },
        },

        mounted() {
            // window.Echo 준비 대기 후 구독(QA 교훈)
            this._subscribeStatus();
            // 전역 QA 훅
            window.__requestShow = this;
        },

        beforeUnmount() {
            this._stopStatusPolling();
            if (window.Echo && this.projectId && this.userId) {
                try { window.Echo.leave(`event.${this.projectId}.requester.${this.userId}`); } catch (e) { /* noop */ }
            }
        },
    };
}
