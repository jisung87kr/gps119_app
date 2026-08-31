{{--
    참가자 활동 화면 — 위치 자동공유 토글. 시안이 없는 화면이라
    design-system.html 어휘로 파생했다. 스크립트 동작은 기존과 동일.
--}}
<x-layouts.app :title="'GPS119 - '.$project->name" :heading="$project->name" :back="route('dashboard')">
    <!-- Vue 3 (페이지별 마운트 — 기존 request/create·event/join 패턴 계승) -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    <div id="eventActiveApp"
         class="space-y-6"
         data-project-id="{{ $project->id }}"
         data-role="{{ $role }}"
         data-role-label="{{ $roleLabel }}"
         data-project-name="{{ $project->name }}"
         data-sharing="{{ $sharing ? '1' : '0' }}">

        {{-- 내 역할 --}}
        <div class="flex items-center gap-2">
            <span class="text-sm font-semibold text-ink-500">내 역할</span>
            <x-ui.badge :tone="\App\Enums\EventRole::from($role)->badgeTone()"
                        :icon="\App\Enums\EventRole::from($role)->icon()" size="sm">
                {{ $roleLabel }}
            </x-ui.badge>
        </div>

        {{-- 위치 공유 --}}
        <x-ui.card>
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-base font-bold text-ink-950">실시간 위치 공유</p>
                    <p class="mt-0.5 text-sm leading-relaxed text-ink-500">
                        상황실이 내 위치를 지도에서 확인합니다.
                    </p>
                </div>

                <button type="button" v-on:click="toggle" role="switch" :aria-checked="sharing"
                        :class="sharing ? 'bg-brand-600' : 'bg-ink-300'"
                        class="relative inline-flex h-7 w-12 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-brand-200">
                    <span :class="sharing ? 'translate-x-5' : 'translate-x-0.5'"
                          class="inline-block h-6 w-6 translate-y-0.5 transform rounded-full bg-white shadow transition-transform"></span>
                </button>
            </div>

            {{--
                🔑 **컨트롤은 토글 «하나»다.** 예전에는 토글 옆에 「항상 허용으로 바꾸기」·
                   「설정 열기」가 전체 너비 버튼으로 나란히 있어서, 서로 다른 축(공유 «의도»
                   vs OS «권한»)이 동급으로 보였다 — 사용자는 무엇을 눌러야 공유가 켜지는지
                   알 수 없었다. 권한 조치는 «상태에 딸린 조치»로 내린다.

                🔴 그리고 상태가 sharing 만 보고 「위치 공유 중」이라 말하고 있었다.
                   권한이 거부돼도 초록 불이 켜졌다 — 바로 아래에서 「전혀 전달되지
                   않습니다」라고 말하면서. M-5 가 막으려던 그 거짓 안심을 참가자 본인
                   화면에서 하고 있었다. 이제 상태는 권한까지 보고 말한다.
            --}}
            <div class="mt-5 rounded-2xl p-4" :class="shareStatus.bg">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" :class="shareStatus.dot"></span>
                    <span class="text-sm font-bold" :class="shareStatus.text">@{{ shareStatus.label }}</span>
                </div>

                <p v-if="shareStatus.hint" class="mt-1.5 text-sm leading-relaxed text-ink-600">
                    @{{ shareStatus.hint }}
                    <button v-if="shareStatus.action" type="button"
                            v-on:click="shareStatus.action === 'settings' ? openSettings() : requestAlways()"
                            class="font-bold text-brand-600 underline underline-offset-2">
                        @{{ shareStatus.action === 'settings' ? '설정 열기' : '항상 허용으로 바꾸기' }} →
                    </button>
                </p>

                <p v-if="settingsOpenFailed" class="mt-1.5 text-xs text-ink-500">
                    설정을 열지 못했습니다. 기기 설정 → GPS119 → 위치 에서 직접 허용해 주세요.
                </p>

                {{-- 사용자에게 의미 있는 둘만. 「대기 중」은 네트워크가 끊겼을 때
                     유일한 단서라 그때만 덧붙인다. --}}
                <p v-if="sharing" class="mt-3 text-xs text-ink-500">
                    마지막 전송 @{{ lastSentLabel }} · 정확도 @{{ accuracyLabel }}
                    <span v-if="bufferedCount" class="font-bold text-warning-600">
                        · 대기 @{{ bufferedCount }}건
                    </span>
                </p>
            </div>

            {{-- 🔑 상태가 이미 권한 문제를 말하고 있으면 오류줄을 겹쳐 띄우지 않는다.
                 「위치 공유 중」 옆에 빨간 거부 문구가 같이 뜨던 것이 이 화면의 결함이었다.
                 남는 것은 타임아웃 같은 «일시적» 오류뿐이다. --}}
            <p v-if="error && shareStatus.tone !== 'danger'"
               class="mt-3 text-sm font-bold text-danger-600">@{{ error }}</p>
        </x-ui.card>

        {{-- 🔑 예전에는 여기에 「3초 후 구조요청 화면으로 이동」 카운트다운이 있었다.
             그게 관제 지도가 텅 비는 «직접 원인»이었다 — 참가자는 첫 좌표 1건만 보내고
             페이지를 떠나고, 페이지를 떠나면 watchPosition 이 죽는다. 결과적으로 일반
             참가자는 「행사 입장 시각의 좌표」에 박제된 핀으로 남았다.
             이 화면은 이제 «머무는» 화면이다. 신고는 아래 버튼으로 언제든 갈 수 있다. --}}

        {{-- 다음 단계 --}}
        <div class="space-y-3">
            @if ($canDispatch)
                {{-- 구급대/자원봉사 구급: 지령·출동 화면이 주 화면 --}}
                <x-ui.button :href="route('events.dispatch', $project->id)">
                    <x-ui.icon name="ambulance" class="h-5 w-5" />
                    지령·출동 화면
                </x-ui.button>
                <x-ui.button :href="route('request.create.project', $project->slug)" variant="secondary">구조요청 화면으로</x-ui.button>
            @else
                {{-- 이 화면에 머무는 동안 위치가 계속 공유된다. 신고는 여기서 바로 간다.
                     🔴 slug 경로로 보낸다. route('request.create') 로 보냈더니 이 행사에
                        입장한 사람의 신고가 「상시 운영」에 붙어서 «그 행사 관제에 안 떴다». --}}
                <x-ui.button :href="route('request.create.project', $project->slug)">
                    <x-ui.icon name="ambulance" class="h-5 w-5" />
                    구조요청 하기
                </x-ui.button>
            @endif
            <x-ui.button :href="route('dashboard')" variant="ghost">홈으로</x-ui.button>
        </div>
    </div>

    <script type="module">
        {{--
            🔴 **캐시 버스팅이 필요하다.** 이 파일은 Vite 번들이 아니라 public/ 에서
               «원본 그대로» 서빙되므로 해시가 안 붙는다. 그래서 파일을 고쳐도 웹뷰가
               옛 사본을 계속 쓴다 — 실기기에서 3시간 전 사본을 물고 있어 새로 추가한
               메서드가 없었고, 버튼을 눌러도 «아무 일도 안 일어났다»(2026-08-31).
               증상이 「버튼이 안 눌린다」 하나뿐이라 UI 문제로 오진하기 쉽다.
        --}}
        import { createLocationSharer } from '/js/components/locationShare.js?v={{ @filemtime(public_path('js/components/locationShare.js')) ?: time() }}';

        const { createApp } = Vue;

        // 톤 → 클래스. «판정»은 번들에, «표현»은 화면에 둔다.
        const TONE = {
            ok:      { bg: 'bg-brand-50',   dot: 'animate-pulse bg-brand-600', text: 'text-brand-600' },
            warning: { bg: 'bg-warning-50', dot: 'bg-warning-600',             text: 'text-warning-600' },
            danger:  { bg: 'bg-danger-50',  dot: 'bg-danger-600',              text: 'text-danger-700' },
            muted:   { bg: 'bg-ink-50',     dot: 'bg-ink-300',                 text: 'text-ink-500' },
        };

        const root = document.getElementById('eventActiveApp');

        createApp({
            data() {
                return {
                    projectId: Number(root.dataset.projectId),
                    role: root.dataset.role,
                    roleLabel: root.dataset.roleLabel,
                    projectName: root.dataset.projectName,
                    // sharer state 미러. 초기값은 «서버가 아는 의도»다.
                    sharing: root.dataset.sharing === '1',
                    permission: 'prompt',
                    sentCount: 0,
                    lastSentAt: null,
                    lastAccuracy: null,
                    bufferedCount: 0,
                    error: null,
                    // 위치 권한 3단계 (02 §4). 판정은 번들의 순수 함수가 한다.
                    osPermission: null,
                    permissionStep: 'none',
                    settingsOpenFailed: false,
                };
            },
            watch: {
                // 🔴 공유를 껐다 켜면 단계가 «낡은 채로» 남는다. decidePermissionStep 은
                //    sharing 을 조건으로 쓰는데(끄고 있는 사람에게 배경 권한을 조르지
                //    않으려고), 토글이 바뀔 때 다시 계산하지 않으면 카드가 사라진 채
                //    돌아오지 않는다. 실기기에서 토글을 만지다 발견했다(2026-08-31).
                sharing() {
                    this.applyPermission(this.osPermission);
                },
            },
            computed: {
                /**
                 * 상태 한 줄 — 판정은 번들의 shareStatus() 가 한다(Vitest 로 고정).
                 * 여기에 매트릭스를 두면 두 벌이 되어 어긋난다(0-8 의 교훈).
                 */
                shareStatus() {
                    const s = window.__gps119Bridge?.shareStatus?.({
                        sharing: this.sharing,
                        permissionStep: this.permissionStep,
                        osPermission: this.osPermission,
                        webPermission: this.permission,
                    }) ?? { label: this.sharing ? '위치 공유 중' : '공유 중지됨', hint: null, action: null, tone: this.sharing ? 'ok' : 'muted' };

                    return { ...s, ...TONE[s.tone] };
                },

                lastSentLabel() {
                    if (!this.lastSentAt) return '-';
                    try {
                        return new Date(this.lastSentAt).toLocaleTimeString('ko-KR',
                            { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    } catch (e) { return '-'; }
                },
                accuracyLabel() {
                    return this.lastAccuracy != null ? `±${this.lastAccuracy}m` : '-';
                },
            },
            methods: {
                // 권한을 읽어 서버에 보고하고(M-5) 화면 단계를 갱신한다.
                async syncPermission() {
                    const bridge = window.__gps119Bridge;
                    if (!bridge?.reportLocationPermission) return;

                    this.applyPermission(await bridge.reportLocationPermission(this.projectId));
                },

                applyPermission(permission) {
                    this.osPermission = permission;
                    this.permissionStep = window.__gps119Bridge?.decidePermissionStep?.({
                        native: window.__gps119Bridge?.isNativeApp,
                        permission,
                        sharing: this.sharing,
                    }) ?? 'none';
                },

                // 2단계 — 설명을 읽은 뒤 「항상 허용」 OS 프롬프트로 간다.
                //
                // 🔑 프롬프트를 직접 못 띄운다. 이 플러그인은 addWatcher 안에서만
                //    권한을 요청하므로(requestPermissions), 추적을 «다시 시작»하는 것이
                //    곧 요청이다. 그래서 껐다 켠다.
                async requestAlways() {
                    // 🔑 공유를 끄지 «않는다». 취득만 다시 시작하면서 이번에는 권한을
                    //    요청한다 — 이 플러그인은 addWatcher 가 유일한 프롬프트 통로다.
                    await this.sharer.restart({ requestPermissions: true });
                    await this.syncPermission();
                },

                // 3단계 — 설정 앱으로 보낸다.
                async openSettings() {
                    const ok = await window.__gps119Bridge?.openLocationSettings?.();
                    this.settingsOpenFailed = !ok;
                },

                applyState(s) {
                    this.sharing = s.sharing;
                    this.permission = s.permission;
                    this.sentCount = s.sentCount;
                    this.lastSentAt = s.lastSentAt;
                    this.lastAccuracy = s.lastAccuracy;
                    this.bufferedCount = s.bufferedCount;
                    this.error = s.error;
                },
                toggle() {
                    this.sharer.toggle();
                },
            },
            mounted() {
                this.sharer = createLocationSharer({
                    projectId: this.projectId,
                    onChange: (s) => this.applyState(s),
                    // 위치 «취득»을 셸에 위임한다 (N3 / 02 §3-3).
                    // 앱이고 그 앱이 백그라운드 위치를 «아는» 경우에만 값이 있다.
                    // 없으면(웹·구버전 셸) undefined 라 기존 watchPosition 경로로 떨어진다 —
                    // 「앱이면 네이티브」가 아니라 「그 앱이 그 기능을 아는가」로 판정한다.
                    tracker: window.__gps119Bridge?.locationTracker,
                });
                // 브라우저 QA용 전역 노출(셀렉터/제어 가능)
                window.__locationShare = this.sharer;
                // 🔑 **서버가 「공유 중」이라고 한 사람만 이어받는다.**
                //    resume() 은 enable() 과 달리 PATCH 를 보내지 않는다 — 매번 켜면
                //    사용자가 «끈» 공유가 화면을 옮기는 것만으로 되살아난다.
                //    (그게 이 화면의 실제 결함이었다. 2026-08-31)
                //    공유는 «참가할 때» 한 번 켜지고(joinByCode), 그 뒤로는 토글이 정한다.
                if (this.sharing) {
                    this.sharer.resume();
                }

                // 2·3단계는 여기서 갈린다. 🔴 공유를 켜기 «전»에도 읽는다 —
                // 「켜고 나서야 막힌 걸 아는」 순서면 대원은 이미 현장에 있다.
                this.syncPermission();

                // iOS 는 「항상 허용」을 나중에 다시 물어보고, 사용자가 「사용 중」으로
                // 되돌리면 배경 추적이 «조용히» 끊긴다. 복귀할 때마다 다시 읽는다.
                this.stopWatchingPermission = window.__gps119Bridge?.watchPermissionChanges?.(
                    this.projectId,
                    (p) => this.applyPermission(p),
                ) ?? (() => {});
            },
            beforeUnmount() {
                if (this.sharer) this.sharer.destroy();
                this.stopWatchingPermission?.();
            },
        }).mount('#eventActiveApp');
    </script>
</x-layouts.app>
