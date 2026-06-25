<x-layouts.app>
    <!-- Vue 3 (페이지별 마운트 — 기존 request/create 패턴 계승, 관제 SPA 아님) -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    {{-- 서버에서 전달된 프리필 코드(QR 딥링크 진입 시) --}}
    @php($prefillCode = $prefillCode ?? '')

    <div class="flex-1 flex items-center justify-center bg-slate-50 px-4 py-10">
        <div id="eventJoinApp" class="w-full max-w-md" data-prefill="{{ $prefillCode }}">
            <!-- 헤더 -->
            <div class="text-center mb-8">
                <div class="mx-auto h-16 w-16 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-200 mb-5">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">행사 입장</h1>
                <p class="mt-2 text-sm text-slate-500">행사 코드를 입력하거나 QR로 입장하세요.</p>
            </div>

            <!-- 카드 -->
            <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-200/60 p-6">
                <!-- 1단계: 코드 입력 -->
                <div v-if="step === 'input'">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">행사 코드</label>
                    <input v-model="code"
                           @input="code = code.toUpperCase()"
                           @keyup.enter="lookup"
                           type="text"
                           maxlength="6"
                           placeholder="예: AB3K9P"
                           autocomplete="off"
                           autocapitalize="characters"
                           class="w-full text-center text-2xl font-black tracking-[0.3em] uppercase px-4 py-4 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">

                    <p v-if="error" class="mt-3 text-sm text-red-600 text-center">@{{ error }}</p>

                    <button @click="lookup" :disabled="loading || code.length < 6"
                            class="mt-5 w-full flex justify-center items-center py-3.5 px-4 text-base font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg v-if="loading" class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>@{{ loading ? '확인 중...' : '행사 확인' }}</span>
                    </button>
                </div>

                <!-- 2단계: 미리보기 + 입장 -->
                <div v-else-if="step === 'preview'">
                    <div class="text-center mb-5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                              :class="preview.is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200'">
                            <span class="w-1.5 h-1.5 rounded-full mr-1.5" :class="preview.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                            @{{ preview.is_active ? '진행 중' : '종료/비활성' }}
                        </span>
                        <h2 class="mt-3 text-xl font-bold text-slate-900">@{{ preview.name }}</h2>
                        <p v-if="preview.start_date" class="mt-1 text-sm text-slate-500">
                            @{{ preview.start_date }} ~ @{{ preview.end_date }}
                        </p>
                    </div>

                    <p v-if="error" class="mb-3 text-sm text-red-600 text-center">@{{ error }}</p>

                    <button v-if="preview.is_active" @click="join" :disabled="loading"
                            class="w-full flex justify-center items-center py-3.5 px-4 text-base font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition disabled:opacity-50">
                        <svg v-if="loading" class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>@{{ loading ? '입장 중...' : '이 행사에 입장' }}</span>
                    </button>
                    <div v-else class="text-center text-sm text-slate-500 bg-slate-50 rounded-xl p-4">
                        현재 입장할 수 없는 행사입니다. 일반 구조요청을 이용해 주세요.
                    </div>

                    <button @click="reset" class="mt-3 w-full py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 transition">
                        다른 코드 입력
                    </button>
                </div>

                <!-- 3단계: 입장 완료(역할 표시) -->
                <div v-else-if="step === 'joined'" class="text-center">
                    <div class="mx-auto h-16 w-16 bg-emerald-100 rounded-full flex items-center justify-center mb-5">
                        <svg class="h-9 w-9 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900">입장 완료</h2>
                    <p class="mt-1 text-sm text-slate-500">@{{ joined.project_name }}</p>

                    <div class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 border border-slate-200">
                        <span class="text-xs text-slate-400 font-medium">내 역할</span>
                        <span class="text-sm font-bold text-slate-800">@{{ roleLabel(joined.role) }}</span>
                    </div>

                    <div v-if="joined.status === 'pending'" class="mt-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-3">
                        상황실 승인 대기 중입니다. 승인되면 활동할 수 있습니다.
                    </div>

                    <div class="mt-6 space-y-3">
                        <a href="{{ route('request.create') }}"
                           class="w-full inline-flex justify-center items-center py-3.5 px-4 text-base font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 transition">
                            구조요청 화면으로
                        </a>
                        <a href="{{ route('dashboard') }}"
                           class="w-full inline-flex justify-center items-center py-3 px-4 text-sm font-medium rounded-xl text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition">
                            대시보드로
                        </a>
                    </div>
                </div>
            </div>

            <!-- 안내 -->
            <p class="mt-6 text-center text-xs text-slate-400">
                행사 코드는 주최측이 현장에 게시한 QR 또는 안내문에서 확인할 수 있습니다.
            </p>
        </div>
    </div>

    <script>
        const { createApp } = Vue;

        createApp({
            data() {
                return {
                    step: 'input',          // input | preview | joined
                    code: '',
                    preview: {},
                    joined: {},
                    loading: false,
                    error: '',
                };
            },
            mounted() {
                // QR 딥링크 진입 시 서버가 넣어준 코드 프리필 → 자동 미리보기
                const prefill = (this.$el.dataset.prefill || '').toUpperCase();
                if (prefill) {
                    this.code = prefill;
                    this.lookup();
                }
            },
            methods: {
                // 역할 코드 → 한글 라벨 (EventRole 과 동기화. 백엔드가 단일 출처지만 표시용 매핑)
                roleLabel(role) {
                    const map = {
                        participant: '참가자',
                        staff: '운영진',
                        police: '경찰',
                        volunteer_course: '자원봉사자(코스)',
                        volunteer_medic: '자원봉사자(구급)',
                        paramedic: '구급대',
                        controller: '상황실',
                    };
                    return map[role] || role;
                },

                async lookup() {
                    if (this.code.length < 6 || this.loading) return;
                    this.loading = true;
                    this.error = '';
                    try {
                        const res = await window.axios.get(`/api/events/${this.code}`, {
                            headers: { Accept: 'application/json' },
                        });
                        this.preview = res.data.data;
                        this.step = 'preview';
                    } catch (e) {
                        const status = e.response?.status;
                        if (status === 404) {
                            this.error = '존재하지 않는 행사 코드입니다.';
                        } else {
                            this.error = e.response?.data?.message || '행사 정보를 불러오지 못했습니다.';
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                async join() {
                    if (this.loading) return;
                    this.loading = true;
                    this.error = '';
                    try {
                        const res = await window.axios.post(`/api/events/${this.code}/join`, {}, {
                            headers: { Accept: 'application/json' },
                        });
                        const d = res.data.data;
                        this.joined = {
                            role: d.participant.role,
                            status: d.participant.status,
                            project_name: d.project.name,
                        };
                        this.step = 'joined';
                    } catch (e) {
                        const status = e.response?.status;
                        const msg = e.response?.data?.message || '';
                        if (status === 422 && msg.includes('전화번호')) {
                            // require-phone 정책 계승 — 연락처 등록 화면으로 유도
                            if (confirm('행사 참가에는 전화번호 등록이 필요합니다. 연락처 등록 화면으로 이동할까요?')) {
                                window.location.href = "{{ route('profile.edit') }}";
                                return;
                            }
                            this.error = msg;
                        } else if (status === 422) {
                            this.error = msg || '현재 입장할 수 없는 행사입니다.';
                        } else {
                            this.error = msg || '입장에 실패했습니다.';
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                reset() {
                    this.step = 'input';
                    this.code = '';
                    this.preview = {};
                    this.error = '';
                },
            },
        }).mount('#eventJoinApp');
    </script>
</x-layouts.app>
