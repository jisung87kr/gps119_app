{{--
    구급대원 지령(출동) 앱 — 시안이 없는 화면이라 design-system.html 어휘로 파생했다.
    배지 색은 dispatchMeta.js(= App\Enums\DispatchStatus 미러)에서 온다.
    Vue 화면이라 Blade 컴포넌트를 쓸 수 없는 동적 부분은 클래스를 직접 적었다.
    스크립트 동작은 기존과 동일.
--}}
<x-layouts.app :title="'GPS119 - 지령·출동'" :heading="$project->name" :back="route('events.active', $project->id)">
    <!-- Vue 3 (페이지별 마운트 — 기존 패턴 계승) -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <!-- 개인 지령 채널(event.{id}.dispatch.{userId}) 구독용 본인 id -->
    <script>window.__authUserId = {{ auth()->id() }};</script>

    <div id="dispatchApp" v-cloak
         class="space-y-4"
         data-project-id="{{ $project->id }}"
         data-role="{{ $role }}"
         data-role-label="{{ $roleLabel }}"
         data-project-name="{{ $project->name }}">

        {{-- 상황실 지령 회수 알림 (ADR-0007).

             🔑 목록에서 «조용히 사라지게» 두면 이 기능은 아무것도 못 막는다 — 이미
                현장으로 달리고 있는 대원은 화면을 안 보고 있고, 다음에 봤을 때는
                「어? 없어졌네」로 끝난다. 그래서 (1) 진동·비프로 화면을 보게 만들고
                (2) 목록 맨 위에 «멈추라»는 문장과 사유를 남긴다. 자동으로 사라지지
                않고 대원이 [확인]을 눌러야 닫힌다. --}}
        <div v-if="recalled.open"
             class="flex items-start gap-3 rounded-2xl border-2 border-warning-500 bg-warning-50 p-4">
            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-warning-600">
                <x-ui.icon name="x-circle" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-base font-extrabold text-ink-950">상황실이 지령을 회수했습니다</p>
                <p class="mt-0.5 text-sm font-bold text-ink-800">
                    <template v-if="recalled.requestId">신고 #@{{ recalled.requestId }} — </template>출동을 중지하세요.
                </p>
                <p v-if="recalled.reason" class="mt-1.5 break-keep text-sm text-ink-600">사유: @{{ recalled.reason }}</p>
                <p v-else class="mt-1.5 text-sm text-ink-400">사유가 전달되지 않았습니다. 상황실에 확인하세요.</p>
            </div>
            <button type="button" v-on:click="dismissRecalled"
                    class="min-h-[44px] shrink-0 rounded-xl border-2 border-warning-500 px-3 text-sm font-bold text-warning-600 active:bg-white">
                확인
            </button>
        </div>

        {{-- 역할 + 연결 상태 --}}
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
                <x-ui.badge :tone="\App\Enums\EventRole::from($role)->badgeTone()"
                            :icon="\App\Enums\EventRole::from($role)->icon()" size="sm">
                    {{ $roleLabel }}
                </x-ui.badge>
                <span class="truncate text-sm text-ink-400">지령 수신 대기</span>
            </div>

            <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-bold"
                  :class="wsState==='ws' ? 'text-success-600' : (wsState==='polling' ? 'text-warning-600' : 'text-ink-400')">
                <span class="h-1.5 w-1.5 rounded-full"
                      :class="wsState==='ws' ? 'bg-success-500' : (wsState==='polling' ? 'bg-warning-500' : 'bg-ink-300')"></span>
                @{{ wsState==='ws' ? '실시간' : (wsState==='polling' ? '폴링' : '연결중') }}
            </span>
        </div>

        {{-- 내 지령 목록 --}}
        <div v-if="dispatches.length === 0"
             class="flex flex-col items-center gap-2.5 rounded-2xl border border-ink-200 bg-white px-5 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-300">
                <x-ui.icon name="bell" class="h-6 w-6" />
            </span>
            <p class="text-sm font-medium text-ink-400">배정된 지령이 없습니다.</p>
        </div>

        <div v-for="d in dispatches" :key="d.dispatch_id"
             class="rounded-2xl border border-ink-200 bg-white p-5">
            <div class="flex items-center justify-between gap-2">
                <span class="flex min-w-0 items-center gap-2">
                    <span class="text-base font-extrabold text-ink-950">신고 #@{{ d.request.id }}</span>
                    <span class="inline-flex shrink-0 items-center rounded-full border border-ink-200 px-2.5 py-1 text-xs font-bold"
                          :style="{ color: typeColor(d.request.type) }">@{{ typeLabel(d.request.type) }}</span>
                </span>
                <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-bold"
                      :class="statusBadge(d.status)">@{{ statusLabel(d.status) }}</span>
            </div>

            <p v-if="d.request.address" class="mt-2.5 flex items-start gap-1.5 break-keep text-base font-bold leading-snug text-ink-950">
                <x-ui.icon name="pin" class="mt-0.5 h-[18px] w-[18px] shrink-0 text-danger-600" />
                <span>@{{ d.request.address }}</span>
            </p>

            {{-- 본인 배정건 신고자 연락처(ADR-0004) — 실시간 수신분만 --}}
            <p v-if="d.requester" class="mt-1.5 text-sm text-ink-600">
                @{{ d.requester.name }}
                <a v-if="d.requester.phone" :href="'tel:'+d.requester.phone"
                   class="ml-1 font-bold text-brand-600 underline underline-offset-2">@{{ d.requester.phone }} · 전화</a>
            </p>

            <p v-if="d.note" class="mt-1.5 text-sm text-ink-400">메모: @{{ d.note }}</p>

            {{-- 전이 버튼: 현재 상태의 allowedTransitions 만 --}}
            <div class="mt-4 flex gap-2.5">
                <button v-if="primaryAction(d.status)" type="button"
                        v-on:click="transition(d, primaryAction(d.status))" :disabled="busy[d.dispatch_id]"
                        class="min-h-[56px] flex-1 rounded-2xl bg-brand-600 px-4 text-base font-bold text-white shadow-sm transition-colors active:bg-brand-700 disabled:cursor-not-allowed disabled:bg-ink-100 disabled:text-ink-400 disabled:shadow-none">
                    @{{ actionLabel(primaryAction(d.status)) }}
                </button>
                <button v-if="canReject(d.status)" type="button" v-on:click="openReject(d)"
                        :disabled="busy[d.dispatch_id]"
                        class="min-h-[56px] rounded-2xl border-2 border-danger-200 px-4 text-sm font-bold text-danger-600 transition-colors active:bg-danger-50 disabled:cursor-not-allowed disabled:opacity-50">
                    거절
                </button>
                <button v-if="d.status !== 'rejected'" type="button" v-on:click="navi(d)"
                        class="min-h-[56px] rounded-2xl border-2 border-ink-200 px-4 text-sm font-bold text-ink-900 transition-colors active:bg-ink-50">
                    길안내
                </button>
            </div>

            <p v-if="errors[d.dispatch_id]" class="mt-2.5 text-sm font-bold text-danger-600">
                @{{ errors[d.dispatch_id] }}
            </p>
        </div>

        {{-- 풀스크린 신규 지령 알림 --}}
        <div v-if="alert.open" id="dispatch-fullscreen-alert"
             class="fixed inset-0 z-[300] flex flex-col items-center justify-center bg-brand-600 px-6 text-white">
            <div class="animate-pulse text-center">
                <p class="mb-2 text-sm font-bold text-white/80">새 지령 배정</p>
                <p v-if="alert.dispatch" class="mb-5 text-3xl font-extrabold">신고 #@{{ alert.dispatch.request.id }}</p>
            </div>

            <div v-if="alert.dispatch" class="w-full max-w-sm rounded-3xl bg-white p-5 text-ink-950 shadow-2xl">
                <span class="inline-flex items-center rounded-full border border-ink-200 px-2.5 py-1 text-xs font-bold"
                      :style="{ color: typeColor(alert.dispatch.request.type) }">
                    @{{ typeLabel(alert.dispatch.request.type) }}
                </span>

                <p v-if="alert.dispatch.request.address"
                   class="mt-2.5 break-keep text-lg font-extrabold leading-snug text-ink-950">
                    @{{ alert.dispatch.request.address }}
                </p>

                <p v-if="alert.dispatch.requester" class="mt-1.5 text-sm text-ink-600">
                    @{{ alert.dispatch.requester.name }}
                    <a v-if="alert.dispatch.requester.phone" :href="'tel:'+alert.dispatch.requester.phone"
                       class="ml-1 font-bold text-brand-600 underline underline-offset-2">@{{ alert.dispatch.requester.phone }}</a>
                </p>

                <p v-if="alert.dispatch.note" class="mt-1.5 text-sm text-ink-400">메모: @{{ alert.dispatch.note }}</p>

                <button type="button" v-on:click="dismissAlert"
                        class="mt-5 min-h-[56px] w-full rounded-2xl bg-brand-600 text-base font-bold text-white active:bg-brand-700">
                    확인
                </button>
            </div>
        </div>

        {{-- 거절 사유 모달 --}}
        <div v-if="reject.open" class="fixed inset-0 z-[310] flex items-center justify-center bg-ink-950/40 px-5">
            <div class="w-full max-w-sm rounded-3xl bg-white p-5">
                <h2 class="text-lg font-extrabold text-ink-950">지령 거절</h2>

                <div class="mt-3 divide-y divide-ink-100">
                    <label v-for="r in rejectPresets" :key="r"
                           class="flex cursor-pointer items-center gap-2.5 py-3 text-base text-ink-800">
                        <input type="radio" name="reject" :value="r" v-model="reject.reason"
                               class="h-5 w-5 border-ink-300 text-danger-600 focus:ring-danger-200">
                        <span>@{{ r }}</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2.5 py-3">
                        <input type="radio" name="reject" value="__custom" v-model="reject.reason"
                               class="h-5 w-5 shrink-0 border-ink-300 text-danger-600 focus:ring-danger-200">
                        <input v-model="reject.custom" v-on:focus="reject.reason='__custom'" type="text"
                               placeholder="기타 사유"
                               class="min-w-0 flex-1 rounded-xl border-2 border-ink-200 px-3 py-2 text-base text-ink-950 placeholder:text-ink-400 focus:border-brand-600 focus:outline-none">
                    </label>
                </div>

                <div class="mt-5 space-y-2.5">
                    <button type="button" v-on:click="submitReject" :disabled="!rejectReasonValue"
                            class="w-full rounded-2xl bg-danger-600 py-4 text-base font-bold text-white shadow-sm active:bg-danger-700 disabled:cursor-not-allowed disabled:bg-ink-100 disabled:text-ink-400 disabled:shadow-none">
                        거절 제출
                    </button>
                    <x-ui.button variant="secondary" vue-click="closeReject">취소</x-ui.button>
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
                    // 상황실 회수 배너(ADR-0007). 대원이 [확인]을 누를 때까지 유지된다.
                    recalled: { open: false, requestId: null, reason: '' },
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
                            // 회수(cancelled)된 지령은 목록에서 뺀다. 대원이 취할 수 있는
                            // 조작이 하나도 없고(전이표상 terminal), 회수 사실은 배너가
                            // 이미 말했다. 남겨 두면 「배정」으로 잘못 표시되기도 한다 —
                            // dispatchMeta.js 의 DISPATCH_STATUS_META 에 cancelled 항목이
                            // 없어 statusMeta() 가 assigned 로 폴백한다.
                            .filter((d) => d.status !== 'cancelled')
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
                        .listen('.dispatch.assigned', (e) => this._onAssigned(e))
                        .listen('.dispatch.recalled', (e) => this._onRecalled(e));

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

                // 상황실이 지령을 회수했다(ADR-0007).
                //
                // 🔑 «회수 버튼은 여기 없다» — 회수는 관제의 결정이고 서버가 대원에게
                //    403 을 준다. 대원이 못 가는 상황을 표현하는 건 기존 [거절]이다.
                //    이 핸들러가 하는 일은 하나: 이미 달리고 있는 사람을 멈춰 세우는 것.
                _onRecalled(payload) {
                    if (!payload || !payload.dispatch_id) return;

                    const target = this.dispatches.find((x) => x.dispatch_id === payload.dispatch_id);

                    // 배정 알림과 «구분되는» 패턴(짧게 세 번). 같은 진동이면 새 지령으로 읽는다.
                    if (navigator.vibrate) navigator.vibrate([120, 90, 120, 90, 120]);
                    beep();

                    // 회수된 그 지령을 풀스크린 알림/거절 모달이 붙잡고 있으면 먼저 놓는다.
                    // (이미 사라진 지령을 수락·거절하면 서버가 422 로 돌려준다.)
                    if (this.alert.open && this.alert.dispatch
                        && this.alert.dispatch.dispatch_id === payload.dispatch_id) {
                        this.alert.open = false;
                    }
                    if (this.reject.open && this.reject.dispatch
                        && this.reject.dispatch.dispatch_id === payload.dispatch_id) {
                        this.reject.open = false;
                    }

                    this.dispatches = this.dispatches.filter((x) => x.dispatch_id !== payload.dispatch_id);

                    this.recalled = {
                        open: true,
                        requestId: payload.request_id || (target ? target.request.id : null),
                        reason: payload.reason || '',
                    };

                    // 목록을 아래까지 스크롤한 채로 받으면 배너가 화면 밖이다.
                    try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo(0, 0); }
                },

                dismissRecalled() { this.recalled.open = false; },

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
