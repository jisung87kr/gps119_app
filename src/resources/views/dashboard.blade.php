{{-- 홈(대시보드) — src/tmp/dashboard.html 기준. --}}
<x-layouts.app title="GPS119 - 홈" tab="home">
    <div class="space-y-6">

        {{-- 인사 --}}
        <div>
            <p class="text-sm font-semibold text-ink-500">{{ now()->format('Y년 n월 j일') }}</p>
            <h1 class="mt-0.5 text-[26px] font-extrabold leading-snug tracking-tight text-ink-950">
                안녕하세요,<br class="sm:hidden" /> {{ Auth::user()->name }}님
            </h1>
        </div>

        {{-- 긴급 구조 요청 — 화면에서 가장 강한 액션이므로 유일하게 danger 를 채운다 --}}
        <x-ui.button :href="route('request.create')" variant="danger" size="xl">
            <x-ui.icon name="bolt" class="h-6 w-6" />
            긴급 구조 요청
        </x-ui.button>

        {{-- 요청 현황 --}}
        <div class="grid grid-cols-2 gap-3">
            <x-ui.stat label="전체 요청" :value="$stats['total']" />
            <x-ui.stat label="접수 대기" :value="$stats['pending']" />
            <x-ui.stat label="진행중" :value="$stats['in_progress']" />
            <x-ui.stat label="완료" :value="$stats['completed']" />
        </div>

        {{-- 참가 중인 행사 — 운영 인력이 자기 행사(활동/지령) 화면으로 가는 통로 --}}
        @if ($myEvents->isNotEmpty())
            <x-ui.section title="내 행사" :meta="$myEvents->count().'개'">
                <x-ui.list>
                    @foreach ($myEvents as $participant)
                        <x-ui.list-item :icon="$participant->role->icon()" icon-tone="neutral"
                                        :title="$participant->project->name"
                                        :meta="$participant->role->label()">
                            <x-slot:trailing>
                                @if ($participant->role->canReceiveDispatch())
                                    <x-ui.button :href="route('events.dispatch', $participant->project_id)" size="sm">
                                        지령·출동
                                    </x-ui.button>
                                    <x-ui.button :href="route('events.active', $participant->project_id)"
                                                 variant="secondary" size="sm">
                                        활동
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
        @endif

        {{-- 처리 중인 요청 (대기·진행중) — 내역보다 위에 강조 --}}
        @if ($activeRequests->isNotEmpty())
            <x-ui.section title="처리 중인 요청" :meta="$activeRequests->count().'건'">
                <x-ui.list>
                    @foreach ($activeRequests as $request)
                        <x-ui.list-item :href="route('request.show', $request)" icon="pin">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <x-ui.badge :tone="$request->status->badgeTone()"
                                            :icon="$request->status->badgeIcon()" size="sm">
                                    {{ $request->status->label() }}
                                </x-ui.badge>

                                {{-- 우선순위는 눈에 띄어야 할 때(높음·긴급)만 노출해 배지 소음을 줄인다 --}}
                                @if ($request->priority->badgeIcon())
                                    <x-ui.badge :tone="$request->priority->badgeTone()"
                                                :icon="$request->priority->badgeIcon()"
                                                :filled="$request->priority->badgeFilled()" size="sm">
                                        {{ $request->priority->label() }}
                                    </x-ui.badge>
                                @endif
                            </div>

                            <p class="mt-1.5 truncate text-base font-bold text-ink-950">
                                {{ $request->address ?? '위치 확인 중' }}
                            </p>
                            <p class="mt-0.5 truncate text-sm text-ink-400">
                                {{-- app locale 은 en 이므로 상대시간만 한국어로 지정 --}}
                                {{ $request->requested_at?->locale('ko')->diffForHumans() }}
                                @if ($request->project)
                                    · {{ $request->project->name }}
                                @endif
                                @if ($request->assignedRescuer)
                                    · 담당 {{ $request->assignedRescuer->name }}
                                @endif
                            </p>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.list>
            </x-ui.section>
        @endif

        {{-- 내 요청 내역 --}}
        <x-ui.section title="내 요청 내역">
            @if ($recentRequests->isNotEmpty())
                <x-ui.list>
                    @foreach ($recentRequests as $request)
                        <x-ui.list-item :href="route('request.show', $request)" icon="pin">
                            <x-ui.badge :tone="$request->status->badgeTone()"
                                        :icon="$request->status->badgeIcon()" size="sm">
                                {{ $request->status->label() }}
                            </x-ui.badge>
                            <p class="mt-1.5 truncate text-base font-bold text-ink-950">
                                {{ $request->address ?? '위치 확인 중' }}
                            </p>
                            <p class="mt-0.5 truncate text-sm text-ink-400">
                                {{ $request->requested_at?->format('Y.m.d H:i') }}
                                @if ($request->project)
                                    · {{ $request->project->name }}
                                @endif
                            </p>
                        </x-ui.list-item>
                    @endforeach
                </x-ui.list>
            @else
                <x-ui.card class="text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                        <x-ui.icon name="pin" class="h-6 w-6" />
                    </span>
                    <p class="mt-3 text-base font-bold text-ink-950">아직 구조 요청이 없습니다</p>
                    <p class="mt-1 text-sm leading-relaxed text-ink-500">
                        긴급 상황에서 GPS 위치를 보내 신속하게 구조를 요청할 수 있습니다.
                    </p>
                    <x-ui.button :href="route('request.create')" class="mt-5">첫 구조 요청하기</x-ui.button>
                </x-ui.card>
            @endif
        </x-ui.section>

    </div>
</x-layouts.app>
