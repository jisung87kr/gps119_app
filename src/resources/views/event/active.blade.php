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
                            v-on:click="shareStatus.action === 'settings' ? openSettings() : openDisclosure()"
                            class="font-bold text-brand-600 underline underline-offset-2">
                        @{{ shareStatus.action === 'settings' ? '설정 열기' : '항상 허용으로 바꾸기' }}
                    </button>
                </p>

                <p v-if="alwaysPromptUnavailable" class="mt-2 text-sm leading-relaxed text-ink-600">
                    권한 창이 뜨지 않았습니다. 설정에서 「항상 허용」을 선택해 주세요.
                    <button type="button" v-on:click="openSettings"
                            class="font-bold text-brand-600 underline underline-offset-2">설정 열기</button>
                </p>

                <p v-if="settingsOpenFailed" class="mt-1.5 text-xs text-ink-500">
                    설정을 열지 못했습니다. 설정 앱에서 GPS119 › 위치를 열어 주세요.
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

            {{--
                배터리 최적화 경고 (M-26).

                🔴 **이게 없으면 화면을 끄는 순간 위치 전송이 «완전히» 멈춘다**
                   (실측 0건 → 예외 등록 후 9건). 그런데 앱은 멀쩡히 돌고 알림도 떠
                   있어서 **사용자도 관제도 끊긴 줄 모른다** — M-5 가 막으려던
                   «거짓 안심»과 같은 종류라 반드시 말해야 한다.

                🔑 판정은 번들의 순수 함수가 한다(Vitest). 안드로이드가 아니거나,
                   공유가 꺼져 있거나, 이미 제외돼 있거나, «모르면» 뜨지 않는다.
            --}}
            <div v-if="batteryWarn" class="mt-3 rounded-2xl bg-warning-50 p-4">
                <p class="text-sm font-bold text-warning-700">@{{ batteryText.title }}</p>
                <p class="mt-1.5 break-keep text-sm leading-relaxed text-ink-600">@{{ batteryText.body }}</p>
                <button type="button" v-on:click="openBatterySettings"
                        class="mt-2.5 text-sm font-bold text-brand-600 underline underline-offset-2">
                    @{{ batteryText.action }}
                </button>
                <p v-if="batterySettingsFailed" class="mt-1.5 text-xs text-ink-500">
                    설정을 열지 못했습니다. 설정 앱에서 배터리 › 사용량 관리 › 절전 제외에 GPS119 를 추가해 주세요.
                </p>
            </div>

            {{-- 🔑 상태가 이미 권한 문제를 말하고 있으면 오류줄을 겹쳐 띄우지 않는다.
                 「위치 공유 중」 옆에 빨간 거부 문구가 같이 뜨던 것이 이 화면의 결함이었다.
                 남는 것은 타임아웃 같은 «일시적» 오류뿐이다. --}}
            <p v-if="sharing && error && shareStatus.tone !== 'danger'"
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

        {{--
            백그라운드 위치 사전 고지 (Play 정책 / docs/store/background-location-video.md).

            🔴 **바깥을 눌러서 닫히지 않는다.** 「다음」이나 「나중에」를 눌러야 한다 —
               사용자가 «지나칠 수 있는» 고지는 고지로 인정되지 않는다.
            🔑 문구는 두 가지를 반드시 말해야 한다: 앱을 «쓰지 않는 동안에도» 수집한다는
               사실과, 그것이 «무엇에 쓰이는지». 하나라도 빠지면 반려 사유다.
        --}}
        {{--
            약관 동의 (위치정보법). 🔴 **공유를 켜는 «그 자리»에서 받는다.**
            동의 페이지로 내보내면 사용자는 행사 화면을 잃고, 돌아왔을 때 무엇을
            하려던 참이었는지 다시 찾아야 한다. 동의가 끝나면 원래 하려던
            「공유 켜기」를 이어서 실행한다.

            🔑 항목·문구는 «서버가 준 것»을 그대로 쓴다. 화면에서 목록을 다시 만들면
               약관이 늘거나 바뀔 때 한쪽만 고쳐진다.
        --}}
        <div v-if="consentItems.length"
             class="fixed inset-0 z-[130] flex items-end justify-center bg-ink-950/40 px-0 sm:items-center sm:px-5"
             role="dialog" aria-modal="true" aria-labelledby="consentTitle">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-md sm:rounded-3xl">
                <h2 id="consentTitle" class="break-keep text-lg font-extrabold leading-snug text-ink-950">
                    위치를 공유하려면 약관 동의가 필요합니다
                </h2>
                <p class="mt-2 break-keep text-sm leading-relaxed text-ink-600">
                    상황실에 위치를 전달하려면 아래 항목에 동의해 주세요.
                </p>

                <div class="mt-4 space-y-3 rounded-2xl border border-ink-200 p-4">
                    <label v-for="item in consentItems" :key="item.type"
                           class="flex items-start gap-3 text-sm leading-relaxed text-ink-600">
                        <input type="checkbox" :value="item.type" v-model="consentChecked"
                               class="mt-0.5 h-5 w-5 shrink-0 rounded border-ink-300 text-brand-600 focus:ring-brand-200">
                        <span>
                            <span class="font-bold text-ink-900">[필수]</span>
                            <a :href="item.url" target="_blank" rel="noopener"
                               class="font-bold text-brand-600 underline underline-offset-2">@{{ item.label }}</a>에
                            동의합니다.
                        </span>
                    </label>
                </div>

                <p v-if="consentError" class="mt-3 text-sm font-bold text-danger-600">@{{ consentError }}</p>

                <div class="mt-6 space-y-2.5">
                    <button type="button" v-on:click="agreeConsent" :disabled="consentSubmitting"
                            class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-600 py-4 text-base font-bold text-white shadow-sm transition-colors active:bg-brand-700 disabled:cursor-not-allowed disabled:bg-ink-100 disabled:text-ink-400">
                        @{{ consentSubmitting ? '저장 중…' : '동의하고 공유 시작' }}
                    </button>
                    {{-- 🔑 나가는 길을 반드시 둔다. 동의는 «강요»가 아니라 선택이어야 한다. --}}
                    <x-ui.button variant="secondary" vue-click="consentItems = []">나중에</x-ui.button>
                </div>
            </div>
        </div>

        <div v-if="disclosureOpen"
             class="fixed inset-0 z-[120] flex items-end justify-center bg-ink-950/40 px-0 sm:items-center sm:px-5"
             role="dialog" aria-modal="true" aria-labelledby="bgLocTitle">
            <div class="max-h-[90vh] w-full overflow-y-auto rounded-t-3xl bg-white p-6 sm:max-w-md sm:rounded-3xl">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                    <x-ui.icon name="pin" class="h-6 w-6" />
                </span>

                <h2 id="bgLocTitle" class="mt-4 break-keep text-lg font-extrabold leading-snug text-ink-950">
                    화면을 꺼도 위치를 보내려면 「항상 허용」이 필요합니다
                </h2>

                <p class="mt-3 break-keep text-sm leading-relaxed text-ink-600">
                    허용하면 GPS119 는 <strong class="font-bold text-ink-950">앱을 보고 있지 않을 때나
                    화면이 꺼져 있는 동안에도</strong> 위치를 수집해 상황실 지도에 표시합니다.
                    사고가 났을 때 구조대가 정확한 지점으로 가기 위해서입니다.
                </p>

                <p class="mt-3 break-keep text-sm leading-relaxed text-ink-600">
                    수집은 <strong class="font-bold text-ink-950">행사에 참가 중이고, 위치 공유를 켜 둔
                    동안에만</strong> 이루어집니다. 공유를 끄면 즉시 멈춥니다.
                </p>

                <div class="mt-6 space-y-2.5">
                    <x-ui.button vue-click="confirmDisclosure">다음</x-ui.button>
                    <x-ui.button variant="secondary" vue-click="disclosureOpen = false">나중에</x-ui.button>
                </div>
            </div>
        </div>
    </div>

    <script type="module">
        import { createLocationSharer } from '/js/components/locationShare.js';

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
                    // 백그라운드 수집 사전 고지(Play 정책). 권한 요청 «앞»에 선다.
                    disclosureOpen: false,
                    // 🔴 서버가 「약관 동의가 필요하다」고 한 항목들(위치정보법).
                    //    가입 폼 도입 «전»에 만들어진 계정이 여기 걸린다.
                    consentItems: [],
                    consentChecked: [],
                    consentSubmitting: false,
                    consentError: null,
                    // 배터리 최적화 (M-26). 안드로이드에서만 값이 찬다.
                    batteryWarn: false,
                    batteryText: null,
                    batterySettingsFailed: false,
                    // 승격을 시도했는데 권한이 그대로였다 = iOS 가 프롬프트를 안 띄웠다.
                    alwaysPromptUnavailable: false,
                    requesting: false,
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
                        native: Boolean(window.__gps119Bridge?.isNativeApp),
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
                    const prev = this.osPermission;
                    this.osPermission = permission;
                    if (permission !== prev) this.alwaysPromptUnavailable = false;

                    // 🔑 **설정에서 고치고 돌아온 순간 스스로 다시 시작한다.**
                    //    iOS 는 거부된 뒤 프롬프트를 다시 못 띄우므로 사용자는 설정으로
                    //    갔다 온다. 그때 재시작이 없으면 화면이 그대로여서 「역시 안 되네」로
                    //    끝난다 — 고친 보람이 사라진다.
                    if (this.sharing && window.__gps119Bridge?.shouldRestartTracking?.(prev, permission)) {
                        this.sharer.restart();
                    }

                    this.permissionStep = window.__gps119Bridge?.decidePermissionStep?.({
                        native: window.__gps119Bridge?.isNativeApp,
                        permission,
                        sharing: this.sharing,
                    }) ?? 'none';
                },

                // 배터리 최적화 (M-26).
                //
                // 🔑 **포그라운드로 돌아올 때마다 다시 읽는다.** 사용자가 설정에서
                //    고치고 돌아오는 흐름이라, 한 번만 읽으면 고쳐도 경고가 남는다 —
                //    권한 3단계에서 같은 실수를 한 적이 있다.
                async syncBattery() {
                    const res = await window.__gps119Bridge?.checkBatteryOptimization?.(this.sharing);
                    this.batteryWarn = Boolean(res?.warn);
                    this.batteryText = window.__gps119Bridge?.BATTERY_WARNING ?? null;
                },

                async openBatterySettings() {
                    const opened = await window.__gps119Bridge?.openBatteryOptimizationSettings?.();
                    this.batterySettingsFailed = opened === false;
                },

                // 🔴 **Play 정책: 사전 고지(prominent disclosure).**
                //    백그라운드 위치를 «요청하기 전에», OS 프롬프트가 아니라 우리 화면으로
                //    「앱을 쓰지 않는 동안에도 수집한다」와 그 «용도»를 말해야 한다.
                //    OS 대화상자는 이 고지를 대신하지 못한다 — 심사에서 그것만으로는
                //    반려된다. 시연 영상의 필수 컷이기도 하다
                //    (docs/store/background-location-video.md 컷 3).
                //
                // 🔑 토스트·스낵바로 띄우면 «사용자가 놓칠 수 있는» 고지라 정책 위반이다.
                //    사용자가 「다음」을 누르기 전에는 권한 요청으로 넘어가지 않는다.
                openDisclosure() {
                    this.disclosureOpen = true;
                },

                async confirmDisclosure() {
                    this.disclosureOpen = false;
                    await this.requestAlways();
                },

                // 2단계 — 설명을 읽은 뒤 「항상 허용」 OS 프롬프트로 간다.
                //
                // 🔑 프롬프트를 직접 못 띄운다. 이 플러그인은 addWatcher 안에서만
                //    권한을 요청하므로(requestPermissions), 추적을 «다시 시작»하는 것이
                //    곧 요청이다. 그래서 껐다 켠다.
                async requestAlways() {
                    // 🔑 연타 방지. 프롬프트가 안 뜨면 사용자는 계속 누르는데, 매번
                    //    권한 보고가 나가 스로틀(429)에 걸리고 위치 ping 까지 밀려난다
                    //    (2026-08-31 실기기에서 그렇게 됐다).
                    if (this.requesting) return;
                    this.requesting = true;

                    const before = this.osPermission;
                    try {
                        // 공유를 끄지 «않는다». 취득만 다시 시작하면서 이번에는 권한을
                        // 요청한다 — 이 플러그인은 addWatcher 가 유일한 프롬프트 통로다.
                        await this.sharer.restart({ requestPermissions: true });
                        await this.syncPermission();
                    } finally {
                        this.requesting = false;
                    }

                    // 🔴 **눌렀는데 그대로면 iOS 가 프롬프트를 «안 띄운» 것이다.**
                    //    「사용 중 → 항상」 승격 프롬프트는 앱 설치당 사실상 한 번뿐이고,
                    //    소진된 뒤에는 요청해도 아무 일도 일어나지 않는다(오류도 없다).
                    //    화면이 그 사실을 말하지 않으면 사용자는 계속 누른다.
                    if (this.osPermission === before) {
                        this.alwaysPromptUnavailable = true;
                    }
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

                    // 🔑 서버가 «막았다»는 사실만 받아온다. 무엇에 동의해야 하는지는
                    //    서버가 준 목록을 그대로 쓴다 — 화면에서 다시 만들면 어긋난다.
                    // 공유를 켜고 끌 때 배터리 경고 조건이 바뀐다(끄면 안 띄운다).
                    if (this.batteryWarn !== false || s.sharing) this.syncBattery();

                    this.consentItems = s.consentRequired ?? [];
                    if (this.consentItems.length === 0) {
                        this.consentChecked = [];
                        this.consentError = null;
                    }
                },

                // 동의를 남기고 «원래 하려던 일»을 이어서 한다.
                async agreeConsent() {
                    if (this.consentSubmitting) return;

                    if (this.consentChecked.length !== this.consentItems.length) {
                        this.consentError = '필수 약관에 모두 동의해야 합니다.';

                        return;
                    }

                    this.consentSubmitting = true;
                    this.consentError = null;
                    try {
                        await window.axios.post('/api/consents',
                            { consents: this.consentChecked },
                            { headers: { Accept: 'application/json' } });

                        // 🔑 동의만 받고 끝내지 않는다. 사용자가 누른 것은 「공유 켜기」였다.
                        this.consentItems = [];
                        await this.sharer.enable();
                    } catch (e) {
                        this.consentError = '동의를 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.';
                    } finally {
                        this.consentSubmitting = false;
                    }
                },
                toggle() {
                    this.sharer.toggle();
                },
            },
            mounted() {
                this.sharer = createLocationSharer({
                    projectId: this.projectId,
                    onChange: (s) => this.applyState(s),
                    // 🔑 tracker 를 «넘기지 않는다». createLocationSharer 가 앱이면
                    //    네이티브 트래커를, 아니면 웹 경로를 알아서 고른다.
                    //    호출부마다 배선하면 한 곳을 빠뜨리고, 그 화면만 화면을 끄는
                    //    순간 위치가 끊긴다 — 실제로 그렇게 됐다(2026-09-01).
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

                // 배터리 최적화 (M-26).
                //
                // 🔑 **복귀할 때마다 다시 읽는다.** 사용자가 설정에서 고치고 돌아오는
                //    흐름이라, 한 번만 읽으면 «고쳤는데도 경고가 남는» 화면이 된다.
                //    권한 3단계에서 같은 실수를 했다(02 §4).
                this.syncBattery();
                this._onVisible = () => {
                    if (!document.hidden) this.syncBattery();
                };
                document.addEventListener('visibilitychange', this._onVisible);
            },
            beforeUnmount() {
                if (this.sharer) this.sharer.destroy();
                this.stopWatchingPermission?.();
                if (this._onVisible) document.removeEventListener('visibilitychange', this._onVisible);
            },
        }).mount('#eventActiveApp');
    </script>
</x-layouts.app>
