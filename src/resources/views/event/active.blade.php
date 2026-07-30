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
         data-project-name="{{ $project->name }}">

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

            {{-- 상태 --}}
            <div class="mt-5 rounded-2xl p-4" :class="sharing ? 'bg-brand-50' : 'bg-ink-50'">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full"
                          :class="sharing ? 'animate-pulse bg-brand-600' : 'bg-ink-300'"></span>
                    <span class="text-sm font-bold" :class="sharing ? 'text-brand-600' : 'text-ink-500'">
                        @{{ sharing ? '위치 공유 중' : '공유 중지됨' }}
                    </span>
                </div>

                <dl v-if="sharing" class="mt-3 grid grid-cols-2 gap-3">
                    <div>
                        <dt class="text-xs font-bold text-ink-400">전송 횟수</dt>
                        <dd class="mt-0.5 text-sm font-bold text-ink-950">@{{ sentCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-ink-400">마지막 전송</dt>
                        <dd class="mt-0.5 text-sm font-bold text-ink-950">@{{ lastSentLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold text-ink-400">정확도</dt>
                        <dd class="mt-0.5 text-sm font-bold text-ink-950">@{{ accuracyLabel }}</dd>
                    </div>
                    <div v-if="bufferedCount">
                        <dt class="text-xs font-bold text-ink-400">대기 중</dt>
                        <dd class="mt-0.5 text-sm font-bold text-warning-600">@{{ bufferedCount }}건</dd>
                    </div>
                </dl>
            </div>

            {{-- 안내/에러 --}}
            <p v-if="error" class="mt-3 text-sm font-bold text-danger-600">@{{ error }}</p>
            <p v-else-if="permission === 'prompt'" class="mt-3 text-sm text-ink-400">
                위치 권한을 허용하면 공유가 시작됩니다.
            </p>
        </x-ui.card>

        {{-- 다음 단계 --}}
        <div class="space-y-3">
            @if ($canDispatch)
                {{-- 구급대/자원봉사 구급: 지령·출동 화면이 주 화면 --}}
                <x-ui.button :href="route('events.dispatch', $project->id)">
                    <x-ui.icon name="ambulance" class="h-5 w-5" />
                    지령·출동 화면
                </x-ui.button>
                <x-ui.button :href="route('request.create')" variant="secondary">구조요청 화면으로</x-ui.button>
            @else
                <x-ui.button :href="route('request.create')">구조요청 화면으로</x-ui.button>
            @endif
            <x-ui.button :href="route('dashboard')" variant="ghost">홈으로</x-ui.button>
        </div>
    </div>

    <script type="module">
        import { createLocationSharer } from '/js/components/locationShare.js';

        const { createApp } = Vue;

        const root = document.getElementById('eventActiveApp');

        createApp({
            data() {
                return {
                    projectId: Number(root.dataset.projectId),
                    role: root.dataset.role,
                    roleLabel: root.dataset.roleLabel,
                    projectName: root.dataset.projectName,
                    // sharer state 미러
                    sharing: false,
                    permission: 'prompt',
                    sentCount: 0,
                    lastSentAt: null,
                    lastAccuracy: null,
                    bufferedCount: 0,
                    error: null,
                };
            },
            computed: {
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
                });
                // 브라우저 QA용 전역 노출(셀렉터/제어 가능)
                window.__locationShare = this.sharer;
                // 입장 후 자동 시작(권한 프롬프트 → watchPosition)
                this.sharer.enable();
            },
            beforeUnmount() {
                if (this.sharer) this.sharer.destroy();
            },
        }).mount('#eventActiveApp');
    </script>
</x-layouts.app>
