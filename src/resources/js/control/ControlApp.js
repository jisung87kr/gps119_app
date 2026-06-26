// 웹 관제 SPA — Vue 옵션 객체 (FE-2.1 + FE-3.3).
// 인원 마커 풀 + 신고 고정핀 + 역할필터 + 실시간(presence/control) + 폴링 폴백
// + 지령 배정 패널 + 출동현황 보드(FE-3.3).

import { PersonMarkerPool, RequestPinLayer } from './markerPool';
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

            mapReady: false,
            mapError: false,
            loadingRoster: false,
            railCollapsed: false,

            roleOrder: ROLE_ORDER,
            roleMetaMap: ROLE_META,
            roleFilter: Object.fromEntries(ROLE_ORDER.map((r) => [r, true])),
            roleCounts: {},        // role -> {online,total}
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
    },

    mounted() {
        // Blade 가 data-projects 로 주입한 활성 행사 목록.
        // 템플릿이 다중 루트라 this.$el은 fragment placeholder(텍스트 노드) → 마운트 컨테이너에서 직접 읽는다.
        try {
            const root = document.getElementById('control-app');
            this.projects = JSON.parse(root?.dataset.projects || '[]');
        } catch (e) {
            this.projects = [];
        }
        // 1개면 자동 선택
        if (this.projects.length === 1) {
            this.selectProject(this.projects[0].id);
        }
        // 브라우저 QA용 전역 노출
        window.__control = this;
    },

    beforeUnmount() {
        this._teardownRealtime();
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

            // 새 풀
            this.pool = new PersonMarkerPool(this.map);
            this.requestPins = new RequestPinLayer(this.map);
            this._applyFilterToPool();

            await this.fetchRoster(true);
            await this.loadBoard();
            this._subscribeRealtime();
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
                rows.forEach((row) => this.pool.upsert(row));
                if (fit) this.pool.fitBounds();
                this._refreshCounts();
            } catch (e) {
                console.error('[control] roster 조회 실패', e);
            } finally {
                this.loadingRoster = false;
            }
        },

        // ── 실시간 ──────────────────────────────────────────────
        async _subscribeRealtime() {
            const echo = await this._waitForEcho();
            if (!echo) { this._startPolling(); return; }

            const pid = this.selectedProjectId;
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
            const moved = this.pool.move(payload.user_id, payload.latitude, payload.longitude);
            if (!moved) {
                this.pool.upsert({
                    user_id: payload.user_id,
                    name: null,
                    role: payload.role,
                    status: 'active',
                    last_lat: payload.latitude,
                    last_lng: payload.longitude,
                    last_seen_at: payload.recorded_at,
                });
            } else {
                // last_seen 갱신 위해 row 동기화
                const entry = this.pool.markers.get(payload.user_id);
                if (entry) {
                    entry.row.last_seen_at = payload.recorded_at;
                    entry.row.last_lat = payload.latitude;
                    entry.row.last_lng = payload.longitude;
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
        },

        fmtDistance(m) {
            if (m == null) return '거리 미상';
            return m >= 1000 ? `${(m / 1000).toFixed(1)}km` : `${m}m`;
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

        _startPolling() {
            if (this._pollTimer) return;
            this.wsState = 'polling';
            this._pollTimer = setInterval(() => this.fetchRoster(false), POLL_INTERVAL_MS);
        },
        _stopPolling() {
            if (this._pollTimer) { clearInterval(this._pollTimer); this._pollTimer = null; }
        },

        _teardownRealtime() {
            this._stopPolling();
            if (window.Echo && this.selectedProjectId != null) {
                try { window.Echo.leave(`event.${this.selectedProjectId}.locations`); } catch (e) { /* noop */ }
                try { window.Echo.leave(`event.${this.selectedProjectId}.control`); } catch (e) { /* noop */ }
            }
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
        recenter() { if (this.pool) this.pool.fitBounds(); },
        toggleRequest(id) { this.expandedRequestId = this.expandedRequestId === id ? null : id; },
        fmtTime(iso) {
            if (!iso) return '';
            try { return new Date(iso).toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' }); }
            catch (e) { return ''; }
        },
    },

    template: `
<div class="h-[calc(100vh-0px)] w-full grid grid-cols-[280px_1fr] grid-rows-[56px_1fr] bg-gray-100 text-gray-900"
     :class="{ 'grid-cols-[48px_1fr]': railCollapsed }">

  <!-- HEADER -->
  <header class="col-span-2 row-start-1 h-14 bg-white border-b border-gray-200 flex items-center justify-between px-4">
    <div class="flex items-center gap-3 min-w-0">
      <button @click="toggleRail" class="p-1.5 rounded hover:bg-gray-100 text-gray-500" title="사이드바">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <select v-if="projects.length > 1" :value="selectedProjectId" @change="selectProject($event.target.value)"
              class="text-sm border border-gray-300 rounded-md px-2 py-1.5 focus:ring-2 focus:ring-blue-500">
        <option :value="null" disabled>행사 선택</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <h1 class="text-base font-bold truncate">
        실시간 관제<span v-if="projectName"> — {{ projectName }}</span>
      </h1>
    </div>
    <div class="flex items-center gap-4 text-sm">
      <!-- 기록 다운로드(BE-4.1) — controller/admin, 세션 쿠키로 GET -->
      <div v-if="hasProject" class="relative" @mouseleave="reportMenu=false">
        <button @click="reportMenu=!reportMenu" class="px-2.5 py-1 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 text-xs font-medium">
          기록 ▾
        </button>
        <div v-if="reportMenu" class="absolute right-0 top-full mt-1 w-40 bg-white border border-gray-200 rounded-md shadow-lg py-1 z-20">
          <a :href="reportUrl('requests')" class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">신고 기록 CSV</a>
          <a :href="reportUrl('dispatches')" class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">지령 기록 CSV</a>
          <a :href="reportUrl('tracks')" class="block px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">동선 기록 CSV</a>
        </div>
      </div>
      <span class="text-gray-600">온라인 <b class="text-gray-900">{{ onlineCount }}</b></span>
      <span class="text-gray-600">신고 <b class="text-gray-900">{{ requestCount }}</b></span>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
            :class="wsState==='ws' ? 'bg-green-100 text-green-700' : (wsState==='polling' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500')">
        <span class="w-2 h-2 rounded-full" :class="wsState==='ws' ? 'bg-green-500' : (wsState==='polling' ? 'bg-amber-500' : 'bg-gray-400')"></span>
        {{ wsState==='ws' ? '실시간' : (wsState==='polling' ? '폴링' : '연결중') }}
      </span>
    </div>
  </header>

  <!-- L-RAIL -->
  <aside class="row-start-2 col-start-1 bg-white border-r border-gray-200 overflow-y-auto" v-show="!railCollapsed">
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
  </aside>

  <!-- 우측: MAP + BOTTOM -->
  <div class="row-start-2 col-start-2 grid grid-rows-[1fr_240px] overflow-hidden">
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
    </div>

    <!-- BOTTOM -->
    <div class="grid grid-cols-[3fr_2fr] border-t border-gray-200 bg-white overflow-hidden">
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
      <!-- 지령 배정 패널 (우측 슬라이드, FE-3.3) -->
      <div v-if="assign.open" class="fixed inset-0 z-[200]" @click.self="closeAssign">
        <div class="absolute inset-0 bg-black/30"></div>
        <div class="absolute top-0 right-0 h-full w-96 bg-white shadow-2xl flex flex-col">
          <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base font-bold text-gray-900">지령 배정 — 신고 #{{ assign.request && assign.request.request_id }}</h3>
            <button @click="closeAssign" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <!-- 신고 요약 + 연락처(관제 노출 허용) -->
          <div class="px-4 py-3 border-b border-gray-100 text-sm">
            <div class="flex items-center gap-2 mb-1">
              <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: priorityColor(assign.request && assign.request.priority) }"></span>
              <span class="font-medium">{{ assign.request ? typeLabel(assign.request.type) : '' }}</span>
            </div>
            <div v-if="assign.request && assign.request.address" class="text-gray-500">📍 {{ assign.request.address }}</div>
            <div v-if="assign.request && assign.request.requester" class="text-gray-600 mt-1">
              {{ assign.request.requester.name }}
              <a v-if="assign.request.requester.phone" :href="'tel:'+assign.request.requester.phone" class="text-blue-600 font-semibold ml-1">{{ assign.request.requester.phone }}</a>
            </div>
          </div>
          <!-- 가용 대원 리스트 -->
          <div class="flex-1 overflow-y-auto p-2">
            <div class="px-2 py-1 text-xs font-semibold text-gray-500">가용 구급대원 (online · 거리순)</div>
            <div v-if="assign.loading" class="p-4 text-center text-sm text-gray-400">불러오는 중…</div>
            <div v-else-if="assign.paramedics.length === 0" class="p-4 text-center text-sm text-gray-400">
              배정 가능한 구급대원이 없습니다.
            </div>
            <label v-for="m in assign.paramedics" :key="m.user_id"
                   class="flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-gray-50 cursor-pointer"
                   :class="{ 'bg-blue-50 ring-1 ring-blue-300': assign.selectedId === m.user_id }">
              <input type="radio" name="medic" :value="m.user_id" v-model="assign.selectedId" class="text-blue-600">
              <span class="w-2 h-2 rounded-full flex-shrink-0" :class="m.online ? 'bg-green-500' : 'bg-gray-300'"></span>
              <span class="flex-1 min-w-0">
                <span class="text-sm font-medium">{{ m.name }}</span>
                <span class="text-xs text-gray-400 ml-1">{{ roleLabel(m.role) }}</span>
              </span>
              <span class="text-xs text-gray-500">{{ fmtDistance(m.distance_m) }}</span>
              <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                    :class="m.active_dispatch_count > 1 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'">
                지령 {{ m.active_dispatch_count }}
              </span>
            </label>
          </div>
          <!-- 메모 + 발령 -->
          <div class="px-4 py-3 border-t border-gray-200">
            <input v-model="assign.note" type="text" placeholder="메모(선택)"
                   class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 mb-2 focus:ring-2 focus:ring-blue-500">
            <p v-if="assign.error" class="text-xs text-rose-600 mb-2">{{ assign.error }}</p>
            <div class="flex gap-2">
              <button @click="closeAssign" class="flex-1 py-2 text-sm font-medium rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">취소</button>
              <button @click="submitAssign" :disabled="!assign.selectedId || assign.submitting"
                      class="flex-1 py-2 text-sm font-bold rounded-md text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
                {{ assign.submitting ? '발령 중…' : '지령 발령' }}
              </button>
            </div>
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
</div>
`,
};
