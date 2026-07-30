<x-layouts.admin title="GPS119 통계">
@php
    // 분 → "N분" / "N시간 M분" 표기
    $fmtMin = function ($m) {
        if ($m === null) return '—';
        if ($m < 60) return $m.'분';
        $h = intdiv($m, 60); $r = $m % 60;
        return $r ? "{$h}시간 {$r}분" : "{$h}시간";
    };

    $typeMax = max($type_dist->max('count'), 1);
    $prioMax = max($priority_dist->max('count'), 1);
    $hourMax = max($hourly->max('count'), 1);
@endphp

<div class="p-5 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">

    <!-- Header + filters -->
    @php $selectedProject = $projectId ? $projectList->firstWhere('id', $projectId) : null; @endphp
    <div class="flex flex-col sm:flex-row sm:items-end gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">통계</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $periods[$period] }} · {{ $selectedProject ? $selectedProject->name : '전체 행사' }} · 신고 현황 및 대응 분석</p>
        </div>
        <div class="sm:ml-auto flex flex-wrap items-center gap-2.5">
            {{-- 행사 필터 (기간은 유지) --}}
            <form method="GET" action="{{ route('admin.statistics') }}" class="relative">
                <input type="hidden" name="period" value="{{ $period }}">
                <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <select name="project" onchange="this.form.submit()"
                        class="appearance-none pl-9 pr-9 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl shadow-sm cursor-pointer hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30 max-w-[220px] truncate">
                    <option value="">전체 행사</option>
                    @foreach($projectList as $proj)
                        <option value="{{ $proj->id }}" @selected($projectId === $proj->id)>{{ $proj->name }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </form>
            {{-- 기간 필터 (행사는 유지) --}}
            <div class="inline-flex p-1 bg-white border border-slate-200 rounded-xl shadow-sm">
                @foreach($periods as $key => $label)
                    <a href="{{ route('admin.statistics', ['period' => $key, 'project' => $projectId]) }}"
                       class="px-3.5 py-1.5 text-sm font-medium rounded-lg transition-colors {{ $period === $key ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <span class="grid place-items-center w-11 h-11 rounded-xl bg-blue-50 text-blue-600">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.2-7-11a7 7 0 0 1 14 0c0 5.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.4"/></svg>
                </span>
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight tabular-nums text-slate-900">{{ number_format($kpi['total']) }}</p>
            <p class="mt-1 text-sm text-slate-500">기간 내 총 신고</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <span class="grid place-items-center w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 tabular-nums">{{ $kpi['completed'] }}건</span>
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight tabular-nums text-slate-900">{{ $kpi['completion_rate'] }}%</p>
            <p class="mt-1 text-sm text-slate-500">완료율</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <span class="grid place-items-center w-11 h-11 rounded-xl bg-sky-50 text-sky-600">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight tabular-nums text-slate-900">{{ $fmtMin($kpi['avg_accept_min']) }}</p>
            <p class="mt-1 text-sm text-slate-500">평균 수락 시간 · 배정→수락</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <span class="grid place-items-center w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </span>
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight tabular-nums text-slate-900">{{ $fmtMin($kpi['avg_complete_min']) }}</p>
            <p class="mt-1 text-sm text-slate-500">평균 대응 시간 · 신고→완료</p>
        </div>
    </div>

    <!-- Per-project table (핵심) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center gap-2 px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-900">행사별 신고 현황</h2>
            <span class="px-2 py-0.5 text-xs font-medium text-slate-500 bg-slate-100 rounded-full tabular-nums">{{ $project_rows->count() }}개 행사</span>
            <a href="{{ route('admin.projects.index') }}" class="ml-auto text-sm font-medium text-blue-600 hover:text-blue-700">행사 관리 →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                        <th class="px-6 py-3 font-semibold">행사</th>
                        <th class="px-3 py-3 text-right font-semibold">신고</th>
                        <th class="px-3 py-3 text-right font-semibold">대기</th>
                        <th class="px-3 py-3 text-right font-semibold">진행</th>
                        <th class="px-3 py-3 text-right font-semibold">완료</th>
                        <th class="px-3 py-3 text-right font-semibold">취소</th>
                        <th class="px-3 py-3 text-right font-semibold">참가자</th>
                        <th class="px-6 py-3 font-semibold w-40">완료율</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($project_rows as $p)
                        @php
                            $rate = $p->req_total > 0 ? round($p->req_completed / $p->req_total * 100) : 0;
                            $live = $p->end_date && $p->end_date->endOfDay()->isFuture();
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-3.5">
                                <a href="{{ route('admin.projects.show', $p->id) }}" class="flex items-center gap-2.5 group">
                                    <span class="w-1.5 h-1.5 rounded-full flex-none {{ $live ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    <span class="min-w-0">
                                        <span class="block font-medium text-slate-800 group-hover:text-blue-600 truncate max-w-[220px]">{{ $p->name }}</span>
                                        <span class="block text-xs text-slate-400">~ {{ $p->end_date?->format('Y-m-d') ?? '기간 미정' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-3 py-3.5 text-right font-semibold tabular-nums text-slate-900">{{ $p->req_total }}</td>
                            <td class="px-3 py-3.5 text-right tabular-nums {{ $p->req_pending > 0 ? 'text-amber-600 font-medium' : 'text-slate-300' }}">{{ $p->req_pending }}</td>
                            <td class="px-3 py-3.5 text-right tabular-nums {{ $p->req_in_progress > 0 ? 'text-indigo-600' : 'text-slate-300' }}">{{ $p->req_in_progress }}</td>
                            <td class="px-3 py-3.5 text-right tabular-nums {{ $p->req_completed > 0 ? 'text-emerald-600' : 'text-slate-300' }}">{{ $p->req_completed }}</td>
                            <td class="px-3 py-3.5 text-right tabular-nums text-slate-400">{{ $p->req_cancelled }}</td>
                            <td class="px-3 py-3.5 text-right tabular-nums text-slate-500">{{ $p->active_participants }}</td>
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $rate }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium tabular-nums text-slate-500 w-9 text-right">{{ $rate }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-14 text-center text-sm text-slate-400">해당 기간에 데이터가 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Type + Priority distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- 신고 유형 -->
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6">
            <h2 class="text-base font-semibold text-slate-900">신고 유형 분포</h2>
            <div class="mt-5 space-y-4">
                @foreach($type_dist as $t)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1.5">
                            <span class="text-slate-600">{{ $t['label'] }}</span>
                            <span class="tabular-nums text-slate-400"><b class="text-slate-800 font-semibold">{{ $t['count'] }}</b> · {{ $kpi['total'] > 0 ? round($t['count'] / $kpi['total'] * 100) : 0 }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: {{ round($t['count'] / $typeMax * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- 우선순위 -->
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6">
            <h2 class="text-base font-semibold text-slate-900">우선순위 분포</h2>
            <div class="mt-5 space-y-4">
                @foreach($priority_dist as $p)
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $p['badge'] }}">{{ $p['label'] }}</span>
                            <span class="tabular-nums text-slate-400"><b class="text-slate-800 font-semibold">{{ $p['count'] }}</b> · {{ $kpi['total'] > 0 ? round($p['count'] / $kpi['total'] * 100) : 0 }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-orange-400 rounded-full" style="width: {{ round($p['count'] / $prioMax * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- 시간대별 신고 -->
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6">
        <div class="flex items-end justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-900">시간대별 신고 분포</h2>
                <p class="mt-0.5 text-sm text-slate-400">0시 ~ 23시 · 최다 {{ $hourMax }}건</p>
            </div>
        </div>
        <div class="mt-6 flex items-end gap-[3px] sm:gap-1.5 h-40">
            @foreach($hourly as $h)
                @php $pct = round($h['count'] / $hourMax * 100); @endphp
                <div class="flex-1 h-full flex flex-col justify-end items-center group relative">
                    <div class="w-full rounded-t {{ $h['count'] > 0 ? 'bg-blue-500 group-hover:bg-blue-600' : 'bg-slate-100' }} transition-colors"
                         style="height: {{ max($pct, 2) }}%"></div>
                    <div class="absolute -top-7 hidden group-hover:block px-1.5 py-0.5 text-[11px] font-medium text-white bg-slate-800 rounded whitespace-nowrap tabular-nums z-10">
                        {{ $h['hour'] }}시 · {{ $h['count'] }}건
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-[10px] text-slate-400 tabular-nums">
            <span>0시</span><span>6시</span><span>12시</span><span>18시</span><span>23시</span>
        </div>
    </div>

    <!-- Dispatch + Top responders -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
        <!-- 지령 상태 분포 -->
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold text-slate-900">지령(출동) 상태</h2>
                <span class="px-2 py-0.5 text-xs font-medium text-slate-500 bg-slate-100 rounded-full tabular-nums">{{ $dispatch_total }}건</span>
            </div>
            @if($dispatch_total > 0)
                <div class="mt-4 flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                    @foreach(\App\Enums\DispatchStatus::cases() as $ds)
                        @php $c = (int) ($dispatch_dist[$ds->value] ?? 0); @endphp
                        @if($c > 0)
                            <div class="{{ $ds->dotClass() }}" style="width: {{ round($c / $dispatch_total * 100) }}%"></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-5 grid grid-cols-2 gap-x-6 gap-y-3">
                    @foreach(\App\Enums\DispatchStatus::cases() as $ds)
                        @php $c = (int) ($dispatch_dist[$ds->value] ?? 0); @endphp
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-sm {{ $ds->dotClass() }}"></span>{{ $ds->label() }}</span>
                            <span class="tabular-nums font-semibold text-slate-800">{{ $c }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-8 mb-4 text-center text-sm text-slate-400">해당 기간에 지령이 없습니다.</p>
            @endif
        </div>

        <!-- 응급대원 처리 순위 -->
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-semibold text-slate-900">응급대원 처리 순위</h2>
                <p class="mt-0.5 text-sm text-slate-400">배정된 지령 기준 상위 8명</p>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse($top_responders as $i => $r)
                    <li class="flex items-center gap-3 px-6 py-3">
                        <span class="grid place-items-center w-6 h-6 rounded-full text-xs font-bold flex-none {{ $i < 3 ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500' }} tabular-nums">{{ $i + 1 }}</span>
                        <span class="flex-1 min-w-0 text-sm font-medium text-slate-800 truncate">{{ $r->paramedic?->name ?? '알 수 없음' }}</span>
                        <span class="text-xs text-emerald-600 tabular-nums">완료 {{ (int) $r->completed }}</span>
                        <span class="text-sm font-bold tabular-nums text-slate-900 w-8 text-right">{{ (int) $r->total }}</span>
                    </li>
                @empty
                    <li class="px-6 py-14 text-center text-sm text-slate-400">해당 기간에 지령 배정 이력이 없습니다.</li>
                @endforelse
            </ul>
        </div>
    </div>

</div>
</x-layouts.admin>
