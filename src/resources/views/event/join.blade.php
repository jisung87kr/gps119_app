{{--
    행사 입장 (코드 6자 → 미리보기 → 입장). 시안이 없는 화면이라
    design-system.html 어휘로 파생했다. 스크립트 동작은 기존과 동일.
--}}
<x-layouts.app title="GPS119 - 행사 입장" heading="행사 입장" :back="route('dashboard')">
    <!-- Vue 3 (페이지별 마운트 — 기존 request/create 패턴 계승, 관제 SPA 아님) -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>

    {{-- 서버에서 전달된 프리필 코드(QR 딥링크 진입 시) --}}
    @php($prefillCode = $prefillCode ?? '')

    <div id="eventJoinApp" class="space-y-6" data-prefill="{{ $prefillCode }}">

        {{-- 1단계: 코드 입력 --}}
        <div v-if="step === 'input'" class="space-y-6">
            <div>
                <h1 class="text-[26px] font-extrabold leading-snug tracking-tight text-ink-950">행사 코드 입력</h1>
                <p class="mt-2 text-base leading-relaxed text-ink-500">
                    주최측이 현장에 게시한 QR 또는 안내문에서 6자리 코드를 확인할 수 있습니다.
                </p>
            </div>

            <x-ui.card>
                <label for="joinCode" class="mb-1.5 block text-base font-bold text-ink-900">행사 코드</label>
                <input id="joinCode" v-model="code"
                       v-on:input="code = code.toUpperCase()"
                       v-on:keyup.enter="lookup"
                       type="text" maxlength="6" placeholder="AB3K9P"
                       autocomplete="off" autocapitalize="characters"
                       class="w-full rounded-2xl border-2 border-ink-200 px-4 py-4 text-center text-2xl font-extrabold uppercase tracking-[0.3em] text-ink-950 placeholder:tracking-[0.3em] placeholder:text-ink-300 focus:border-brand-600 focus:outline-none">

                <p v-if="error" class="mt-3 text-center text-sm font-bold text-danger-600">@{{ error }}</p>

                <button type="button" v-on:click="lookup" :disabled="loading || code.length < 6"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-brand-600 py-4 text-base font-bold text-white shadow-sm transition-colors active:bg-brand-700 disabled:cursor-not-allowed disabled:bg-ink-100 disabled:text-ink-400 disabled:shadow-none">
                    <svg v-if="loading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M12 3a9 9 0 1 0 9 9" />
                    </svg>
                    <span>@{{ loading ? '확인 중…' : '행사 확인' }}</span>
                </button>
            </x-ui.card>
        </div>

        {{-- 2단계: 미리보기 + 입장 --}}
        <div v-else-if="step === 'preview'" class="space-y-6">
            <x-ui.card>
                <div class="text-center">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 px-3 py-1.5 text-sm font-bold"
                          :class="preview.is_active ? 'text-success-600' : 'text-ink-400'">
                        <span class="h-1.5 w-1.5 rounded-full"
                              :class="preview.is_active ? 'bg-success-500' : 'bg-ink-300'"></span>
                        @{{ preview.is_active ? '진행 중' : '종료/비활성' }}
                    </span>
                    <h1 class="mt-3 text-xl font-extrabold text-ink-950">@{{ preview.name }}</h1>
                    <p v-if="preview.start_date" class="mt-1 text-sm text-ink-400">
                        @{{ preview.start_date }} ~ @{{ preview.end_date }}
                    </p>
                </div>

                <p v-if="error" class="mt-4 text-center text-sm font-bold text-danger-600">@{{ error }}</p>

                <button v-if="preview.is_active" type="button" v-on:click="join" :disabled="loading"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-brand-600 py-4 text-base font-bold text-white shadow-sm transition-colors active:bg-brand-700 disabled:cursor-not-allowed disabled:bg-ink-100 disabled:text-ink-400 disabled:shadow-none">
                    <svg v-if="loading" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M12 3a9 9 0 1 0 9 9" />
                    </svg>
                    <span>@{{ loading ? '입장 중…' : '이 행사에 입장' }}</span>
                </button>
                <div v-else class="mt-5 rounded-2xl bg-ink-50 p-4 text-center text-sm leading-relaxed text-ink-500">
                    현재 입장할 수 없는 행사입니다. 일반 구조요청을 이용해 주세요.
                </div>

                <button type="button" v-on:click="reset"
                        class="mt-3 w-full py-2 text-sm font-medium text-ink-400 underline underline-offset-2">
                    다른 코드 입력
                </button>
            </x-ui.card>
        </div>

        {{-- 3단계: 입장 완료(역할 표시) — active 이면 1.5초 후 자동 이동 --}}
        <div v-else-if="step === 'joined'" class="space-y-6">
            <x-ui.card class="text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success-50 text-success-600">
                    <x-ui.icon name="check-circle" class="h-8 w-8" />
                </span>
                <h1 class="mt-4 text-xl font-extrabold text-ink-950">입장 완료</h1>
                <p class="mt-1 text-base text-ink-500">@{{ joined.project_name }}</p>

                <div class="mt-5 inline-flex items-center gap-2 rounded-xl bg-ink-50 px-4 py-3">
                    <span class="text-sm font-bold text-ink-500">내 역할</span>
                    <span class="text-sm font-bold text-ink-950">@{{ roleLabel(joined.role) }}</span>
                </div>

                <div v-if="joined.status === 'pending'"
                     class="mt-4 flex items-start gap-2.5 rounded-2xl bg-warning-50 px-4 py-3.5 text-left text-sm font-bold text-warning-600">
                    <x-ui.icon name="clock" class="mt-px h-[18px] w-[18px] shrink-0" />
                    <span>상황실 승인 대기 중입니다. 승인되면 활동할 수 있습니다.</span>
                </div>

                {{-- active 일 때 자동 이동 안내 --}}
                <div v-if="joined.status === 'active'"
                     class="mt-4 flex items-center justify-center gap-1.5 text-sm text-ink-400">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <path d="M12 3a9 9 0 1 0 9 9" />
                    </svg>
                    <span>잠시 후 위치 공유 화면으로 이동합니다…</span>
                </div>
            </x-ui.card>

            <div class="space-y-3">
                <a v-if="joined.status === 'active'" :href="`/events/${joined.project_id}/active`"
                   class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-600 py-4 text-base font-bold text-white shadow-sm active:bg-brand-700">
                    바로 이동
                </a>
                <x-ui.button :href="route('request.create')" variant="secondary">구조요청 화면으로</x-ui.button>
                <x-ui.button :href="route('dashboard')" variant="ghost">홈으로</x-ui.button>
            </div>
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
                // 템플릿이 다중 루트라 this.$el은 fragment placeholder(텍스트 노드) → 컨테이너에서 직접 읽는다
                const root = document.getElementById('eventJoinApp');
                const prefill = (root?.dataset.prefill || '').toUpperCase();
                if (!prefill) return;
                this.code = prefill;
                // window.axios는 deferred 모듈(bootstrap.js)에서 로드되어 인라인 mounted보다 늦으므로, 준비될 때까지 대기 후 자동 미리보기
                const waitAxios = () => window.axios ? this.lookup() : setTimeout(waitAxios, 50);
                waitAxios();
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
                            project_id: d.project.id,
                        };
                        this.step = 'joined';
                        // active 참가자면 1.5초 후 위치 공유 화면으로 자동 이동
                        if (d.participant.status === 'active') {
                            setTimeout(() => {
                                window.location.href = `/events/${d.project.id}/active`;
                            }, 1500);
                        }
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
