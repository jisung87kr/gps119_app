{{--
    구급대원 홈 — 행사 횡단 출동 이력 (현장 피드백 #4·#6).

    「출동 요청 0건 완료 0건」이라는 지적이 여기서 해소된다. 예전에는 구조대 계정도
    일반 대시보드로 떨어져서 «내가 신고한» 건수 0건을 봤다 — 자기 일과 아무 상관 없는
    숫자 넉 장이었다.

    실시간은 이 화면의 몫이 아니다. 지령을 «받는» 화면은 행사 스코프의
    /events/{id}/dispatch 이고, 여기는 그 진입점 + 이력이다.
--}}
<x-layouts.app title="GPS119 - 지령" tab="dispatch">
    <div class="space-y-6">

        <div>
            <p class="text-sm font-semibold text-ink-500">{{ now()->format('Y년 n월 j일') }}</p>
            <h1 class="mt-0.5 text-[26px] font-extrabold leading-snug tracking-tight text-ink-950">
                {{ Auth::user()->name }}님,<br class="sm:hidden" /> 출동 현황입니다
            </h1>
        </div>

        {{-- 새 지령이 있으면 화면에서 가장 강한 것이 된다. 0이면 아예 안 보인다 —
             「0건」을 강조하는 배너는 매일 보면 아무도 안 읽게 된다. --}}
        @if ($stats['assigned'] > 0)
            <x-ui.alert tone="danger">
                <b>수락 대기 중인 지령 {{ $stats['assigned'] }}건</b>이 있습니다. 아래 행사에서 확인해 주세요.
            </x-ui.alert>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <x-ui.stat label="새 지령" :value="$stats['assigned']" />
            <x-ui.stat label="진행 중" :value="$stats['in_progress']" />
            <x-ui.stat label="오늘 완료" :value="$stats['completed_today']" />
            <x-ui.stat label="누적 완료" :value="$stats['completed_total']" />
        </div>

        {{-- 실시간 지령 화면 진입점. 이게 없으면 구급대원은 출동 지령을 받을 방법이 없다. --}}
        @if ($myEvents->isNotEmpty())
            <x-ui.section title="내 행사" :meta="$myEvents->count().'개'">
                <x-ui.list>
                    @foreach ($myEvents as $participant)
                        <x-ui.list-item :icon="$participant->role->icon()" icon-tone="brand"
                                        :title="$participant->project->name"
                                        :meta="$participant->role->label()">
                            <x-slot:trailing>
                                {{-- 🔑 역할별로 버튼이 갈린다. 이 목록에는 «참가 중인 행사 전부»가
                                     오는데(그래야 참가자로 있는 행사에 갈 길이 생긴다), 라벨을
                                     「지령·출동」으로 고정하면 참가자에게 거짓말이 된다 —
                                     눌러도 활동 화면으로 튕기므로 «동작은» 하지만 그게 더 나쁘다. --}}
                                @if ($participant->role->canReceiveDispatch())
                                    <x-ui.button :href="route('events.dispatch', $participant->project_id)" size="sm">
                                        지령·출동
                                    </x-ui.button>
                                @else
                                    <x-ui.button :href="route('events.active', $participant->project_id)"
                                                 variant="secondary" size="sm">
                                        활동 화면
                                    </x-ui.button>
                                @endif
                            </x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.list>
            </x-ui.section>
        @else
            <x-ui.card>
                <x-ui.empty icon="ambulance">
                    참가 중인 행사가 없습니다.<br>행사가 시작되면 지령이 여기에 표시됩니다.
                </x-ui.empty>
                <x-ui.button :href="route('events.join')" variant="secondary">행사 참가하기</x-ui.button>
            </x-ui.card>
        @endif

        <x-ui.section title="출동 이력" :meta="$dispatches->count().'건'">
            @if ($dispatches->isEmpty())
                <x-ui.card>
                    <p class="text-base text-ink-500">아직 배정된 지령이 없습니다.</p>
                </x-ui.card>
            @else
                <x-ui.list>
                    @foreach ($dispatches as $dispatch)
                        <x-ui.list-item :icon="$dispatch->status->badgeIcon()" icon-tone="neutral"
                                        :title="$dispatch->request?->address ?: '주소 미상'"
                                        :meta="($dispatch->project?->name ?? '행사 미상').' · '.$dispatch->assigned_at?->format('n/j H:i')">
                            <x-slot:trailing>
                                <x-ui.badge :tone="$dispatch->status->badgeTone()" :icon="$dispatch->status->badgeIcon()">
                                    {{ $dispatch->status->label() }}
                                </x-ui.badge>
                            </x-slot:trailing>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.list>
            @endif
        </x-ui.section>
    </div>
</x-layouts.app>
