<x-layouts.app>
    <!-- Vue 3 (페이지별 마운트 — 기존 패턴 계승) -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- 개인 지령 채널(event.{id}.dispatch.{userId}) 구독용 본인 id -->
    <script>window.__authUserId = {{ auth()->id() }};</script>

    <div class="flex-1 bg-slate-50">
        <div id="dispatchApp"
             class="w-full max-w-md mx-auto px-4 py-6"
             data-project-id="{{ $project->id }}"
             data-role="{{ $role }}"
             data-role-label="{{ $roleLabel }}"
             data-project-name="{{ $project->name }}">

            <!-- 헤더 -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 min-w-0">
                    <a href="{{ route('events.active', $project->id) }}" class="flex-none p-1.5 -ml-1 rounded-lg text-slate-400 hover:bg-slate-100" title="활동 화면으로">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <div class="min-w-0">
                        <h1 class="text-lg font-black tracking-tight text-slate-900 truncate">@{{ projectName }}</h1>
                        <p class="text-xs text-slate-400">@{{ roleLabel }} · 지령 수신 대기</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
                      :class="wsState==='ws' ? 'bg-green-100 text-green-700' : (wsState==='polling' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500')">
                    <span class="w-2 h-2 rounded-full" :class="wsState==='ws' ? 'bg-green-500' : (wsState==='polling' ? 'bg-amber-500' : 'bg-gray-400')"></span>
                    @{{ wsState==='ws' ? '실시간' : (wsState==='polling' ? '폴링' : '연결중') }}
                </span>
            </div>

            <!-- 내 지령 목록 -->
            <div v-if="dispatches.length === 0" class="text-center py-16 text-sm text-slate-400">
                배정된 지령이 없습니다.
            </div>

            <div v-for="d in dispatches" :key="d.dispatch_id"
                 class="bg-white rounded-2xl shadow-sm border border-slate-200/60 p-4 mb-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="flex items-center gap-2">
                        <span class="text-sm font-bold">신고 #@{{ d.request.id }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded-full" :style="{ backgroundColor: typeColor(d.request.type)+'22', color: typeColor(d.request.type) }">@{{ typeLabel(d.request.type) }}</span>
                    </span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full font-medium" :class="statusBadge(d.status)">@{{ statusLabel(d.status) }}</span>
                </div>
                <div v-if="d.request.address" class="text-sm text-slate-600 mb-1">📍 @{{ d.request.address }}</div>
                <!-- 본인 배정건 신고자 연락처(ADR-0004) — 실시간 수신분만 -->
                <div v-if="d.requester" class="text-sm text-slate-700 mb-1">
                    @{{ d.requester.name }}
                    <a v-if="d.requester.phone" :href="'tel:'+d.requester.phone" class="text-blue-600 font-semibold ml-1">@{{ d.requester.phone }} · 전화</a>
                </div>
                <div v-if="d.note" class="text-xs text-slate-400 mb-2">메모: @{{ d.note }}</div>

                <!-- 전이 버튼: 현재 상태의 allowedTransitions 만 -->
                <div class="flex gap-2 mt-3">
                    <button v-if="primaryAction(d.status)" @click="transition(d, primaryAction(d.status))"
                            :disabled="busy[d.dispatch_id]"
                            class="flex-1 py-3 text-base font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
                            style="min-height:56px">
                        @{{ actionLabel(primaryAction(d.status)) }}
                    </button>
                    <button v-if="canReject(d.status)" @click="openReject(d)"
                            :disabled="busy[d.dispatch_id]"
                            class="px-4 py-3 text-sm font-medium rounded-xl border border-rose-300 text-rose-600 hover:bg-rose-50 disabled:opacity-50"
                            style="min-height:56px">
                        거절
                    </button>
                    <button v-if="d.status !== 'rejected'" @click="navi(d)"
                            class="px-4 py-3 text-sm font-medium rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50"
                            style="min-height:56px">
                        길안내
                    </button>
                </div>
                <p v-if="errors[d.dispatch_id]" class="text-xs text-rose-600 mt-2">@{{ errors[d.dispatch_id] }}</p>
            </div>

    <!-- 풀스크린 신규 지령 알림 -->
    <div v-if="alert.open" id="dispatch-fullscreen-alert"
         class="fixed inset-0 z-[300] bg-blue-700 text-white flex flex-col items-center justify-center px-6">
        <div class="animate-pulse text-center">
            <div class="text-sm font-semibold opacity-80 mb-2">새 지령 배정</div>
            <div class="text-3xl font-black mb-4" v-if="alert.dispatch">신고 #@{{ alert.dispatch.request.id }}</div>
        </div>
        <div v-if="alert.dispatch" class="bg-white text-slate-900 rounded-2xl p-5 w-full max-w-sm shadow-2xl">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-sm font-bold" :style="{ color: typeColor(alert.dispatch.request.type) }">@{{ typeLabel(alert.dispatch.request.type) }}</span>
            </div>
            <div v-if="alert.dispatch.request.address" class="text-sm text-slate-600 mb-1">📍 @{{ alert.dispatch.request.address }}</div>
            <div v-if="alert.dispatch.requester" class="text-sm text-slate-700 mb-1">
                @{{ alert.dispatch.requester.name }}
                <a v-if="alert.dispatch.requester.phone" :href="'tel:'+alert.dispatch.requester.phone" class="text-blue-600 font-semibold ml-1">@{{ alert.dispatch.requester.phone }}</a>
            </div>
            <div v-if="alert.dispatch.note" class="text-xs text-slate-400 mb-3">메모: @{{ alert.dispatch.note }}</div>
            <button @click="dismissAlert" class="w-full py-3 mt-2 text-base font-bold rounded-xl bg-blue-600 text-white" style="min-height:56px">확인</button>
        </div>
    </div>

    <!-- 거절 사유 모달 -->
    <div v-if="reject.open" class="fixed inset-0 z-[310] flex items-center justify-center bg-black/40 px-4">
        <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
            <h3 class="text-base font-bold mb-3">지령 거절</h3>
            <label v-for="r in rejectPresets" :key="r" class="flex items-center gap-2 py-2 cursor-pointer">
                <input type="radio" name="reject" :value="r" v-model="reject.reason" class="text-rose-600">
                <span class="text-sm">@{{ r }}</span>
            </label>
            <label class="flex items-center gap-2 py-2 cursor-pointer">
                <input type="radio" name="reject" value="__custom" v-model="reject.reason" class="text-rose-600">
                <input v-model="reject.custom" @focus="reject.reason='__custom'" type="text" placeholder="기타 사유"
                       class="flex-1 text-sm border border-slate-300 rounded-md px-2 py-1.5">
            </label>
            <div class="flex gap-2 mt-4">
                <button @click="closeReject" class="flex-1 py-2.5 text-sm font-medium rounded-xl border border-slate-300 text-slate-700">취소</button>
                <button @click="submitReject" :disabled="!rejectReasonValue"
                        class="flex-1 py-2.5 text-sm font-bold rounded-xl text-white bg-rose-600 disabled:opacity-50">거절 제출</button>
            </div>
        </div>
    </div>
        </div>
    </div>

    <script type="module">
        import { statusMeta, nextStatuses, typeMeta, ACTION_LABEL } from '/js/components/dispatchMeta.js';
        import { openKakaoNavi } from '/js/components/kakaoNavi.js';
        import { createLocationSharer } from '/js/components/locationShare.js';

        const { createApp } = Vue;
        const root = document.getElementById('dispatchApp');

        // 알림음(asset 없이 WebAudio 비프) — best-effort
        let audioCtx = null;
        function beep() {
            try {
                audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                const o = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                o.connect(g); g.connect(audioCtx.destination);
                o.type = 'sine'; o.frequency.value = 880;
                g.gain.setValueAtTime(0.001, audioCtx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.3, audioCtx.currentTime + 0.05);
                g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.6);
                o.start(); o.stop(audioCtx.currentTime + 0.6);
            } catch (e) { /* best-effort */ }
        }

        createApp({
            data() {
                return {
                    projectId: Number(root.dataset.projectId),
                    role: root.dataset.role,
                    roleLabel: root.dataset.roleLabel,
                    projectName: root.dataset.projectName,
                    dispatches: [],
                    busy: {},
                    errors: {},
                    wsState: 'connecting',
                    alert: { open: false, dispatch: null },
                    reject: { open: false, dispatch: null, reason: '', custom: '' },
                    rejectPresets: ['이미 다른 출동중', '거리가 너무 멂', '장비/인력 부족'],
                };
            },
            computed: {
                rejectReasonValue() {
                    if (this.reject.reason === '__custom') return this.reject.custom.trim();
                    return this.reject.reason;
                },
            },
            methods: {
                statusLabel(s) { return statusMeta(s).label; },
                statusBadge(s) { return statusMeta(s).badge; },
                typeLabel(t) { return typeMeta(t).label; },
                typeColor(t) { return typeMeta(t).color; },
                actionLabel(s) { return ACTION_LABEL[s] || s; },
                primaryAction(status) {
                    return nextStatuses(status).find((s) => s !== 'rejected') || null;
                },
                canReject(status) {
                    return nextStatuses(status).includes('rejected');
                },

                async loadMine() {
                    try {
                        const res = await window.axios.get('/api/dispatches/mine', { headers: { Accept: 'application/json' } });
                        const all = res.data.data || [];
                        // 이 행사 + 활성/표시대상만(완료·거절도 보이되 최신 우선)
                        this.dispatches = all
                            .filter((d) => d.project && d.project.id === this.projectId)
                            .map((d) => ({
                                dispatch_id: d.dispatch_id,
                                status: d.status,
                                note: d.note,
                                request: d.request || {},
                                requester: null, // mine 엔 연락처 없음(실시간 assigned 수신분만)
                            }));
                    } catch (e) {
                        console.error('[dispatch] mine 조회 실패', e);
                    }
                },

                async _subscribe() {
                    const echo = await this._waitForEcho();
                    if (!echo) { this.wsState = 'polling'; this._startPolling(); return; }

                    const uid = window.__authUserId;
                    echo.private(`event.${this.projectId}.dispatch.${uid}`)
                        .listen('.dispatch.assigned', (e) => this._onAssigned(e));

                    const conn = echo.connector?.pusher?.connection;
                    if (conn) {
                        conn.bind('state_change', ({ current }) => {
                            if (current === 'connected') { this.wsState = 'ws'; this._stopPolling(); }
                            else if (['unavailable', 'failed', 'disconnected'].includes(current)) { this._startPolling(); }
                        });
                        if (conn.state === 'connected') this.wsState = 'ws';
                    } else { this._startPolling(); }
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

                _onAssigned(payload) {
                    // 알림: 진동 + 비프(DS-3.3 §1)
                    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
                    beep();

                    const d = {
                        dispatch_id: payload.dispatch_id,
                        status: 'assigned',
                        note: payload.note,
                        request: payload.request || {},
                        requester: payload.request ? {
                            name: payload.request.requester_name,
                            phone: payload.request.requester_phone,
                        } : null,
                    };
                    // 중복 방지 후 prepend
                    this.dispatches = this.dispatches.filter((x) => x.dispatch_id !== d.dispatch_id);
                    this.dispatches.unshift(d);
                    this.alert = { open: true, dispatch: d };
                },

                dismissAlert() { this.alert.open = false; },

                async transition(d, status) {
                    if (this.busy[d.dispatch_id]) return;
                    this.busy[d.dispatch_id] = true;
                    this.errors[d.dispatch_id] = '';
                    try {
                        const res = await window.axios.patch(`/api/dispatches/${d.dispatch_id}/status`,
                            { status }, { headers: { Accept: 'application/json' } });
                        d.status = res.data.data.status; // 서버 최신 상태
                        // 출동(en_route) 시 카카오내비(고정좌표)
                        if (status === 'en_route' && d.request.latitude) {
                            openKakaoNavi(d.request.latitude, d.request.longitude, d.request.address || '신고 위치');
                        }
                    } catch (e) {
                        // 422(잘못된 전이/경합) → 안내 + 재동기화
                        this.errors[d.dispatch_id] = e.response?.status === 422
                            ? '처리할 수 없는 상태입니다.' : '전이에 실패했습니다.';
                        await this.loadMine();
                    } finally {
                        this.busy[d.dispatch_id] = false;
                    }
                },

                navi(d) {
                    if (d.request.latitude) openKakaoNavi(d.request.latitude, d.request.longitude, d.request.address || '신고 위치');
                },

                openReject(d) { this.reject = { open: true, dispatch: d, reason: '', custom: '' }; },
                closeReject() { this.reject.open = false; },
                async submitReject() {
                    const reason = this.rejectReasonValue;
                    if (!reason) return;
                    const d = this.reject.dispatch;
                    this.reject.open = false;
                    this.busy[d.dispatch_id] = true;
                    try {
                        const res = await window.axios.patch(`/api/dispatches/${d.dispatch_id}/status`,
                            { status: 'rejected', reject_reason: reason },
                            { headers: { Accept: 'application/json' } });
                        d.status = res.data.data.status;
                    } catch (e) {
                        this.errors[d.dispatch_id] = '거절 처리에 실패했습니다.';
                        await this.loadMine();
                    } finally {
                        this.busy[d.dispatch_id] = false;
                    }
                },

                _startPolling() {
                    if (this._pollTimer) return;
                    this.wsState = 'polling';
                    this._pollTimer = setInterval(() => this.loadMine(), 12000);
                },
                _stopPolling() {
                    if (this._pollTimer) { clearInterval(this._pollTimer); this._pollTimer = null; }
                },
            },
            async mounted() {
                window.__dispatchApp = this; // 브라우저 QA용

                // 오디오 unlock(첫 제스처) + 위치공유 시작
                const unlock = () => { beep(); document.removeEventListener('click', unlock); };
                document.addEventListener('click', unlock, { once: true });

                await this.loadMine();
                await this._subscribe();

                // 구급대원도 위치 송신(FE-2.2 재사용)
                this.sharer = createLocationSharer({ projectId: this.projectId });
                this.sharer.enable();
            },
            beforeUnmount() {
                this._stopPolling();
                if (this.sharer) this.sharer.destroy();
            },
        }).mount('#dispatchApp');
    </script>
</x-layouts.app>
