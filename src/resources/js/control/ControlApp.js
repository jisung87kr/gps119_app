// 웹 관제 SPA — Vue 옵션 객체 (FE-2.1 + FE-3.3).
// 인원 마커 풀 + 신고 고정핀 + 역할필터 + 실시간(presence/control) + 폴링 폴백
// + 지령 배정 패널 + 출동현황 보드(FE-3.3).

import { PersonMarkerPool, RequestPinLayer, CLUSTER_PROFILE } from './markerPool';
import {
    ROLE_ORDER, ROLE_META, roleMeta, priorityMeta,
    dispatchStatusMeta, DISPATCH_STATUS_ORDER, requestTypeMeta,
} from './roleMeta';

const POLL_INTERVAL_MS = 12000;
const KAKAO_KEY = '509c2656c00fa9af4782197a888763f6';

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

            requests: [],          // 라이브 신고(최신 우선)
            expandedRequestId: null,
            requestStatusMap: {},  // request_id -> dispatch status(배정후 신고행 표시용)

            onlineCount: 0,
            requestCount: 0,
            wsState: 'connecting', // connecting | ws | polling
            reportMenu: false,     // 기록 다운로드 메뉴(BE-4.1)

            // FE-3.3 지령 배정 패널
            assign: {
                open: false,
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

            // FE-3.3 출동현황 보드
            dispatchStatusOrder: DISPATCH_STATUS_ORDER,
            board: { counts: {}, active: [], history: [], loading: false },
        };
    },

    computed: {
        hasProject() {
            return this.selectedProjectId != null;
        },

        // 모바일에서는 레일을 아예 쓰지 않으므로 항상 0px. 그리드 좌표를 바꾸지 않고
        // 폭만 접어서 데스크톱 마크업을 그대로 재사용한다.
        gridStyle() {
            const cols = (this.isMobile || this.railCollapsed) ? '0px 1fr' : '280px 1fr';
            return { gridTemplateColumns: cols };
        },

        // 아직 배정되지 않은 신고(거절 포함 — 재지령 필요). 시트 peek 상태의 핵심 지표.
        unassignedRequests() {
            return this.requests.filter((r) => {
                const s = this.requestStatus(r.request_id);
                return !s || s === 'rejected';
            });
        },

        // 미배정 우선, 그다음 최신순. 폰에서는 스크롤 없이 보이는 첫 화면이 전부다.
        sortedRequests() {
            const unassigned = [];
            const rest = [];
            this.requests.forEach((r) => {
                const s = this.requestStatus(r.request_id);
                (!s || s === 'rejected') ? unassigned.push(r) : rest.push(r);
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

            await this._ensureMap();
            if (!this.mapReady) return;

            // 새 풀 (클러스터 파라미터는 화면 폭에 따라 다름 — markerPool.CLUSTER_PROFILE 주석 참조)
            this.pool = new PersonMarkerPool(
                this.map,
                this.isMobile ? CLUSTER_PROFILE.mobile : CLUSTER_PROFILE.desktop
            );
            this.requestPins = new RequestPinLayer(this.map);
            this._applyFilterToPool();

            await this.fetchRoster(true);
            await this.fetchRequests();
            await this.loadBoard();
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
        async fetchRoster(fit = false) {
            if (!this.hasProject) return;
            this.loadingRoster = true;
            try {
                const res = await window.axios.get(
                    `/api/events/${this.selectedProjectId}/participants`,
                    { headers: { Accept: 'application/json' } }
                );
                const rows = res.data.data || [];
                this.roster = rows;
                rows.forEach((row) => this.pool.upsert(row));
                if (fit) this.pool.fitBounds();
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
                rows.forEach((r) => this.requestPins.upsert(r));
                this.requests = rows; // 이미 최신순(desc)
                this.requestCount = this.requestPins.count();
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
            this._locCh = echo.join(`event.${pid}.locations`)
                .listen('.participant.location', (e) => this._onLocation(e));
            // private control: 신규 신고 + 지령 상태 갱신
            this._ctrlCh = echo.private(`event.${pid}.control`)
                .listen('.request.created', (e) => this._onRequestCreated(e))
                .listen('.dispatch.updated', (e) => this._onDispatchUpdated(e));

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

        _onLocation(payload) {
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
                }
            }
            this._refreshCounts();
        },

        _onRequestCreated(payload) {
            // 신고 고정핀(클러스터 제외·z100) + 목록 prepend
            this.requestPins.upsert(payload);
            this.requests.unshift(payload);
            this.requestCount = this.requestPins.count();
        },

        // ── FE-3.3: 지령 배정 패널 ──────────────────────────────
        async openAssign(req) {
            this.assign.open = true;
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
            this.assign.submitting = true;
            this.assign.error = '';
            try {
                await window.axios.post(
                    `/api/requests/${this.assign.request.request_id}/dispatch`,
                    { paramedic_id: this.assign.selectedId, note: this.assign.note || null },
                    { headers: { Accept: 'application/json' } }
                );
                // 신고행 상태 = 배정
                this.requestStatusMap[this.assign.request.request_id] = 'assigned';
                this.closeAssign();
                await this.loadBoard();
            } catch (e) {
                const status = e.response?.status;
                // OI-2 동시 배정 경합 → 422
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
                // 신고행 상태 맵 갱신(활성 지령 기준)
                const map = {};
                (d.active || []).forEach((x) => { map[x.request_id] = x.status; });
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
                // rejected 면 재지령 필요 → 신고행 경고 표시
                this.requestStatusMap[payload.request_id] = payload.status;
            }
            this.loadBoard();
        },

        boardCount(s) { return this.board.counts?.[s] || 0; },
        requestStatus(id) { return this.requestStatusMap[id] || null; },

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
                await this.fetchRoster(false);
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
            if (!this.pool) return;
            this.roleCounts = this.pool.counts();
            this.onlineCount = this.pool.onlineTotal();
        },

        roleOnline(role) { return (this.roleCounts[role]?.online) || 0; },
        roleTotal(role) { return (this.roleCounts[role]?.total) || 0; },

        toggleRail() { this.railCollapsed = !this.railCollapsed; },

        // 전체보기. 모바일은 시트가 지도 하단을 가리므로 그만큼 아래 패딩을 준다
        // (안 그러면 마커가 시트 밑에 깔려 "전체 보기"가 전체를 안 보여준다).
        recenter() {
            if (!this.pool) return;
            if (!this.isMobile) { this.pool.fitBounds(); return; }
            const h = window.innerHeight || 0;
            const bottom = this.sheetSnap === 'full' ? Math.round(h * 0.9)
                : this.sheetSnap === 'half' ? Math.round(h * 0.45)
                    : 96;
            this.pool.fitBounds({ top: 24, right: 24, bottom: bottom + 24, left: 24 });
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
          <a :href="reportUrl('requests')" class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">신고 기록 CSV</a>
          <a :href="reportUrl('dispatches')" class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">지령 기록 CSV</a>
          <a :href="reportUrl('tracks')" class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">동선 기록 CSV</a>
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
      <h2 class="text-sm font-bold text-gray-700 mb-2">참가자 역할 배정</h2>
      <div class="space-y-1.5 max-h-56 overflow-y-auto">
        <div v-for="r in roster" :key="'a-'+r.user_id" class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: roleColor(r.role) }"></span>
          <span class="text-sm text-gray-700 flex-1 truncate" :title="r.name">{{ r.name }}</span>
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
        <div class="text-center">
          <p class="text-gray-600 mb-2">지도를 불러오지 못했습니다.</p>
          <button @click="recenter" class="px-3 py-1.5 bg-blue-600 text-white rounded text-sm">새로고침</button>
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
              <span v-if="requestStatus(req.request_id)==='rejected'" class="text-[10px] text-rose-600 font-semibold">재지령 필요</span>
            </button>
            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="text-xs text-gray-400">{{ fmtTime(req.created_at) }}</span>
              <!-- 활성 배정 없으면 [배정], 거절상태면 [재배정] -->
              <button v-if="!requestStatus(req.request_id) || requestStatus(req.request_id)==='rejected'"
                      @click="openAssign(req)"
                      class="text-xs px-2 py-1 rounded bg-blue-600 text-white hover:bg-blue-700 font-medium">
                {{ requestStatus(req.request_id)==='rejected' ? '재배정' : '배정' }}
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
        <div v-for="d in board.active" :key="'a'+d.dispatch_id" class="flex items-center justify-between px-3 py-2 border-b border-gray-50">
          <span class="flex items-center gap-2 min-w-0">
            <span class="text-sm font-medium">#{{ d.request_id }}</span>
            <span class="text-xs text-gray-500">{{ d.request ? typeLabel(d.request.type) : '' }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium" :class="dispatchBadge(d.status)">{{ dispatchLabel(d.status) }}</span>
          </span>
          <span class="text-xs text-gray-500 truncate">{{ d.paramedic_name }}</span>
        </div>
        <!-- 완료 이력 -->
        <div v-if="board.history.length" class="px-3 py-1.5 text-[10px] text-gray-400 bg-gray-50">완료/종료 이력</div>
        <div v-for="d in board.history" :key="'h'+d.dispatch_id" class="flex items-center justify-between px-3 py-1.5 border-b border-gray-50 opacity-70">
          <span class="flex items-center gap-2 min-w-0">
            <span class="text-sm">#{{ d.request_id }}</span>
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
            <button v-if="!requestStatus(req.request_id) || requestStatus(req.request_id)==='rejected'"
                    @click="openAssign(req)"
                    class="flex h-11 flex-1 items-center justify-center rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-700">
              {{ requestStatus(req.request_id)==='rejected' ? '재배정' : '배정' }}
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
          <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium" :class="dispatchBadge(d.status)">{{ dispatchLabel(d.status) }}</span>
          <span class="ml-auto truncate text-xs text-gray-500">{{ d.paramedic_name }}</span>
        </div>
        <div v-if="board.history.length" class="bg-gray-50 px-4 py-1.5 text-[10px] text-gray-400">완료/종료 이력</div>
        <div v-for="d in board.history" :key="'mh'+d.dispatch_id" class="flex items-center gap-2 border-b border-gray-100 px-4 py-2 opacity-70">
          <span class="text-sm">#{{ d.request_id }}</span>
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
      <div class="flex flex-none items-center justify-between border-b border-gray-200 px-4 py-3">
        <h3 class="text-base font-bold text-gray-900">지령 배정 — 신고 #{{ assign.request && assign.request.request_id }}</h3>
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
        <div class="px-2 py-1 text-xs font-semibold text-gray-500">가용 구급대원 (online · 거리순)</div>
        <div v-if="assign.loading" class="p-4 text-center text-sm text-gray-400">불러오는 중…</div>
        <div v-else-if="assign.paramedics.length === 0" class="p-4 text-center text-sm text-gray-400">
          배정 가능한 구급대원이 없습니다.
        </div>
        <label v-for="m in assign.paramedics" :key="m.user_id"
               class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-3 hover:bg-gray-50 sm:py-2"
               :class="{ 'bg-blue-50 ring-1 ring-blue-300': assign.selectedId === m.user_id }">
          <input type="radio" name="medic" :value="m.user_id" v-model="assign.selectedId" class="text-blue-600">
          <span class="h-2 w-2 flex-shrink-0 rounded-full" :class="m.online ? 'bg-green-500' : 'bg-gray-300'"></span>
          <span class="min-w-0 flex-1">
            <span class="text-sm font-medium">{{ m.name }}</span>
            <span class="ml-1 text-xs text-gray-400">{{ roleLabel(m.role) }}</span>
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
              지령 발령
            </button>
          </div>
        </template>
        <!-- 확인 단계: 발령은 되돌릴 수 없다(DispatchStatus 에 역행 전이 없음) -->
        <template v-else>
          <p class="mb-2.5 text-sm text-gray-700">
            <b>{{ selectedParamedic() ? selectedParamedic().name : '' }}</b> 님에게
            신고 <b>#{{ assign.request && assign.request.request_id }}</b> 지령을 발령합니다.
            <span class="mt-1 block text-xs text-gray-400">발령 후에는 취소할 수 없습니다.</span>
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
</div>
`,
};
