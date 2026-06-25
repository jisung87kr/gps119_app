// 웹 관제 SPA — Vue 옵션 객체 (FE-2.1 / control-map-spec).
// 인원 마커 풀 + 신고 고정핀 + 역할필터 + 실시간(presence/control) + 폴링 폴백.
// 출동현황 보드/지령 배정/폴리라인은 Phase 3 — 자리만 비워둠.

import { PersonMarkerPool, RequestPinLayer } from './markerPool';
import { ROLE_ORDER, ROLE_META, roleMeta, priorityMeta } from './roleMeta';

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

            onlineCount: 0,
            requestCount: 0,
            wsState: 'connecting', // connecting | ws | polling
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
    },

    beforeUnmount() {
        this._teardownRealtime();
    },

    methods: {
        roleLabel(role) { return roleMeta(role).label; },
        roleColor(role) { return roleMeta(role).color; },
        priorityLabel(p) { return priorityMeta(p).label; },
        priorityColor(p) { return priorityMeta(p).color; },

        async selectProject(id) {
            if (id == null) return;
            this.selectedProjectId = Number(id);
            const p = this.projects.find((x) => x.id === this.selectedProjectId);
            this.projectName = p ? p.name : '';
            this._teardownRealtime();
            this.requests = [];
            this.requestCount = 0;

            await this._ensureMap();
            if (!this.mapReady) return;

            // 새 풀
            this.pool = new PersonMarkerPool(this.map);
            this.requestPins = new RequestPinLayer(this.map);
            this._applyFilterToPool();

            await this.fetchRoster(true);
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
            // private control: 신규 신고
            this._ctrlCh = echo.private(`event.${pid}.control`)
                .listen('.request.created', (e) => this._onRequestCreated(e));

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
          <button @click="toggleRequest(req.request_id)"
                  class="w-full flex items-center justify-between px-3 py-2 hover:bg-gray-50 text-left">
            <span class="flex items-center gap-2 min-w-0">
              <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: priorityColor(req.priority) }"></span>
              <span class="text-sm font-medium">#{{ req.request_id }}</span>
              <span class="text-xs text-gray-500">{{ priorityLabel(req.priority) }}</span>
            </span>
            <span class="text-xs text-gray-400">{{ fmtTime(req.created_at) }}</span>
          </button>
          <!-- 펼침: §7 연락처 노출 허용 -->
          <div v-if="expandedRequestId===req.request_id" class="px-3 pb-3 text-sm bg-gray-50">
            <div class="text-gray-700">{{ req.requester ? req.requester.name : '' }}</div>
            <a v-if="req.requester && req.requester.phone" :href="'tel:'+req.requester.phone"
               class="text-blue-600 font-semibold">{{ req.requester.phone }} · 전화</a>
            <div v-if="req.address" class="text-gray-500 mt-1">{{ req.address }}</div>
          </div>
        </div>
      </div>
      <!-- 출동 현황 보드 (Phase 3 자리) -->
      <div class="overflow-y-auto">
        <div class="px-3 py-2 border-b border-gray-100 sticky top-0 bg-white">
          <h2 class="text-sm font-bold text-gray-700">출동 현황</h2>
        </div>
        <div class="p-6 text-center text-sm text-gray-300">
          출동 지령 배정/현황은 다음 단계에서 제공됩니다.
        </div>
      </div>
    </div>
  </div>
</div>
`,
};
