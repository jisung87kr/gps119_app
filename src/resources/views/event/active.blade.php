<x-layouts.app>
    <!-- Vue 3 (페이지별 마운트 — 기존 request/create·event/join 패턴 계승) -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    <div class="flex-1 flex items-start justify-center bg-slate-50 px-4 py-10">
        <div id="eventActiveApp"
             class="w-full max-w-md"
             data-project-id="{{ $project->id }}"
             data-role="{{ $role }}"
             data-role-label="{{ $roleLabel }}"
             data-project-name="{{ $project->name }}">

            <!-- 헤더 -->
            <div class="text-center mb-6">
                <h1 class="text-xl font-black tracking-tight text-slate-900">@{{ projectName }}</h1>
                <div class="mt-2 inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 border border-slate-200">
                    <span class="text-xs text-slate-400 font-medium">내 역할</span>
                    <span class="text-sm font-bold text-slate-800">@{{ roleLabel }}</span>
                </div>
            </div>

            <!-- 위치 공유 카드 -->
            <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-200/60 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">실시간 위치 공유</p>
                        <p class="mt-0.5 text-xs text-slate-400">상황실이 내 위치를 지도에서 확인합니다.</p>
                    </div>
                    <!-- 토글 -->
                    <button @click="toggle" type="button" role="switch" :aria-checked="sharing"
                            :class="sharing ? 'bg-blue-600' : 'bg-slate-300'"
                            class="relative inline-flex h-7 w-12 flex-shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <span :class="sharing ? 'translate-x-5' : 'translate-x-0.5'"
                              class="inline-block h-6 w-6 transform rounded-full bg-white shadow translate-y-0.5 transition-transform"></span>
                    </button>
                </div>

                <!-- 상태 -->
                <div class="mt-5 rounded-xl p-4"
                     :class="sharing ? 'bg-blue-50 border border-blue-100' : 'bg-slate-50 border border-slate-100'">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full"
                              :class="sharing ? 'bg-blue-500 animate-pulse' : 'bg-slate-300'"></span>
                        <span class="text-sm font-semibold"
                              :class="sharing ? 'text-blue-700' : 'text-slate-500'">
                            @{{ sharing ? '위치 공유 중' : '공유 중지됨' }}
                        </span>
                    </div>
                    <dl v-if="sharing" class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-500">
                        <div><dt class="text-slate-400">전송 횟수</dt><dd class="font-semibold text-slate-700">@{{ sentCount }}</dd></div>
                        <div><dt class="text-slate-400">마지막 전송</dt><dd class="font-semibold text-slate-700">@{{ lastSentLabel }}</dd></div>
                        <div><dt class="text-slate-400">정확도</dt><dd class="font-semibold text-slate-700">@{{ accuracyLabel }}</dd></div>
                        <div v-if="bufferedCount"><dt class="text-slate-400">대기 중</dt><dd class="font-semibold text-amber-600">@{{ bufferedCount }}건</dd></div>
                    </dl>
                </div>

                <!-- 안내/에러 -->
                <p v-if="error" class="mt-3 text-sm text-red-600">@{{ error }}</p>
                <p v-else-if="permission === 'prompt'" class="mt-3 text-xs text-slate-400">
                    위치 권한을 허용하면 공유가 시작됩니다.
                </p>
            </div>

            <!-- 다음 단계 -->
            <div class="mt-5 space-y-2">
                <a href="{{ route('request.create') }}"
                   class="w-full inline-flex justify-center items-center py-3 px-4 text-sm font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 transition">
                    구조요청 화면으로
                </a>
                <a href="{{ route('dashboard') }}"
                   class="w-full inline-flex justify-center items-center py-2.5 px-4 text-sm font-medium rounded-xl text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition">
                    대시보드로
                </a>
            </div>
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
