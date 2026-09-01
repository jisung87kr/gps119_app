// 웹 관제 SPA — Vue 옵션 객체 (FE-2.1 + FE-3.3).
// 인원 마커 풀 + 신고 고정핀 + 역할필터 + 실시간(presence/control) + 폴링 폴백
// + 지령 배정 패널 + 출동현황 보드(FE-3.3).

import { PersonMarkerPool, RequestPinLayer, CLUSTER_PROFILE } from './markerPool';
import { TrackLayer } from './trackLayer';
import {
    ROLE_ORDER, ROLE_META, roleMeta, priorityMeta,
    dispatchStatusMeta, DISPATCH_STATUS_ORDER, requestTypeMeta, presenceState,
    trackingMeta, attentionCount,
} from './roleMeta';
import { isNativeApp, hasNativeCapability, NativeCapability } from '../native/bridge';

const POLL_INTERVAL_MS = 12000;
// 마커의 online/stale/offline 재판정 주기. 임계값은 30s/120s(roleMeta)이므로
// 15초면 «한 칸 늦게» 어두워지는 정도라 상황실 판단을 그르치지 않는다.
// 이 타이머가 없으면 WS 가 붙어 있는 동안 마커 상태가 영원히 갱신되지 않는다.
const PRESENCE_DECAY_MS = 15000;
const KAKAO_KEY = '509c2656c00fa9af4782197a888763f6';

// 전체보기가 파고들 수 있는 최대 배율(카카오는 숫자가 작을수록 확대).
// 핀이 한 점뿐일 때 setBounds 가 여기까지 붙어버려 주변 지형이 사라지는 것을 막는다.
const MIN_FIT_LEVEL = 4;

export default {
    data() {
        return {
            projects: [],          // [{id,name}]
            selectedProjectId: null,
            projectName: '',
            backUrl: null,         // 지정 시 헤더에 "대시보드로" 백링크 표시(관리자 진입)

            mapReady: false,
            mapError: false,
            loadingRoster: false,
            railCollapsed: false,

            // ── 모바일(<lg) 분기 ────────────────────────────────
            // 지도는 «머무는 화면», 배정은 «끼어드는 작업». 둘을 동급 탭으로 두면
            // 신고가 떴을 때 "그게 어디냐"를 보려고 지도 컨텍스트를 버려야 한다.
            // 그래서 탭이 아니라 지도 위에 겹치는 3단 스냅 시트로 간다.
            // 부수 효과: 시트가 오버레이라 지도 컨테이너 크기가 안 변한다
            //           → 탭 전환마다 필요한 map.relayout() 이 통째로 불필요.
            isMobile: false,
            sheetSnap: 'peek',     // peek(조망) | half(인지) | full(상세)
            sheetTab: 'requests',  // requests | board | roster

            roleOrder: ROLE_ORDER,
            roleMetaMap: ROLE_META,
            roleFilter: Object.fromEntries(ROLE_ORDER.map((r) => [r, true])),
            roleCounts: {},        // role -> {online,total}
            roster: [],            // roster 원본(활성 참가자) — 역할 배정 패널용
            assigningUserId: null, // 역할 배정 진행중 표시
            hideOffline: false,
            // 이동 궤적 (M-25). 기본은 «꺼짐» — 관제의 기본 질문은 「지금 어디」이고,
            // 선이 항상 깔려 있으면 현재 위치를 읽기 어려워진다.
            showTracks: false,
            trackMinutes: 60,
            tracks: [],
            loadingTracks: false,
            trackError: null,

            requests: [],          // 라이브 신고(최신 우선)
            expandedRequestId: null,
            // request_id -> { primary: 지령상태|null, supports: 보조 인원수 }
            //
            // 🔑 예전에는 request_id -> status 1:1 이었다. 다중 배차(ADR-0007 D4)에서
            //    그대로 두면 보조의 상태가 주담당 칸을 덮어써서, 상황실 화면이 「누가 이
            //    환자를 책임지는가」를 잘못 보여준다. 주담당은 «상태», 보조는 «머릿수»로
            //    성격이 다르므로 한 칸에 합치지 않는다.
            requestStatusMap: {},

            onlineCount: 0,
            requestCount: 0,
            wsState: 'connecting', // connecting | ws | polling
            reportMenu: false,     // 기록 다운로드 메뉴(BE-4.1)

            // FE-3.3 지령 배정 패널
            assign: {
                open: false,
                // 'primary' = 주담당 배정/재배정, 'support' = 보조 «추가»(ADR-0007 D4).
                // 서버도 별도 엔드포인트다 — 한 신고에 두 명이 붙는 건 명시적 결정이다.
                mode: 'primary',
                request: null,     // 대상 신고(payload)
                paramedics: [],     // 가용 대원
                selectedId: null,
                note: '',
                loading: false,
                submitting: false,
                error: '',
                // DispatchStatus::allowedTransitions() 에 역행 전이가 없어 발령은 되돌릴 수
                // 없다. 좁은 화면·장갑 낀 손에서의 오탭 비용이 커서 확인 단계를 둔다.
                confirming: false,
            },

            // 지령 회수(ADR-0007). 배정과 «같은» 2단 확인을 쓴다 — 상황실 화면은 하루
            // 종일 열려 있고, cancelled 는 terminal 이라 되돌릴 수 없다. 다른 점은
            // 하나뿐: 회수는 사유(note)를 같이 보낸다(선택).
            recall: {
                dispatch: null,    // 확인 대기중인 지령(null 이면 닫힘)
                reason: '',
                submitting: false,
                error: '',
            },

            // FE-3.3 출동현황 보드
            dispatchStatusOrder: DISPATCH_STATUS_ORDER,
            board: { counts: {}, active: [], history: [], loading: false },
        };
    },

    computed: {
        /**
         * 🔴 지금 «위치가 전혀 안 오는» 사람 수.
         *
         * 공유를 켜뒀는데 OS 권한이 막힌 사람들이다. 본인은 보이는 줄 알고, 관제는
         * M-5 이전이라면 그냥 「오프라인」으로 봤다 — 그 구분이 M-5 다.
         *
         * 🔑 세는 기준은 서버가 준 attention 이다. 화면이 상태 이름을 나열해 세면
         *    상태가 늘어날 때 여기만 안 고쳐져 조용히 빠진다.
         */
        trackingAlertCount() { return attentionCount(this.roster); },

        hasProject() {
            return this.selectedProjectId != null;
        },

        // CSV 를 내려받을 수 있는 환경인가 (M-21).
        //
        // 🔑 웹뷰는 «파일 다운로드를 기본 처리하지 않는다» — 링크를 눌러도 아무 일도
        //    일어나지 않고, 오류도 안 난다. 상황실 입장에서는 「눌렀는데 안 받아진다」다.
        //    그래서 앱에서는 링크 대신 «어디서 받으면 되는지»를 알려 준다.
        //
        // 「앱이면 숨김」이 아니라 «그 셸이 다운로드를 아는가»로 판정한다 — 나중에 셸이
        //    `file-download` 를 지원하면 웹을 다시 배포하지 않아도 링크가 살아난다.
        //    (웹이 앱보다 최신인 상태가 정상이라는 것 → native/bridge.js)
        reportsDownloadable() {
            return ! isNativeApp() || hasNativeCapability(NativeCapability.FILE_DOWNLOAD);
        },

        // 모바일에서는 레일을 아예 쓰지 않으므로 항상 0px. 그리드 좌표를 바꾸지 않고
        // 폭만 접어서 데스크톱 마크업을 그대로 재사용한다.
        gridStyle() {
            const cols = (this.isMobile || this.railCollapsed) ? '0px 1fr' : '280px 1fr';
            return { gridTemplateColumns: cols };
        },

        // 아직 배정되지 않은 신고(거절·회수 포함 — 재지령 필요). 시트 peek 상태의 핵심 지표.
        unassignedRequests() {
            return this.requests.filter((r) => this.needsAssign(r.request_id));
        },

        // 미배정 우선, 그다음 최신순. 폰에서는 스크롤 없이 보이는 첫 화면이 전부다.
        sortedRequests() {
            const unassigned = [];
            const rest = [];
            this.requests.forEach((r) => {
                this.needsAssign(r.request_id) ? unassigned.push(r) : rest.push(r);
            });
            return unassigned.concat(rest);
        },

        sheetHeightClass() {
            if (this.sheetSnap === 'full') return 'h-[90dvh]';
            if (this.sheetSnap === 'half') return 'h-[45dvh]';
            return 'h-24';
        },
    },

    created() {
        // 비반응 인스턴스 필드 — 구독 해제 대상을 추적한다(선언 위치를 한곳에 모아둔다).
        this._subscribedProjectId = null;
        this._locCh = null;
        this._ctrlCh = null;
        this._pollTimer = null;
        this._mq = null;
        this._onMqChange = null;
        this._pendingRequestId = null; // 딥링크(?request=) — 로드 후 배정 화면 자동 오픈
    },

    mounted() {
        // lg(1024px) 경계로 3단 ↔ 시트 전환. Tailwind lg 브레이크포인트와 맞춘다.
        this._mq = window.matchMedia('(max-width: 1023px)');
        this.isMobile = this._mq.matches;
        this._onMqChange = (e) => {
            this.isMobile = e.matches;
            // 시트가 가리는 영역이 달라지므로 전체보기를 다시 잡아준다.
            this.$nextTick(() => this.recenter());
        };
        this._mq.addEventListener('change', this._onMqChange);

        // 알림/디스코드에서 ?request=123 으로 들어오면 해당 신고 배정 화면으로 바로.
        // "알림 받고 즉시 배정"의 착지점 — 중간 네비게이션 없이 푸시 + 2탭.
        const wantRequest = Number(new URLSearchParams(window.location.search).get('request')) || null;
        this._pendingRequestId = wantRequest;

        // Blade 가 data-projects 로 주입한 활성 행사 목록.
        // 템플릿이 다중 루트라 this.$el은 fragment placeholder(텍스트 노드) → 마운트 컨테이너에서 직접 읽는다.
        try {
            const root = document.getElementById('control-app');
            this.projects = JSON.parse(root?.dataset.projects || '[]');
        } catch (e) {
            this.projects = [];
        }
        // 딥링크(?project=id → data-selected)로 지정된 행사가 활성 목록에 있으면 우선 진입,
        // 없으면 최신(첫 번째, id desc)을 자동 선택. 여러 개면 헤더 셀렉트로 전환.
        const root = document.getElementById('control-app');
        this.backUrl = root?.dataset.backUrl || null;
        const wanted = Number(root?.dataset.selected) || null;
        if (wanted && this.projects.some((p) => p.id === wanted)) {
            this.selectProject(wanted);
        } else if (this.projects.length >= 1) {
            this.selectProject(this.projects[0].id);
        }
        // 브라우저 QA용 전역 노출
        window.__control = this;
    },

    beforeUnmount() {
        this._teardownRealtime();
        if (this._mq && this._onMqChange) this._mq.removeEventListener('change', this._onMqChange);
    },

    methods: {
        roleLabel(role) { return roleMeta(role).label; },
        roleColor(role) { return roleMeta(role).color; },

        // 위치 추적 상태 (M-5). 라벨·색·「경보인가」는 전부 서버가 준 값이다.
        trackingLabel(state) { return trackingMeta(state).label; },
        trackingColor(state) { return trackingMeta(state).color; },
        priorityLabel(p) { return priorityMeta(p).label; },
        priorityColor(p) { return priorityMeta(p).color; },
        dispatchLabel(s) { return dispatchStatusMeta(s).label; },
        dispatchBadge(s) { return dispatchStatusMeta(s).badge; },
        typeLabel(t) { return requestTypeMeta(t).label; },

        async selectProject(id) {
            if (id == null) return;
            this.selectedProjectId = Number(id);
            const p = this.projects.find((x) => x.id === this.selectedProjectId);
            this.projectName = p ? p.name : '';
            this._teardownRealtime();
            this.requests = [];
            this.requestCount = 0;
            this.requestStatusMap = {};
            this.closeAssign();
            this.cancelRecallConfirm(); // 이전 행사의 지령이 확인창에 남지 않도록

            await this._ensureMap();

            // 🔑 지도는 «표현»이고 신고 목록·출동 보드·실시간은 «관제 기능»이다.
            //    예전에는 여기서 `if (!this.mapReady) return;` 로 빠져나가서, 카카오 지도가
            //    실패하면 목록·보드·실시간 구독·딥링크가 **전부** 죽었다. 화면에는
            //    「지도를 불러오지 못했습니다」만 뜨고 상황실은 «신고가 없는 것»으로 본다.
            //    카카오 쿼터 소진은 이 에픽이 이미 리스크로 꼽은 항목인데(09 리스크 표),
            //    그 파급이 지도에 그치지 않는다는 걸 아무도 추적하지 않았다.
            //    → 지도가 없으면 «마커만» 없고 나머지는 그대로 돈다.
            if (this.mapReady) {
                // 클러스터 파라미터는 화면 폭에 따라 다름 — markerPool.CLUSTER_PROFILE 주석 참조
                this.pool = new PersonMarkerPool(
                    this.map,
                    this.isMobile ? CLUSTER_PROFILE.mobile : CLUSTER_PROFILE.desktop
                );
                this.requestPins = new RequestPinLayer(this.map);
                this.trackLayer = new TrackLayer(this.map);
                this._applyFilterToPool();
            } else {
                this.pool = null;
                this.requestPins = null;
            }

            await this.fetchRoster();
            await this.fetchRequests();
            await this.loadBoard();

            // 초기 조망은 인원핀·신고핀이 «모두» 올라온 뒤에 잡는다.
            // 예전에는 roster 직후에 맞춰서 신고핀이 경계 계산에 아예 들어가지 못했다.
            this.recenter();

            this._subscribeRealtime();
            this._consumeDeepLink();
        },

        // ?request=123 딥링크 소비 — 목록에 있으면 바로 배정 화면을 연다.
        // 한 번만 쓰고 버린다(행사 전환 때마다 다시 열리면 안 된다).
        _consumeDeepLink() {
            const id = this._pendingRequestId;
            if (!id) return;
            this._pendingRequestId = null;
            const req = this.requests.find((r) => Number(r.request_id) === id);
            if (req) this.openAssign(req);
        },

        // ── 지도 ────────────────────────────────────────────────
        _ensureMap() {
            return new Promise((resolve) => {
                if (this.mapReady) return resolve();
                this._loadKakao()
                    .then(() => {
                        kakao.maps.load(() => {
                            const el = document.getElementById('control-map');
                            if (!el) { this.mapError = true; return resolve(); }
                            this.map = new kakao.maps.Map(el, {
                                center: new kakao.maps.LatLng(37.5665, 126.978),
                                level: 6,
                            });
                            this.map.addControl(new kakao.maps.ZoomControl(),
                                kakao.maps.ControlPosition.RIGHT);
                            this.mapReady = true;
                            resolve();
                        });
                    })
                    .catch(() => { this.mapError = true; resolve(); });
            });
        },

        _loadKakao() {
            return new Promise((resolve, reject) => {
                if (window.kakao && window.kakao.maps) return resolve();
                const s = document.createElement('script');
                s.src = `//dapi.kakao.com/v2/maps/sdk.js?appkey=${KAKAO_KEY}&libraries=services,clusterer,drawing&autoload=false`;
                s.onload = () => resolve();
                s.onerror = () => reject(new Error('kakao sdk load fail'));
                document.head.appendChild(s);
            });
        },

        // ── roster(폴백·초기로드) ───────────────────────────────
        // 조망(recenter)은 여기서 하지 않는다 — 폴링도 이 함수를 부르는데, 그때마다
        // 지도가 튀면 관제사가 보고 있던 화면을 12초마다 빼앗긴다. 조망은 진입 시
        // 한 번(selectProject)과 「전체보기」 버튼에서만.
        async fetchRoster() {
            if (!this.hasProject) return;
            this.loadingRoster = true;
            try {
                const res = await window.axios.get(
                    `/api/events/${this.selectedProjectId}/participants`,
                    { headers: { Accept: 'application/json' } }
                );
                const rows = res.data.data || [];
                this.roster = rows;
                if (this.pool) {
                    rows.forEach((row) => this.pool.upsert(row));
                }
                this._refreshCounts();
            } catch (e) {
                console.error('[control] roster 조회 실패', e);
            } finally {
                this.loadingRoster = false;
            }
        },

        // 관제 진입 시 기존 미완료 신고(pending/in_progress) 초기 로드 —
        // 라이브 브로드캐스트(_onRequestCreated)로만 채워지던 목록의 빈-초기상태 버그 보완.
        async fetchRequests() {
            if (!this.hasProject) return;
            try {
                const res = await window.axios.get(
                    `/api/events/${this.selectedProjectId}/requests`,
                    { headers: { Accept: 'application/json' } }
                );
                const rows = res.data.data || [];
                if (this.requestPins) rows.forEach((r) => this.requestPins.upsert(r));
                this.requests = rows; // 이미 최신순(desc)
                this.requestCount = this._requestCount();
            } catch (e) {
                console.error('[control] 신고 조회 실패', e);
            }
        },

        // 참가자 행사 역할 배정(controller/admin) → assignRole API 후 roster 갱신
        async assignParticipantRole(userId, role) {
            if (!this.hasProject) return;
            this.assigningUserId = userId;
            try {
                await window.axios.patch(
                    `/api/events/${this.selectedProjectId}/participants/${userId}`,
                    { role },
                    { headers: { Accept: 'application/json' } }
                );
                await this.fetchRoster();
            } catch (e) {
                console.error('[control] 역할 배정 실패', e);
                await this.fetchRoster(); // 실패 시 셀렉트 값 원복
            } finally {
                this.assigningUserId = null;
            }
        },

        // ── 실시간 ──────────────────────────────────────────────
        async _subscribeRealtime() {
            const echo = await this._waitForEcho();
            if (!echo) { this._startPolling(); return; }

            const pid = this.selectedProjectId;
            this._subscribedProjectId = pid; // teardown 이 읽을 단일 출처
            // presence: 위치
            // 🔑 presence «멤버십»도 쓴다. 예전에는 .participant.location 만 듣고
            //    leaving 을 무시해서, 앱을 끈 사람의 핀이 임계 시간(2분)이 지나도
            //    선명한 채로 남았다 — 상황실은 그 사람이 아직 현장에 있다고 읽는다.
            this._locCh = echo.join(`event.${pid}.locations`)
                .here((members) => this._onPresenceSync(members))
                .joining((member) => this._onPresenceJoin(member))
                .leaving((member) => this._onPresenceLeave(member))
                .listen('.participant.location', (e) => this._onLocation(e));
            this._startPresenceDecay();
            // private control: 신규 신고 + 지령 상태 갱신 + 신고 자체의 상태 갱신
            this._ctrlCh = echo.private(`event.${pid}.control`)
                .listen('.request.created', (e) => this._onRequestCreated(e))
                .listen('.dispatch.updated', (e) => this._onDispatchUpdated(e))
                .listen('.request.status.updated', (e) => this._onRequestStatusUpdated(e));

            // 연결 상태 → 인디케이터 + 폴백
            const conn = echo.connector?.pusher?.connection;
            if (conn) {
                conn.bind('state_change', ({ current }) => {
                    if (current === 'connected') { this.wsState = 'ws'; this._stopPolling(); }
                    else if (['unavailable', 'failed', 'disconnected'].includes(current)) {
                        this._startPolling();
                    }
                });
                if (conn.state === 'connected') this.wsState = 'ws';
            } else {
                this._startPolling();
            }
        },

        // Phase0/1 QA 교훈: 마운트 시점에 window.Echo 가 아직 없을 수 있음 → 최대 ~3초 대기
        _waitForEcho() {
            return new Promise((resolve) => {
                if (window.Echo) return resolve(window.Echo);
                let waited = 0;
                const t = setInterval(() => {
                    waited += 100;
                    if (window.Echo) { clearInterval(t); resolve(window.Echo); }
                    else if (waited >= 3000) { clearInterval(t); resolve(null); }
                }, 100);
            });
        },

        // ── presence 멤버십 ─────────────────────────────────────
        // 페이로드는 {user_id, role} 만이다(ADR-0004 — 연락처는 presence 에 싣지 않는다).

        _onPresenceSync(members) {
            if (!this.pool) return;
            const here = new Set((members || []).map((m) => Number(m.user_id)));
            for (const [userId, entry] of this.pool.markers) {
                if (here.has(Number(userId))) entry.left = false;
                else this.pool.markLeft(userId);
            }
            this._refreshCounts();
        },

        _onPresenceJoin(member) {
            if (!this.pool || !member) return;
            const entry = this.pool.markers.get(member.user_id);
            if (entry) {
                entry.left = false;
                this.pool._applyState(entry);
                this._refreshCounts();
            }
            // 풀에 없으면 아직 좌표가 없는 사람이다. 첫 .participant.location 이
            // 오면 upsert 로 생긴다 — 좌표 없는 마커를 만들지 않는 규칙 그대로.
        },

        _onPresenceLeave(member) {
            if (!this.pool || !member) return;
            if (this.pool.markLeft(member.user_id)) this._refreshCounts();
        },

        // 시간이 지나면 online → stale → offline 으로 «저절로» 내려앉아야 한다.
        // 아무 이벤트도 안 오는 동안 화면이 갱신될 유일한 계기가 이 타이머다.
        _startPresenceDecay() {
            if (this._presenceTimer) return;
            this._presenceTimer = setInterval(() => {
                if (!this.pool) return;
                if (this.pool.refreshPresence()) this._refreshCounts();
            }, PRESENCE_DECAY_MS);
        },

        _stopPresenceDecay() {
            if (this._presenceTimer) { clearInterval(this._presenceTimer); this._presenceTimer = null; }
        },

        _onLocation(payload) {
            // 지도가 없으면 그릴 마커도 없다. 실시간 «수신»은 계속돼야 하므로 조용히 지나간다.
            if (!this.pool) return;

            // 좌표만 이동(리렌더 금지). 풀에 없으면(신규 인원) upsert 로 생성.
            const moved = this.pool.move(
                payload.user_id, payload.latitude, payload.longitude, payload.accuracy ?? null
            );
            if (!moved) {
                this.pool.upsert({
                    user_id: payload.user_id,
                    name: null,
                    role: payload.role,
                    status: 'active',
                    last_lat: payload.latitude,
                    last_lng: payload.longitude,
                    last_accuracy: payload.accuracy ?? null,
                    last_seen_at: payload.recorded_at,
                });
            } else {
                // last_seen 갱신 위해 row 동기화
                const entry = this.pool.markers.get(payload.user_id);
                if (entry) {
                    entry.row.last_seen_at = payload.recorded_at;
                    entry.row.last_lat = payload.latitude;
                    entry.row.last_lng = payload.longitude;
                    entry.row.last_accuracy = payload.accuracy ?? null;
                    // 위치가 왔으니 다시 online 이다. 이 재판정이 없으면 한 번
                    // 어두워진 마커는 돌아와도 계속 어두운 채로 남는다.
                    this.pool._applyState(entry);
                }
            }
            this._refreshCounts();
        },

        _onRequestCreated(payload) {
            // 🔑 목록 갱신이 «먼저»다. 핀은 지도가 있을 때만 — 예전에는 핀부터 찍어서
            //    지도가 없으면 여기서 터지고 신규 신고가 목록에도 안 올라갔다.
            this.requests.unshift(payload);
            if (this.requestPins) this.requestPins.upsert(payload);
            this.requestCount = this._requestCount();
        },

        /** 신고 건수 — 지도가 없으면 핀을 셀 수 없으므로 목록 길이로 센다. */
        _requestCount() {
            return this.requestPins ? this.requestPins.count() : this.requests.length;
        },

        // 신고 «자체»의 상태 변화(취소·완료). 지령 상태(.dispatch.updated)와 별개다.
        //
        // 🔑 이 이벤트가 신고자 채널로만 가던 때는 상황실이 취소를 끝까지 몰랐다 —
        //    취소된 신고의 핀이 지도에 계속 떠 있고, 아무도 안 가는 좌표에 인력이
        //    묶인다. 목록에서 지우는 게 아니라 «관제 화면 전체»에서 걷어내야 한다.
        _onRequestStatusUpdated(payload) {
            if (!payload || payload.request_id == null) return;

            if (payload.status !== 'cancelled') {
                // 취소가 아니면 목록은 그대로 둔다. 보드는 같은 전이에서 나가는
                // .dispatch.updated 가 이미 다시 읽으므로 여기서 또 부르지 않는다.
                return;
            }

            this._removeRequest(payload.request_id);
            // 서버가 딸린 활성 지령을 자동 회수하므로 보드도 다시 읽는다.
            this.loadBoard();
        },

        // 신고를 관제 화면에서 걷어낸다 — 목록 · 지도핀 · 상태맵 · 펼침 · 배정 패널.
        _removeRequest(id) {
            const rid = Number(id);
            this.requests = this.requests.filter((r) => Number(r.request_id) !== rid);
            this._removeRequestPin(rid);
            delete this.requestStatusMap[id];
            this.requestCount = this._requestCount();

            if (Number(this.expandedRequestId) === rid) this.expandedRequestId = null;

            // 배정 패널이 «그 신고»를 열어 둔 채면 닫는다. 안 닫으면 상황실이 이미
            // 취소된 신고에 대원을 발령하고, 서버가 422 를 돌려줄 때까지 모른다.
            if (this.assign.open && Number(this.assign.request?.request_id) === rid) {
                this.closeAssign();
            }
        },

        // 신고 고정핀 제거.
        // ⚠ RequestPinLayer 에 remove(id) 가 없어 오버레이를 여기서 직접 내린다.
        //   markerPool.js 에 remove(id) 가 생기면 이 메서드를 그걸로 교체할 것.
        _removeRequestPin(id) {
            if (!this.requestPins) return;
            const pins = this.requestPins.pins;
            const key = [...pins.keys()].find((k) => Number(k) === Number(id));
            if (key === undefined) return;
            const overlay = pins.get(key);
            if (overlay && typeof overlay.setMap === 'function') overlay.setMap(null);
            pins.delete(key);
        },

        // ── FE-3.3: 지령 배정 패널 ──────────────────────────────
        async openAssign(req, mode = 'primary') {
            this.assign.open = true;
            this.assign.mode = mode === 'support' ? 'support' : 'primary';
            this.assign.request = req;
            this.assign.selectedId = null;
            this.assign.note = '';
            this.assign.error = '';
            this.assign.confirming = false;
            this.assign.paramedics = [];
            this.assign.loading = true;
            try {
                const res = await window.axios.get(
                    `/api/requests/${req.request_id}/available-paramedics`,
                    { headers: { Accept: 'application/json' } }
                );
                this.assign.paramedics = res.data.data || [];
            } catch (e) {
                this.assign.error = '가용 구급대원 조회에 실패했습니다.';
            } finally {
                this.assign.loading = false;
            }
        },

        closeAssign() {
            this.assign.open = false;
            this.assign.mode = 'primary';
            this.assign.request = null;
            this.assign.paramedics = [];
            this.assign.selectedId = null;
            this.assign.note = '';
            this.assign.error = '';
            this.assign.submitting = false;
            this.assign.confirming = false;
        },

        fmtDistance(m) {
            if (m == null) return '거리 미상';
            return m >= 1000 ? `${(m / 1000).toFixed(1)}km` : `${m}m`;
        },

        // 1단계: 확인 요청. 2단계(submitAssign)에서 실제 발령.
        requestAssignConfirm() {
            if (!this.assign.selectedId || this.assign.submitting) return;
            this.assign.error = '';
            this.assign.confirming = true;
        },
        cancelAssignConfirm() { this.assign.confirming = false; },

        selectedParamedic() {
            return this.assign.paramedics.find((m) => m.user_id === this.assign.selectedId) || null;
        },

        async submitAssign() {
            if (!this.assign.selectedId || this.assign.submitting) return;
            const rid = this.assign.request.request_id;
            const support = this.assign.mode === 'support';
            this.assign.submitting = true;
            this.assign.error = '';
            try {
                await window.axios.post(
                    `/api/requests/${rid}/dispatch${support ? '/support' : ''}`,
                    { paramedic_id: this.assign.selectedId, note: this.assign.note || null },
                    { headers: { Accept: 'application/json' } }
                );
                // 신고행 즉시 반영(보드 왕복 동안의 빈 칸 방지). 확정은 loadBoard 가 한다.
                const entry = this._statusEntry(rid);
                if (support) entry.supports += 1;
                else entry.primary = 'assigned';
                this.requestStatusMap[rid] = entry;

                this.closeAssign();
                await this.loadBoard();
            } catch (e) {
                const status = e.response?.status;
                // OI-2 동시 배정 경합 / 중복 대원 / 주담당 없음 → 422(서버 메시지가 더 정확하다)
                this.assign.error = status === 422
                    ? (e.response?.data?.message || '이미 배정된 신고입니다.')
                    : (status === 403 ? '배정 권한이 없습니다.' : '지령 발령에 실패했습니다.');
                this.assign.confirming = false; // 실패 시 확인 단계로 되돌리지 않고 리스트로
            } finally {
                this.assign.submitting = false;
            }
        },

        // ── FE-3.3: 출동 현황 보드 ──────────────────────────────
        async loadBoard() {
            if (!this.hasProject) return;
            this.board.loading = true;
            try {
                const res = await window.axios.get(
                    `/api/events/${this.selectedProjectId}/dispatches`,
                    { headers: { Accept: 'application/json' } }
                );
                const d = res.data.data || {};
                this.board.counts = d.counts || {};
                this.board.active = d.active || [];
                this.board.history = d.history || [];
                // 신고행 상태 맵 갱신(활성 지령 기준). 주담당은 «상태», 보조는 «머릿수».
                const map = {};
                (d.active || []).forEach((x) => {
                    const e = map[x.request_id] || (map[x.request_id] = { primary: null, supports: 0 });
                    if (x.is_primary === false) e.supports += 1;
                    else e.primary = x.status;
                });
                this.requestStatusMap = map;
            } catch (e) {
                console.error('[control] 출동보드 조회 실패', e);
            } finally {
                this.board.loading = false;
            }
        },

        _onDispatchUpdated(payload) {
            // 실시간 상태 갱신: 보드 재조회(controller 전용, 저빈도) + 신고행 상태 반영
            if (payload && payload.request_id) {
                const entry = this._statusEntry(payload.request_id);
                // 🔑 보조의 전이는 주담당 칸을 «건드리지 않는다». 보조가 완료를 눌렀다고
                //    신고행이 [완료]로 보이면, 아직 현장에 있는 주담당이 화면에서 사라진다.
                //    보조 인원수는 바로 아래 loadBoard() 가 활성 지령 기준으로 확정한다.
                //    (is_primary 가 없는 옛 페이로드는 주담당으로 본다 — 기존 동작 유지)
                if (payload.is_primary !== false) {
                    // rejected 면 재지령 필요 → 신고행 경고 표시
                    entry.primary = payload.status;
                }
                this.requestStatusMap[payload.request_id] = entry;
            }
            this.loadBoard();
        },

        boardCount(s) { return this.board.counts?.[s] || 0; },

        /** 신고행 상태 엔트리(없으면 빈 엔트리를 «복사»로 만든다 — 맵을 몰래 채우지 않는다). */
        _statusEntry(id) {
            const cur = this.requestStatusMap[id];

            return cur ? { primary: cur.primary ?? null, supports: cur.supports ?? 0 }
                : { primary: null, supports: 0 };
        },

        /** 이 신고의 «주담당» 지령 상태. 화면의 배지·배정 버튼이 전부 이 값을 본다. */
        requestStatus(id) { return this.requestStatusMap[id]?.primary ?? null; },

        /** 이 신고에 붙은 «보조» 인원수. */
        supportCount(id) { return this.requestStatusMap[id]?.supports ?? 0; },

        // 보조는 주담당이 «현장에 붙어 있는 동안»에만 추가할 수 있다. 서버도 같은 규칙이라
        // (주담당 없는 신고에는 보조 배정 거부) 버튼을 먼저 숨겨 422 를 만나지 않게 한다.
        canAddSupport(id) {
            const s = this.requestStatus(id);

            return ['assigned', 'accepted', 'en_route', 'arrived'].includes(s);
        },

        // 「이 신고를 (다시) 배정할 수 있는가」의 단일 출처 — 목록 정렬·미배정 카운트·
        // 배정 버튼이 전부 여기를 본다.
        //
        // 🔑 cancelled(회수)가 여기 빠지면 회수한 신고를 다시 배정할 수 없어 회수 기능
        //    자체가 무의미해진다. rejected(대원 거절)와 완전히 같은 자리에 둔다 —
        //    「담당자가 없어서 지금 누군가 보내야 하는 신고」라는 점에서 동일하다.
        needsAssign(id) {
            const s = this.requestStatus(id);
            return !s || s === 'rejected' || s === 'cancelled';
        },

        // 거절·회수 뒤에는 «다시» 보내는 것이라 [재배정]으로 부른다.
        assignLabel(id) { return this.requestStatus(id) ? '재배정' : '배정'; },

        // 보드 행의 «구분» 배지 — 이 지령이 그 신고의 책임자인지 보조인지.
        kindLabel(d) { return d && d.is_primary === false ? '보조' : '주담당'; },
        kindBadge(d) {
            return d && d.is_primary === false
                ? 'bg-gray-100 text-gray-500'
                : 'bg-blue-50 text-blue-700';
        },

        // ── 지령 회수 (ADR-0007) ────────────────────────────────
        // 🔑 arrived 에서는 회수 버튼을 «노출하지 않는다». 서버 전이표가 막아 422 를
        //    돌려주므로(도착 기록을 없던 일로 만들지 않는다), 눌리는 버튼으로 두면
        //    상황실은 "왜 안 되지"만 배우게 된다.
        canRecall(d) {
            return !!d && !!d.dispatch_id && d.status !== 'arrived';
        },

        // 1단계: 확인 요청. 2단계(submitRecall)에서 실제 회수.
        requestRecallConfirm(d) {
            if (!this.canRecall(d)) return;
            this.recall.dispatch = d;
            this.recall.reason = '';
            this.recall.error = '';
            this.recall.submitting = false;
        },
        cancelRecallConfirm() {
            this.recall.dispatch = null;
            this.recall.reason = '';
            this.recall.error = '';
        },

        async submitRecall() {
            const d = this.recall.dispatch;
            if (!d || this.recall.submitting) return;
            this.recall.submitting = true;
            this.recall.error = '';
            try {
                // 새 엔드포인트가 아니라 기존 상태 전이 API 다 — 사유는 note 로 들어간다.
                await window.axios.patch(
                    `/api/dispatches/${d.dispatch_id}/status`,
                    { status: 'cancelled', note: this.recall.reason || null },
                    { headers: { Accept: 'application/json' } }
                );
                // 회수된 신고는 즉시 «미배정»으로 돌아가야 한다. loadBoard() 가 활성
                // 지령 기준으로 맵을 통째로 다시 만들지만, 그 왕복 동안 옛 상태가
                // 남아 [배정] 버튼이 사라져 있는 것을 막는다.
                //
                // ⚠️ 엔트리를 통째로 지우지 않는다 — 보조를 회수했는데 주담당 상태까지
                //    사라지면 그 왕복 동안 「아무도 안 갔다」로 보인다.
                const entry = this._statusEntry(d.request_id);
                if (d.is_primary === false) entry.supports = Math.max(0, entry.supports - 1);
                else entry.primary = null;
                this.requestStatusMap[d.request_id] = entry;
                this.cancelRecallConfirm();
                await this.loadBoard();
            } catch (e) {
                const status = e.response?.status;
                // 422: 이미 끝났거나(경합) arrived 로 넘어간 지령 / 403: 대원 본인 등
                this.recall.error = status === 422
                    ? (e.response?.data?.message || '회수할 수 없는 지령입니다.')
                    : (status === 403 ? '회수 권한이 없습니다.' : '지령 회수에 실패했습니다.');
            } finally {
                this.recall.submitting = false;
            }
        },

        // 기록 다운로드 URL(BE-4.1) — 세션 쿠키로 GET 다운로드
        reportUrl(kind) {
            return `/api/events/${this.selectedProjectId}/report/${kind}.csv`;
        },

        // WS 폴백. roster 만 돌리면 안 된다 — 신규 신고(.request.created)와 지령 상태
        // (.dispatch.updated)도 WS 로만 들어오므로, 폴링 중에는 신고가 화면에 아예 안 뜬다.
        // 구조 도메인에서 "떴는데 못 봄"이 최악의 실패라 세 개를 같이 돌린다.
        async _poll() {
            if (!this.hasProject) return;
            try {
                await this.fetchRoster();
                await this.fetchRequests();
                await this.loadBoard();
            } catch (e) {
                console.error('[control] 폴백 폴링 실패', e);
            }
        },

        _startPolling() {
            if (this._pollTimer) return;
            this.wsState = 'polling';
            this._pollTimer = setInterval(() => this._poll(), POLL_INTERVAL_MS);
            this._poll(); // 첫 주기(12초)를 기다리지 않고 즉시 1회
        },
        _stopPolling() {
            if (this._pollTimer) { clearInterval(this._pollTimer); this._pollTimer = null; }
        },

        // 구독 해제는 반드시 "실제로 구독한 행사 id"(_subscribedProjectId)를 쓴다.
        // selectedProjectId 를 읽으면 안 된다 — selectProject() 가 새 id 를 먼저 대입한 뒤
        // teardown 을 부르므로, 아직 구독하지도 않은 새 채널을 leave 하고 이전 행사 구독이
        // 살아남는다. event.{id}.control 은 신고자 연락처를 싣기 때문에(ADR-0004)
        // 이전 행사의 신고가 연락처째로 새 화면에 계속 흘러들어온다.
        _teardownRealtime() {
            this._stopPolling();
            this._stopPresenceDecay();
            const pid = this._subscribedProjectId;
            if (window.Echo && pid != null) {
                try { window.Echo.leave(`event.${pid}.locations`); } catch (e) { /* noop */ }
                try { window.Echo.leave(`event.${pid}.control`); } catch (e) { /* noop */ }
            }
            this._subscribedProjectId = null;
            this._locCh = null;
            this._ctrlCh = null;
        },

        // ── 역할 필터 ───────────────────────────────────────────
        toggleRole(role) {
            this.roleFilter[role] = !this.roleFilter[role];
            this._applyFilterToPool();
        },
        allRoles() {
            this.roleOrder.forEach((r) => { this.roleFilter[r] = true; });
            this._applyFilterToPool();
        },
        // 모바일 필터 칩용 — 지령 수령 가능 역할(EventRole::canReceiveDispatch)만 남긴다.
        onlyMedics() {
            const medics = new Set(['paramedic', 'volunteer_medic']);
            this.roleOrder.forEach((r) => { this.roleFilter[r] = medics.has(r); });
            this._applyFilterToPool();
        },
        clearRoles() {
            this.roleOrder.forEach((r) => { this.roleFilter[r] = false; });
            this._applyFilterToPool();
        },
        // ── 이동 궤적 (M-25) ───────────────────────────────────
        //
        // 🔑 **켤 때만 부른다.** 관제는 12초마다 roster 를 폴링하는데 궤적까지 같이
        //    끌면 매번 수천 점이 오간다. 궤적은 「지금 어디」와 달리 «초 단위로
        //    달라지지 않는» 정보라 그럴 이유가 없다.
        async toggleTracks() {
            this.showTracks = !this.showTracks;

            if (!this.showTracks) {
                if (this.trackLayer) this.trackLayer.setVisible(false);

                return;
            }

            await this.fetchTracks();
            if (this.trackLayer) this.trackLayer.setVisible(true);
        },

        async setTrackMinutes(minutes) {
            this.trackMinutes = Number(minutes);
            if (this.showTracks) await this.fetchTracks();
        },

        async fetchTracks() {
            if (!this.hasProject || !this.trackLayer) return;

            this.loadingTracks = true;
            this.trackError = null;
            try {
                const res = await window.axios.get(
                    `/api/events/${this.selectedProjectId}/tracks`,
                    {
                        params: { minutes: this.trackMinutes },
                        headers: { Accept: 'application/json' },
                    }
                );
                this.tracks = res.data.data?.tracks || [];

                // 🔑 색은 그 사람의 «역할»을 따른다. 마커와 다른 색이면 관제사가
                //    선과 핀을 이어 보지 못한다.
                const roleByUser = new Map(this.roster.map((r) => [r.user_id, r.role]));
                this.trackLayer.render(this.tracks, (userId) => roleByUser.get(userId));
                this.trackLayer.setVisible(this.showTracks);
            } catch (e) {
                console.error('[control] 궤적 조회 실패', e);
                this.trackError = '궤적을 불러오지 못했습니다.';
            } finally {
                this.loadingTracks = false;
            }
        },

        /** 지금 화면에 그려진 궤적의 «원본» 점 수. 「일부만 보고 있다」를 말하기 위해. */
        trackPointsShown() {
            return this.tracks.reduce((sum, t) => sum + t.points.length, 0);
        },
        trackPointsTotal() {
            return this.tracks.reduce((sum, t) => sum + (t.count ?? t.points.length), 0);
        },

        toggleHideOffline() {
            this.hideOffline = !this.hideOffline;
            if (this.pool) this.pool.setHideOffline(this.hideOffline);
        },
        _applyFilterToPool() {
            if (!this.pool) return;
            const visible = new Set(this.roleOrder.filter((r) => this.roleFilter[r]));
            this.pool.setVisibleRoles(visible);
            this.pool.setHideOffline(this.hideOffline);
        },

        _refreshCounts() {
            if (this.pool) {
                this.roleCounts = this.pool.counts();
                this.onlineCount = this.pool.onlineTotal();

                return;
            }

            // 지도가 없어도 «인력 현황»은 보여야 한다. 마커 풀 대신 roster 로 직접 센다 —
            // 예전에는 여기서 그냥 빠져나가 전원 0 명으로 표시됐다.
            const counts = {};
            let online = 0;

            this.roster.forEach((row) => {
                counts[row.role] = counts[row.role] || { online: 0, total: 0 };
                counts[row.role].total++;
                if (presenceState(row.last_seen_at) === 'online') {
                    counts[row.role].online++;
                    online++;
                }
            });

            this.roleCounts = counts;
            this.onlineCount = online;
        },

        roleOnline(role) { return (this.roleCounts[role]?.online) || 0; },
        roleTotal(role) { return (this.roleCounts[role]?.total) || 0; },

        toggleRail() { this.railCollapsed = !this.railCollapsed; },

        // 전체보기 = 지도 중심·배율을 «핀이 그리는 경계»에 맞춘다.
        //
        // 🔑 인원핀과 신고핀을 «같은» 경계에 넣는다. 예전에는 인원핀만 맞춰서, 인원이
        //    한 명도 없는 행사(대부분의 초기 상태)면 경계 자체가 안 잡혀 지도가 서울시청
        //    기본 좌표에 머물렀다 — 신고가 강원도에 찍혀 있어도 화면 밖이다.
        recenter() {
            if (!this.map) return;
            const bounds = new kakao.maps.LatLngBounds();
            let any = false;
            if (this.pool && this.pool.extendBounds(bounds)) any = true;
            if (this.requestPins && this.requestPins.extendBounds(bounds)) any = true;
            if (!any) return; // 핀이 하나도 없으면 기본 중심을 그대로 둔다

            const p = this._mapPadding();
            this.map.setBounds(bounds, p.top, p.right, p.bottom, p.left);

            // 핀이 하나거나 서로 붙어 있으면 setBounds 가 최대 배율까지 파고들어
            // «여기가 어디인지»를 잃는다(주변 지형지물이 화면에서 사라진다).
            if (typeof this.map.getLevel === 'function' && this.map.getLevel() < MIN_FIT_LEVEL) {
                this.map.setLevel(MIN_FIT_LEVEL);
            }
        },

        // 지도에서 «가려지는» 만큼의 여백. 모바일은 바텀시트가 하단을 덮으므로
        // 그만큼 아래를 비워야 핀이 시트 밑에 깔리지 않는다.
        _mapPadding() {
            if (!this.isMobile) return { top: 24, right: 24, bottom: 24, left: 24 };
            const h = window.innerHeight || 0;
            const bottom = this.sheetSnap === 'full' ? Math.round(h * 0.9)
                : this.sheetSnap === 'half' ? Math.round(h * 0.45)
                    : 96;
            return { top: 24, right: 24, bottom: bottom + 24, left: 24 };
        },

        // 핸들 탭 = 조망 → 인지 → 상세 → 조망 순환.
        cycleSheet() {
            const next = { peek: 'half', half: 'full', full: 'peek' };
            this.sheetSnap = next[this.sheetSnap] || 'peek';
        },
        setSheet(snap) { this.sheetSnap = snap; },
        setSheetTab(tab) {
            this.sheetTab = tab;
            if (this.sheetSnap === 'peek') this.sheetSnap = 'half';
        },

        // 리스트에서 신고를 고르면 지도를 그 지점으로 옮기고 시트를 내려 위치를 보여준다.
        // "그게 어디냐"에 답하는 동선 — 탭 구조였다면 화면을 갈아타야 했을 자리다.
        focusRequestOnMap(req) {
            if (!this.map || req.latitude == null || req.longitude == null) return;
            this.map.setCenter(new kakao.maps.LatLng(Number(req.latitude), Number(req.longitude)));
            this.map.setLevel(3);
            if (this.isMobile) this.sheetSnap = 'peek';
        },

        toggleRequest(id) { this.expandedRequestId = this.expandedRequestId === id ? null : id; },
        fmtTime(iso) {
            if (!iso) return '';
            try { return new Date(iso).toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' }); }
            catch (e) { return ''; }
        },
    },

    template: `
<!--
  헤더 행 높이 = 56px + 상단 safe-area. 앱/홈화면 PWA 는 화면 최상단부터 시작하므로
  이걸 더하지 않으면 행사 선택 드롭다운이 상태바·다이나믹 아일랜드 밑으로 들어간다.
  브라우저에서는 env() 가 0 이라 기존과 동일하다.
-->
<div class="h-[100dvh] w-full grid grid-rows-[calc(56px+env(safe-area-inset-top))_1fr] bg-gray-100 text-gray-900"
     :style="gridStyle">

  <!-- HEADER -->
  <header class="col-span-2 row-start-1 h-full bg-white border-b border-gray-200 flex items-center justify-between px-3 lg:px-4 gap-2"
          style="padding-top: env(safe-area-inset-top)">
    <div class="flex items-center gap-2 lg:gap-3 min-w-0">
      <a v-if="backUrl" :href="backUrl" class="flex items-center gap-1 pl-1 pr-2 py-1.5 rounded-md hover:bg-gray-100 text-gray-500 text-sm font-medium" title="대시보드로 돌아가기">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        <span class="hidden sm:inline">대시보드</span>
      </a>
      <span v-if="backUrl" class="h-5 w-px bg-gray-200 hidden lg:block"></span>
      <!-- 레일은 데스크톱 전용. 모바일은 시트가 그 역할을 하므로 햄버거를 숨긴다 -->
      <button v-if="!isMobile" @click="toggleRail" class="p-1.5 rounded hover:bg-gray-100 text-gray-500" title="사이드바">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <span class="text-base font-bold whitespace-nowrap hidden sm:inline">실시간 관제</span>
      <select v-if="projects.length" :value="selectedProjectId" @change="selectProject($event.target.value)"
              class="text-sm font-medium border border-gray-300 rounded-md px-2 py-1.5 min-w-0 max-w-[150px] lg:max-w-[220px] focus:ring-2 focus:ring-blue-500">
        <option :value="null" disabled>행사 선택</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <span v-else class="text-sm text-gray-400">활성 행사 없음</span>
    </div>
    <div class="flex items-center gap-2 lg:gap-4 text-sm flex-shrink-0">
      <!-- 기록 다운로드(BE-4.1) — 행사 종료 후 데스크톱 작업이라 모바일에서는 숨긴다 -->
      <div v-if="hasProject && !isMobile" class="relative" @mouseleave="reportMenu=false">
        <button @click="reportMenu=!reportMenu" class="px-2.5 py-1 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 text-xs font-medium">
          기록 ▾
        </button>
        <div v-if="reportMenu" class="absolute right-0 top-full mt-1 w-40 bg-white border border-gray-200 rounded-md shadow-lg py-1 z-20">
          <template v-if="reportsDownloadable">
            <a :href="reportUrl('requests')" class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">신고 기록 CSV</a>
            <a :href="reportUrl('dispatches')" class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">지령 기록 CSV</a>
            <a :href="reportUrl('tracks')" class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">동선 기록 CSV</a>
          </template>
          <!-- 링크를 «숨기기»만 하면 기능이 사라진 것으로 읽혀 상황실이 찾아 헤맨다.
               어디서 받으면 되는지까지 말한다. -->
          <p v-else class="px-3 py-2 text-xs leading-relaxed text-gray-500 w-48">
            앱에서는 파일을 내려받을 수 없습니다.<br>
            <b class="text-gray-700">PC 관제 화면</b>에서 받아 주세요.
          </p>
        </div>
      </div>
      <span class="text-gray-600 whitespace-nowrap text-xs lg:text-sm">온라인 <b class="text-gray-900">{{ onlineCount }}</b></span>
      <span class="text-gray-600 whitespace-nowrap text-xs lg:text-sm hidden sm:inline">신고 <b class="text-gray-900">{{ requestCount }}</b></span>
      <span class="inline-flex items-center gap-1.5 px-2 lg:px-2.5 py-1 rounded-full text-xs font-medium whitespace-nowrap"
            :class="wsState==='ws' ? 'bg-green-100 text-green-700' : (wsState==='polling' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500')">
        <span class="w-2 h-2 rounded-full" :class="wsState==='ws' ? 'bg-green-500' : (wsState==='polling' ? 'bg-amber-500' : 'bg-gray-400')"></span>
        <span class="hidden sm:inline">{{ wsState==='ws' ? '실시간' : (wsState==='polling' ? '폴링' : '연결중') }}</span>
      </span>
    </div>
  </header>

  <!-- L-RAIL — 데스크톱 전용. 모바일에서는 시트의 [인력] 탭이 대체한다.
       특히 「참가자 역할 배정」은 모바일에서 노출하지 않는다: 이 셀렉트는 controller 까지
       옵션에 포함하고 서버(assignRole)도 허용하므로 controller 자기증식 경로가 된다.
       승격된 사람은 즉시 event.{id}.control 을 구독해 참가자 위치·신고자 연락처를 받는다.
       고정 부스라면 몰라도 현장 폰(잠금 없이 놓아둔/빌려준)에서는 감수할 수 없다.
       역할 배정은 행사 전 셋업 작업이고 /admin/projects/{id}/participants 에 이미 있다. -->
  <aside class="row-start-2 col-start-1 bg-white border-r border-gray-200 overflow-y-auto"
         v-show="!railCollapsed && !isMobile">
    <div class="p-3 border-b border-gray-100">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-sm font-bold text-gray-700">역할 필터</h2>
        <div class="flex gap-1">
          <button @click="allRoles" class="text-xs px-2 py-0.5 rounded bg-gray-100 hover:bg-gray-200">전체</button>
          <button @click="clearRoles" class="text-xs px-2 py-0.5 rounded bg-gray-100 hover:bg-gray-200">해제</button>
        </div>
      </div>
      <label v-for="role in roleOrder" :key="role"
             class="flex items-center gap-2 py-1.5 px-1 rounded hover:bg-gray-50 cursor-pointer">
        <input type="checkbox" :checked="roleFilter[role]" @change="toggleRole(role)"
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: roleColor(role) }"></span>
        <span class="text-sm text-gray-700 flex-1">{{ roleLabel(role) }}</span>
        <span class="text-xs text-gray-400" :title="roleOnline(role) + '/' + roleTotal(role)">({{ roleOnline(role) }})</span>
      </label>
    </div>
    <!-- 이동 궤적 (M-25) -->
    <div class="p-3 border-t border-gray-100">
      <div class="flex items-center justify-between">
        <label class="text-sm font-bold text-gray-700 flex items-center gap-1.5 cursor-pointer">
          <input type="checkbox" :checked="showTracks" @change="toggleTracks"
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
          이동 궤적
        </label>
        <select :value="trackMinutes" @change="setTrackMinutes($event.target.value)"
                :disabled="!showTracks"
                class="text-xs border-gray-300 rounded py-0.5 pl-1.5 pr-6 disabled:bg-gray-50 disabled:text-gray-400">
          <option :value="30">최근 30분</option>
          <option :value="60">최근 1시간</option>
          <option :value="180">최근 3시간</option>
          <option :value="720">최근 12시간</option>
        </select>
      </div>
      <p v-if="loadingTracks" class="mt-1 text-xs text-gray-400">불러오는 중…</p>
      <p v-else-if="trackError" class="mt-1 text-xs text-red-600 font-bold">{{ trackError }}</p>
      <!--
        🔑 «몇 점을 보고 있는지» 말한다. 서버가 사람당 500점으로 솎으므로,
           말하지 않으면 관제사는 지금 보는 선을 원본이라고 믿는다.
      -->
      <p v-else-if="showTracks" class="mt-1 text-xs text-gray-400">
        <template v-if="tracks.length">
          {{ tracks.length }}명 · {{ trackPointsShown() }}점
          <span v-if="trackPointsTotal() > trackPointsShown()">
            (원본 {{ trackPointsTotal() }}점을 솎아 표시)
          </span>
        </template>
        <template v-else>이 시간 범위에 기록된 이동이 없습니다.</template>
      </p>
    </div>

    <div class="p-3">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-sm font-bold text-gray-700">인력 현황</h2>
        <label class="text-xs text-gray-500 flex items-center gap-1 cursor-pointer">
          <input type="checkbox" :checked="hideOffline" @change="toggleHideOffline" class="rounded border-gray-300">
          오프라인 숨기기
        </label>
      </div>
      <div v-for="role in roleOrder" :key="'c-'+role" class="flex items-center justify-between text-sm py-0.5">
        <span class="flex items-center gap-1.5 text-gray-600">
          <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: roleColor(role) }"></span>{{ roleLabel(role) }}
        </span>
        <span class="text-gray-500"><b class="text-green-600">{{ roleOnline(role) }}</b> / {{ roleTotal(role) }}</span>
      </div>
    </div>

    <!-- 참가자 역할 배정(controller/admin) -->
    <div v-if="roster.length" class="p-3 border-t border-gray-100">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-sm font-bold text-gray-700">참가자 역할 배정</h2>
        <!-- 🔴 M-5 — 「켜뒀는데 위치가 안 오는」 사람 수. 예전에는 그냥 오프라인으로 보였다. -->
        <span v-if="trackingAlertCount" class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-bold text-red-600">
          위치 권한 없음 {{ trackingAlertCount }}명
        </span>
      </div>
      <div class="space-y-1.5 max-h-56 overflow-y-auto">
        <div v-for="r in roster" :key="'a-'+r.user_id" class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: roleColor(r.role) }"></span>
          <span class="text-sm text-gray-700 flex-1 truncate" :title="r.name">{{ r.name }}</span>
          <!--
            위치 추적 상태 (M-5). 🔑 «전부» 표시한다 — unknown 을 감추면
            「문제 없음」으로 읽히고, 그게 M-5 를 다시 잃는 길이다.
            색·라벨은 서버가 준 값이라 여기에 사본이 없다.
          -->
          <span class="flex-shrink-0 text-[11px] font-semibold leading-none"
                :style="{ color: trackingColor(r.tracking_state) }"
                :title="'위치 추적: ' + trackingLabel(r.tracking_state)">
            ● {{ trackingLabel(r.tracking_state) }}
          </span>
          <select :value="r.role" @change="assignParticipantRole(r.user_id, $event.target.value)"
                  :disabled="assigningUserId === r.user_id"
                  class="text-xs border border-gray-300 rounded px-1.5 py-1 focus:ring-2 focus:ring-blue-500 disabled:opacity-50 cursor-pointer max-w-[110px]">
            <option v-for="role in roleOrder" :key="role" :value="role">{{ roleLabel(role) }}</option>
          </select>
        </div>
      </div>
    </div>
  </aside>

  <!-- 우측: MAP + BOTTOM.
       모바일은 지도가 영역 전체를 쓰고 하단 패널이 시트로 «겹친다» — 지도 컨테이너
       크기가 변하지 않으므로 map.relayout() 이 필요 없다(탭 구조였다면 매번 필요). -->
  <div class="row-start-2 col-start-2 grid overflow-hidden"
       :class="isMobile ? 'grid-rows-[1fr]' : 'grid-rows-[1fr_240px]'">
    <!-- MAP -->
    <div class="relative overflow-hidden bg-gray-200">
      <div id="control-map" class="absolute inset-0"></div>

      <div v-if="!hasProject" class="absolute inset-0 flex items-center justify-center bg-gray-50/95 z-10">
        <div class="text-center">
          <p class="text-gray-500 mb-3">관제할 행사를 선택하세요</p>
          <select :value="selectedProjectId" @change="selectProject($event.target.value)"
                  class="text-sm border-2 border-blue-500 rounded-md px-3 py-2">
            <option :value="null" disabled>행사 선택</option>
            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <p v-if="projects.length===0" class="text-xs text-gray-400 mt-3">관제 가능한 활성 행사가 없습니다.</p>
        </div>
      </div>

      <div v-else-if="loadingRoster" class="absolute inset-0 flex items-center justify-center bg-white/60 z-10">
        <div class="text-center">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <p class="mt-2 text-sm text-gray-600">전 인원 위치 불러오는 중</p>
        </div>
      </div>

      <div v-if="mapError" class="absolute inset-0 flex items-center justify-center bg-white z-20">
        <div class="text-center px-6">
          <p class="text-gray-700 font-semibold mb-1">지도를 불러오지 못했습니다.</p>
          <p class="text-gray-500 text-sm mb-3">신고 접수·출동 현황·실시간 수신은 정상 동작합니다.<br class="hidden sm:block">아래 목록에서 계속 관제할 수 있습니다.</p>
          <button @click="recenter" class="px-3 py-1.5 bg-blue-600 text-white rounded text-sm">지도 다시 시도</button>
        </div>
      </div>

      <!-- floating: 중심복귀 -->
      <button v-if="hasProject && mapReady" @click="recenter"
              class="absolute top-3 right-3 z-10 bg-white shadow rounded-md px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
        전체 보기
      </button>

      <!-- floating 필터 칩(모바일) — 역할 7개 체크박스는 375px 지도에서 구분이 안 되므로
           «전체 / 구급대만» 2단으로 줄인다. 세밀한 필터는 데스크톱 레일에 그대로 남아 있다. -->
      <div v-if="isMobile && hasProject && mapReady" class="absolute top-3 left-3 z-10 flex gap-1.5">
        <button @click="allRoles"
                class="px-3 h-9 rounded-full bg-white shadow text-xs font-semibold text-gray-700 active:bg-gray-100">
          전체
        </button>
        <button @click="onlyMedics"
                class="px-3 h-9 rounded-full bg-white shadow text-xs font-semibold text-rose-600 active:bg-rose-50">
          구급대만
        </button>
      </div>
    </div>

    <!-- BOTTOM (데스크톱 전용 2분할) -->
    <div v-if="!isMobile" class="grid grid-cols-[3fr_2fr] border-t border-gray-200 bg-white overflow-hidden">
      <!-- 신고 접수 목록 -->
      <div class="border-r border-gray-200 overflow-y-auto">
        <div class="px-3 py-2 border-b border-gray-100 sticky top-0 bg-white">
          <h2 class="text-sm font-bold text-gray-700">신고 접수 ({{ requests.length }})</h2>
        </div>
        <div v-if="requests.length===0" class="p-6 text-center text-sm text-gray-400">
          아직 접수된 신고가 없습니다.
        </div>
        <div v-for="req in requests" :key="req.request_id" class="border-b border-gray-50">
          <div class="w-full flex items-center justify-between px-3 py-2 hover:bg-gray-50">
            <button @click="toggleRequest(req.request_id)" class="flex items-center gap-2 min-w-0 text-left flex-1">
              <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: priorityColor(req.priority) }"></span>
              <span class="text-sm font-medium">#{{ req.request_id }}</span>
              <span class="text-xs text-gray-500">{{ typeLabel(req.type) }}</span>
              <!-- 배정 상태 뱃지 -->
              <span v-if="requestStatus(req.request_id)"
                    class="text-[10px] px-1.5 py-0.5 rounded-full font-medium" :class="dispatchBadge(requestStatus(req.request_id))">
                {{ dispatchLabel(requestStatus(req.request_id)) }}
              </span>
              <!-- 보조 인원(ADR-0007 D4). 주담당 «상태» 옆에 머릿수로 붙인다. -->
              <span v-if="supportCount(req.request_id)"
                    class="text-[10px] px-1.5 py-0.5 rounded-full font-medium bg-gray-100 text-gray-600">
                +보조 {{ supportCount(req.request_id) }}
              </span>
              <span v-if="requestStatus(req.request_id)==='rejected'" class="text-[10px] text-rose-600 font-semibold">재지령 필요</span>
            </button>
            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="text-xs text-gray-400">{{ fmtTime(req.created_at) }}</span>
              <!-- 활성 주담당 없으면 [배정], 거절/회수 상태면 [재배정] -->
              <button v-if="needsAssign(req.request_id)"
                      @click="openAssign(req)"
                      class="text-xs px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700 font-medium">
                {{ assignLabel(req.request_id) }}
              </button>
              <!-- 주담당이 붙어 있는 동안만 [보조]. 주담당을 «교체»하는 버튼이 아니다. -->
              <button v-else-if="canAddSupport(req.request_id)"
                      @click="openAssign(req, 'support')"
                      class="text-xs px-2 py-1 rounded border border-blue-300 text-blue-700 hover:bg-blue-50 font-medium">
                보조
              </button>
            </div>
          </div>
          <!-- 펼침: §7 연락처 노출 허용 -->
          <div v-if="expandedRequestId===req.request_id" class="px-3 pb-3 text-sm bg-gray-50">
            <div class="text-gray-700">{{ req.requester ? req.requester.name : '' }}</div>
            <a v-if="req.requester && req.requester.phone" :href="'tel:'+req.requester.phone"
               class="text-blue-600 font-semibold">{{ req.requester.phone }} · 전화</a>
            <div v-if="req.address" class="text-gray-500 mt-1">{{ req.address }}</div>
          </div>
        </div>
      </div>
      <!-- 출동 현황 보드 (FE-3.3) -->
      <div class="overflow-y-auto">
        <div class="px-3 py-2 border-b border-gray-100 sticky top-0 bg-white">
          <h2 class="text-sm font-bold text-gray-700">출동 현황</h2>
        </div>
        <!-- 카운트 칩(배정/출동/도착/완료) -->
        <div class="grid grid-cols-4 gap-1.5 px-3 py-2 border-b border-gray-50">
          <div class="text-center rounded-md py-1.5 bg-amber-50">
            <div class="text-base font-bold text-amber-700">{{ boardCount('assigned') }}</div>
            <div class="text-[10px] text-amber-600">배정</div>
          </div>
          <div class="text-center rounded-md py-1.5 bg-blue-50">
            <div class="text-base font-bold text-blue-700">{{ boardCount('en_route') }}</div>
            <div class="text-[10px] text-blue-600">출동</div>
          </div>
          <div class="text-center rounded-md py-1.5 bg-indigo-50">
            <div class="text-base font-bold text-indigo-700">{{ boardCount('arrived') }}</div>
            <div class="text-[10px] text-indigo-600">도착</div>
          </div>
          <div class="text-center rounded-md py-1.5 bg-emerald-50">
            <div class="text-base font-bold text-emerald-700">{{ boardCount('completed') }}</div>
            <div class="text-[10px] text-emerald-600">완료</div>
          </div>
        </div>
        <!-- 활성 지령 타임라인 -->
        <div v-if="board.active.length === 0 && board.history.length === 0" class="p-6 text-center text-xs text-gray-300">
          진행 중인 지령이 없습니다.
        </div>
        <div v-for="d in board.active" :key="'a'+d.dispatch_id" class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-50">
          <span class="flex items-center gap-2 min-w-0">
            <span class="text-sm font-medium">#{{ d.request_id }}</span>
            <span class="text-xs text-gray-500">{{ d.request ? typeLabel(d.request.type) : '' }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium" :class="kindBadge(d)">{{ kindLabel(d) }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium" :class="dispatchBadge(d.status)">{{ dispatchLabel(d.status) }}</span>
          </span>
          <span class="flex items-center gap-2 flex-shrink-0">
            <span class="text-xs text-gray-500 truncate max-w-[80px]">{{ d.paramedic_name }}</span>
            <!-- 회수(ADR-0007). arrived 면 노출하지 않는다 — 서버 전이표가 막는다. -->
            <button v-if="canRecall(d)" @click="requestRecallConfirm(d)"
                    class="text-xs px-2 py-1 rounded border border-rose-200 text-rose-600 hover:bg-rose-50 font-medium">
              회수
            </button>
          </span>
        </div>
        <!-- 완료 이력 -->
        <div v-if="board.history.length" class="px-3 py-1.5 text-[10px] text-gray-400 bg-gray-50">완료/종료 이력</div>
        <div v-for="d in board.history" :key="'h'+d.dispatch_id" class="flex items-center justify-between px-3 py-1.5 border-b border-gray-50 opacity-70">
          <span class="flex items-center gap-2 min-w-0">
            <span class="text-sm">#{{ d.request_id }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium" :class="kindBadge(d)">{{ kindLabel(d) }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium" :class="dispatchBadge(d.status)">{{ dispatchLabel(d.status) }}</span>
          </span>
          <span class="text-xs text-gray-400">{{ d.paramedic_name }}</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════ 모바일 바텀시트 ══════════
       peek(조망) → half(인지) → full(상세) 3단. 지도 위에 «겹치므로» 지도 컨테이너
       크기가 변하지 않는다 → relayout 불필요. peek 상태가 미배정 건수를 항상 이고
       있어서 "새 신고 있나?"에 답하려고 시트를 끌어올릴 필요가 없다. -->
  <div v-if="isMobile && hasProject"
       class="fixed inset-x-0 bottom-0 z-30 flex flex-col rounded-t-2xl border-t border-gray-200 bg-white shadow-[0_-8px_24px_-8px_rgba(0,0,0,0.18)] transition-[height] duration-200 ease-out"
       :class="sheetHeightClass"
       style="padding-bottom: env(safe-area-inset-bottom)">

    <!-- 핸들 + 요약 바 (peek 에서 보이는 전부) -->
    <button type="button" @click="cycleSheet"
            class="flex-none w-full px-4 pt-2 pb-2 text-left" aria-label="패널 펼치기/접기">
      <span class="mx-auto mb-2 block h-1.5 w-12 rounded-full bg-gray-300"></span>
      <span class="flex items-center gap-2">
        <span v-if="unassignedRequests.length"
              class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700">
          <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
          미배정 {{ unassignedRequests.length }}건
        </span>
        <span v-else class="text-xs font-medium text-gray-400">미배정 없음</span>
        <span class="ml-auto text-xs text-gray-400">신고 {{ requestCount }} · 온라인 {{ onlineCount }}</span>
      </span>
    </button>

    <!-- half/full 에서만: 세그먼트 + 내용 -->
    <div v-show="sheetSnap !== 'peek'" class="flex min-h-0 flex-1 flex-col">
      <div class="flex-none grid grid-cols-3 gap-1 px-3 pb-2">
        <button @click="setSheetTab('requests')" class="h-9 rounded-lg text-xs font-semibold"
                :class="sheetTab==='requests' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'">신고</button>
        <button @click="setSheetTab('board')" class="h-9 rounded-lg text-xs font-semibold"
                :class="sheetTab==='board' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'">출동</button>
        <button @click="setSheetTab('roster')" class="h-9 rounded-lg text-xs font-semibold"
                :class="sheetTab==='roster' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'">인력</button>
      </div>

      <!-- 신고 -->
      <div v-show="sheetTab==='requests'" class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
        <div v-if="requests.length===0" class="p-8 text-center text-sm text-gray-400">아직 접수된 신고가 없습니다.</div>
        <div v-for="req in sortedRequests" :key="'m'+req.request_id"
             class="border-b border-gray-100 px-4 py-3">
          <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 flex-none rounded-full" :style="{ backgroundColor: priorityColor(req.priority) }"></span>
            <span class="text-sm font-bold">#{{ req.request_id }}</span>
            <span class="text-xs text-gray-500">{{ typeLabel(req.type) }}</span>
            <span v-if="requestStatus(req.request_id)"
                  class="rounded-full px-1.5 py-0.5 text-[10px] font-medium" :class="dispatchBadge(requestStatus(req.request_id))">
              {{ dispatchLabel(requestStatus(req.request_id)) }}
            </span>
            <span v-if="supportCount(req.request_id)"
                  class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">
              +보조 {{ supportCount(req.request_id) }}
            </span>
            <span class="ml-auto text-xs text-gray-400">{{ fmtTime(req.created_at) }}</span>
          </div>
          <div v-if="req.address" class="mt-1 text-xs text-gray-600 break-keep">{{ req.address }}</div>
          <div v-if="req.requester" class="mt-0.5 text-xs text-gray-500">{{ req.requester.name }}</div>

          <div class="mt-2.5 flex gap-2">
            <!-- 폰이 데스크톱보다 «우월한» 유일한 지점 — 원탭 통화 -->
            <a v-if="req.requester && req.requester.phone" :href="'tel:'+req.requester.phone"
               class="flex h-11 flex-1 items-center justify-center rounded-xl bg-gray-100 text-sm font-semibold text-gray-700 active:bg-gray-200">
              전화
            </a>
            <button @click="focusRequestOnMap(req)"
                    class="flex h-11 flex-1 items-center justify-center rounded-xl bg-gray-100 text-sm font-semibold text-gray-700 active:bg-gray-200">
              지도에서
            </button>
            <button v-if="needsAssign(req.request_id)"
                    @click="openAssign(req)"
                    class="flex h-11 flex-1 items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-700">
              {{ assignLabel(req.request_id) }}
            </button>
            <button v-else-if="canAddSupport(req.request_id)"
                    @click="openAssign(req, 'support')"
                    class="flex h-11 flex-1 items-center justify-center rounded-xl border border-blue-300 text-sm font-bold text-blue-700 active:bg-blue-50">
              보조 추가
            </button>
          </div>
        </div>
      </div>

      <!-- 출동 -->
      <div v-show="sheetTab==='board'" class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
        <div class="grid grid-cols-4 gap-1.5 px-4 py-3">
          <div class="rounded-lg bg-amber-50 py-2 text-center">
            <div class="text-lg font-bold text-amber-700">{{ boardCount('assigned') }}</div>
            <div class="text-[10px] text-amber-600">배정</div>
          </div>
          <div class="rounded-lg bg-blue-50 py-2 text-center">
            <div class="text-lg font-bold text-blue-700">{{ boardCount('en_route') }}</div>
            <div class="text-[10px] text-blue-600">출동</div>
          </div>
          <div class="rounded-lg bg-indigo-50 py-2 text-center">
            <div class="text-lg font-bold text-indigo-700">{{ boardCount('arrived') }}</div>
            <div class="text-[10px] text-indigo-600">도착</div>
          </div>
          <div class="rounded-lg bg-emerald-50 py-2 text-center">
            <div class="text-lg font-bold text-emerald-700">{{ boardCount('completed') }}</div>
            <div class="text-[10px] text-emerald-600">완료</div>
          </div>
        </div>
        <div v-if="board.active.length===0 && board.history.length===0" class="p-8 text-center text-sm text-gray-400">
          진행 중인 지령이 없습니다.
        </div>
        <div v-for="d in board.active" :key="'mb'+d.dispatch_id" class="flex items-center gap-2 border-b border-gray-100 px-4 py-3">
          <span class="text-sm font-bold">#{{ d.request_id }}</span>
          <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium" :class="kindBadge(d)">{{ kindLabel(d) }}</span>
          <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium" :class="dispatchBadge(d.status)">{{ dispatchLabel(d.status) }}</span>
          <span class="ml-auto truncate text-xs text-gray-500">{{ d.paramedic_name }}</span>
          <!-- 회수(ADR-0007) — 폰에서도 오탭 방지 2단 확인을 거친다 -->
          <button v-if="canRecall(d)" @click="requestRecallConfirm(d)"
                  class="flex h-11 flex-none items-center rounded-xl border border-rose-200 px-3 text-xs font-bold text-rose-600 active:bg-rose-50">
            회수
          </button>
        </div>
        <div v-if="board.history.length" class="bg-gray-50 px-4 py-1.5 text-[10px] text-gray-400">완료/종료 이력</div>
        <div v-for="d in board.history" :key="'mh'+d.dispatch_id" class="flex items-center gap-2 border-b border-gray-100 px-4 py-2 opacity-70">
          <span class="text-sm">#{{ d.request_id }}</span>
          <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium" :class="kindBadge(d)">{{ kindLabel(d) }}</span>
          <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium" :class="dispatchBadge(d.status)">{{ dispatchLabel(d.status) }}</span>
          <span class="ml-auto truncate text-xs text-gray-400">{{ d.paramedic_name }}</span>
        </div>
      </div>

      <!-- 인력 (조회 전용 — 역할 «배정»은 모바일에서 제공하지 않는다) -->
      <div v-show="sheetTab==='roster'" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 pb-4">
        <div v-for="role in roleOrder" :key="'mr'+role" class="flex items-center justify-between border-b border-gray-100 py-2.5">
          <span class="flex items-center gap-2 text-sm text-gray-700">
            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: roleColor(role) }"></span>{{ roleLabel(role) }}
          </span>
          <span class="text-sm text-gray-500"><b class="text-green-600">{{ roleOnline(role) }}</b> / {{ roleTotal(role) }}</span>
        </div>
        <!-- 🔴 모바일은 개인별 명단이 없다(역할 집계만). 그래도 «이 경보»는 보여야 한다 —
             위치가 안 오는 사람이 있다는 사실은 집계 뒤에 숨으면 안 된다. -->
        <div v-if="trackingAlertCount" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm font-bold text-red-600">
          위치 권한이 없는 참가자 {{ trackingAlertCount }}명 — 위치가 전혀 오지 않습니다
        </div>
        <p class="pt-3 text-xs text-gray-400">역할 배정은 관리자 &gt; 행사 &gt; 참가자 관리에서 합니다.</p>
      </div>
    </div>
  </div>

  <!-- ══════════ 지령 배정 (FE-3.3) ══════════
       루트로 끌어올린다(예전에는 하단 2분할 그리드의 자식이었다).
       모바일은 풀스크린, 데스크톱은 기존 우측 384px 드로어 그대로. -->
  <div v-if="assign.open" class="fixed inset-0 z-[200]" @click.self="closeAssign">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="absolute top-0 right-0 flex h-[100dvh] w-full flex-col bg-white shadow-2xl sm:w-96">
      <!-- 🔴 top-0 + h-[100dvh] 라 앱/홈화면 PWA 에서는 이 헤더가 «화면 최상단»에 붙는다.
           상단 safe-area 를 더하지 않으면 제목이 다이나믹 아일랜드에 가린다(아이폰 실측
           2026-08-09). 하단은 이미 처리돼 있었는데(아래 pb-[calc(...)]) 상단만 빠져 있었다.
           딥링크로 들어오면 이 패널이 «자동으로» 열리므로 앱에서 가장 먼저 보이는 화면이다. -->
      <div class="flex flex-none items-center justify-between border-b border-gray-200 px-4 pb-3 pt-[calc(0.75rem+env(safe-area-inset-top))]">
        <h3 class="text-base font-bold text-gray-900">
          {{ assign.mode === 'support' ? '보조 인원 추가' : '지령 배정' }} — 신고 #{{ assign.request && assign.request.request_id }}
        </h3>
        <button @click="closeAssign" class="flex h-11 w-11 items-center justify-center text-gray-400 hover:text-gray-600" aria-label="닫기">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <!-- 신고 요약 + 연락처(관제 노출 허용) -->
      <div class="flex-none border-b border-gray-100 px-4 py-3 text-sm">
        <div class="mb-1 flex items-center gap-2">
          <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: priorityColor(assign.request && assign.request.priority) }"></span>
          <span class="font-medium">{{ assign.request ? typeLabel(assign.request.type) : '' }}</span>
        </div>
        <div v-if="assign.request && assign.request.address" class="text-gray-500">📍 {{ assign.request.address }}</div>
        <div v-if="assign.request && assign.request.requester" class="mt-1 text-gray-600">
          {{ assign.request.requester.name }}
          <a v-if="assign.request.requester.phone" :href="'tel:'+assign.request.requester.phone" class="ml-1 font-semibold text-blue-600">{{ assign.request.requester.phone }}</a>
        </div>
      </div>
      <!-- 가용 대원 리스트 -->
      <div class="min-h-0 flex-1 overflow-y-auto p-2">
        <div class="px-2 py-1 text-xs font-semibold text-gray-500">
          가용 구급대원 (online · 거리순)
          <span v-if="assign.mode === 'support'" class="ml-1 font-normal text-gray-400">— 주담당은 그대로 둡니다</span>
        </div>
        <div v-if="assign.loading" class="p-4 text-center text-sm text-gray-400">불러오는 중…</div>
        <div v-else-if="assign.paramedics.length === 0" class="p-4 text-center text-sm text-gray-400">
          배정 가능한 구급대원이 없습니다.
        </div>
        <!-- 이미 이 신고에 붙어 있는 대원은 «고를 수 없다». 서버가 422 로 막으므로
             누를 수 있게 두면 상황실은 눌러 보고서야 안다(ADR-0007 D4). -->
        <label v-for="m in assign.paramedics" :key="m.user_id"
               class="flex items-center gap-2 rounded-lg px-2 py-3 sm:py-2"
               :class="m.on_this_request
                         ? 'cursor-not-allowed opacity-50'
                         : ['cursor-pointer hover:bg-gray-50', assign.selectedId === m.user_id ? 'bg-blue-50 ring-1 ring-blue-300' : '']">
          <input type="radio" name="medic" :value="m.user_id" v-model="assign.selectedId"
                 :disabled="m.on_this_request" class="text-blue-600">
          <span class="h-2 w-2 flex-shrink-0 rounded-full" :class="m.online ? 'bg-green-500' : 'bg-gray-300'"></span>
          <span class="min-w-0 flex-1">
            <span class="text-sm font-medium">{{ m.name }}</span>
            <span class="ml-1 text-xs text-gray-400">{{ roleLabel(m.role) }}</span>
            <span v-if="m.on_this_request" class="ml-1 text-[10px] font-semibold text-gray-500">이 신고 배정중</span>
          </span>
          <span class="text-xs text-gray-500">{{ fmtDistance(m.distance_m) }}</span>
          <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                :class="m.active_dispatch_count > 1 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'">
            지령 {{ m.active_dispatch_count }}
          </span>
        </label>
      </div>
      <!-- 메모 + 발령 -->
      <div class="flex-none border-t border-gray-200 px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
        <template v-if="!assign.confirming">
          <input v-model="assign.note" type="text" placeholder="메모(선택)"
                 class="mb-2 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <p v-if="assign.error" class="mb-2 text-xs text-rose-600">{{ assign.error }}</p>
          <div class="flex gap-2">
            <button @click="closeAssign" class="h-12 flex-1 rounded-md border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">취소</button>
            <button @click="requestAssignConfirm" :disabled="!assign.selectedId"
                    class="h-12 flex-1 rounded-md bg-blue-600 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">
              {{ assign.mode === 'support' ? '보조 발령' : '지령 발령' }}
            </button>
          </div>
        </template>
        <!-- 확인 단계: 발령은 되돌릴 수 없다(DispatchStatus 에 역행 전이 없음) -->
        <template v-else>
          <p class="mb-2.5 text-sm text-gray-700">
            <b>{{ selectedParamedic() ? selectedParamedic().name : '' }}</b> 님에게
            신고 <b>#{{ assign.request && assign.request.request_id }}</b>
            {{ assign.mode === 'support' ? '보조 지령을 발령합니다.' : '지령을 발령합니다.' }}
            <span v-if="assign.mode === 'support'" class="mt-1 block text-xs text-gray-500">
              주담당은 그대로 유지됩니다. 신고 종결은 주담당이 완료할 때 이뤄집니다.
            </span>
            <span class="mt-1 block text-xs text-gray-400">발령 후에는 회수만 가능합니다.</span>
          </p>
          <div class="flex gap-2">
            <button @click="cancelAssignConfirm" :disabled="assign.submitting"
                    class="h-12 flex-1 rounded-md border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">뒤로</button>
            <button @click="submitAssign" :disabled="assign.submitting"
                    class="h-12 flex-1 rounded-md bg-rose-600 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50">
              {{ assign.submitting ? '발령 중…' : '발령 확정' }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>

  <!-- ══════════ 지령 회수 확인 (ADR-0007) ══════════
       배정(requestAssignConfirm)과 같은 2단 확인. 회수는 되돌릴 수 없고(cancelled 는
       terminal), 상황실 화면은 하루 종일 열려 있어 스쳐 눌릴 위험이 배정과 같다.
       배정 드로어(z-200)보다 위에 둔다 — 두 창이 겹칠 수 있는 동선은 없지만,
       겹쳤을 때 «되돌릴 수 없는 쪽»이 뒤에 깔리면 안 된다. -->
  <div v-if="recall.dispatch" class="fixed inset-0 z-[210] flex items-center justify-center px-5"
       @click.self="cancelRecallConfirm">
    <div class="absolute inset-0 bg-black/40"></div>
    <div class="relative w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
      <h3 class="text-base font-bold text-gray-900">지령 회수</h3>
      <p class="mt-2 text-sm text-gray-700">
        <b>{{ recall.dispatch.paramedic_name }}</b> 님의
        신고 <b>#{{ recall.dispatch.request_id }}</b> {{ kindLabel(recall.dispatch) }} 지령을 회수합니다.
        <!-- 보조를 뺀 것과 주담당을 뺀 것은 신고에 미치는 영향이 다르다.
             한 문장으로 뭉뚱그리면 상황실이 「미배정으로 돌아갔다」고 잘못 읽는다. -->
        <span v-if="recall.dispatch.is_primary === false" class="mt-1 block text-xs text-gray-400">
          해당 대원 화면에서 지령이 즉시 사라집니다. 주담당은 그대로 출동을 이어갑니다. 되돌릴 수 없습니다.
        </span>
        <span v-else class="mt-1 block text-xs text-gray-400">
          대원 화면에서 지령이 즉시 사라지고, 신고는 다시 미배정으로 돌아갑니다. 되돌릴 수 없습니다.
        </span>
      </p>
      <!-- 사유는 대원 화면에 그대로 표시된다(«왜 멈추라는지»가 없으면 대원은 계속 간다) -->
      <input v-model="recall.reason" type="text" placeholder="회수 사유(선택) — 대원에게 표시됩니다"
             class="mt-3 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <p v-if="recall.error" class="mt-2 text-xs text-rose-600">{{ recall.error }}</p>
      <div class="mt-4 flex gap-2">
        <button @click="cancelRecallConfirm" :disabled="recall.submitting"
                class="h-12 flex-1 rounded-md border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">뒤로</button>
        <button @click="submitRecall" :disabled="recall.submitting"
                class="h-12 flex-1 rounded-md bg-rose-600 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50">
          {{ recall.submitting ? '회수 중…' : '회수 확정' }}
        </button>
      </div>
    </div>
  </div>
</div>
`,
};
