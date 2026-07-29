<x-layouts.admin title="GPS119 관제 대시보드">
@php
    $total = max($stats['total_requests'], 1);
    $pendingP = round($stats['pending_requests'] / $total * 100);
    $progressP = round($stats['in_progress_requests'] / $total * 100);
    $completedP = round($stats['completed_requests'] / $total * 100);

    // ── 영역 차트 좌표 (실데이터, 최근 14일) ──
    $vals = $daily->pluck('count')->values();
    $vmax = max($vals->max(), 1);
    $W = 760; $H = 240; $padL = 12; $padR = 12; $padT = 18; $padB = 34;
    $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
    $n = $vals->count();
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $padL + ($n > 1 ? $i / ($n - 1) * $plotW : 0);
        $y = $padT + $plotH - ($v / $vmax) * $plotH;
        $pts[] = [round($x, 1), round($y, 1)];
    }
    $lineStr = implode(' ', array_map(fn ($p) => "{$p[0]},{$p[1]}", $pts));
    $baseY = $padT + $plotH;
    $areaStr = "{$padL},{$baseY} {$lineStr} " . ($padL + $plotW) . ",{$baseY}";
    $lastPt = end($pts);
    $xTicks = [0, intdiv($n - 1, 4), intdiv($n - 1, 2), intdiv(3 * ($n - 1), 4), $n - 1];

    $stBadge = [
        'pending' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'in_progress' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
        'completed' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'cancelled' => 'bg-slate-100 text-slate-500 ring-1 ring-slate-200',
    ];
    $sevDot = fn ($pv) => in_array($pv, ['critical', 'high']) ? 'bg-red-500'
                        : ($pv === 'medium' ? 'bg-amber-500' : 'bg-slate-300');
@endphp

<div class="p-5 sm:p-6 lg:p-8 max-w-[1400px] mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-end gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">관제 대시보드</h1>
            <p class="mt-1 text-sm text-slate-500">{{ now()->format('Y년 n월 j일') }} · 운영 현황 요약</p>
        </div>
        <div class="sm:ml-auto flex items-center gap-2">
            <a href="{{ route('admin.requests') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
                전체 신고
            </a>
            <a href="{{ route('admin.projects.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 shadow-sm shadow-blue-600/20 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                새 행사
            </a>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        {{-- 전체 신고 + 주간 추세 --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <span class="grid place-items-center w-11 h-11 rounded-xl bg-blue-50 text-blue-600">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.2-7-11a7 7 0 0 1 14 0c0 5.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.4"/></svg>
                </span>
                @if($stats['week_delta_pct'] !== null)
                    @php $up = $stats['week_delta_pct'] >= 0; @endphp
                    <span class="inline-flex items-center gap-0.5 px-2 py-1 rounded-lg text-xs font-semibold {{ $up ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            @if($up)<path d="M7 17 17 7M9 7h8v8"/>@else<path d="M7 7l10 10M9 17h8V9"/>@endif
                        </svg>{{ abs($stats['week_delta_pct']) }}%
                    </span>
                @endif
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight tabular-nums text-slate-900">{{ number_format($stats['total_requests']) }}</p>
            <p class="mt-1 text-sm text-slate-500">전체 신고 · 최근 7일 {{ $stats['week_requests'] }}건</p>
        </div>

        {{-- 대기 --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <span class="grid place-items-center w-11 h-11 rounded-xl bg-amber-50 text-amber-600">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                @if($stats['pending_requests'] > 0)
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>배정 대기
                    </span>
                @endif
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight tabular-nums text-slate-900">{{ number_format($stats['pending_requests']) }}</p>
            <p class="mt-1 text-sm text-slate-500">대기 신고 · 즉시 배정 필요</p>
        </div>

        {{-- 진행중 --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <span class="grid place-items-center w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17V5a1 1 0 0 1 1-1h11l3 3v10"/><circle cx="8" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
                </span>
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight tabular-nums text-slate-900">{{ number_format($stats['in_progress_requests']) }}</p>
            <p class="mt-1 text-sm text-slate-500">진행중 · 구조대 출동</p>
        </div>

        {{-- 완료 --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-start justify-between">
                <span class="grid place-items-center w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 tabular-nums">{{ $completedP }}%</span>
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight tabular-nums text-slate-900">{{ number_format($stats['completed_requests']) }}</p>
            <p class="mt-1 text-sm text-slate-500">완료 · 활성 행사 {{ $stats['active_projects'] }}/{{ $stats['total_projects'] }}</p>
        </div>
    </div>

    <!-- Chart + distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- area chart -->
        <div class="lg:col-span-2 rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <div class="flex items-end justify-between gap-4 px-6 pt-5">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">신고 추이</h2>
                    <p class="mt-0.5 text-sm text-slate-400">최근 14일</p>
                </div>
                <div class="text-right">
                    <div class="flex items-baseline gap-1.5 justify-end">
                        <span class="text-2xl font-bold tabular-nums text-slate-900">{{ $stats['today_requests'] }}</span>
                        <span class="text-sm text-slate-400">오늘</span>
                    </div>
                    @php $tup = $stats['today_delta'] >= 0; @endphp
                    <span class="inline-flex items-center gap-0.5 mt-0.5 text-xs font-semibold {{ $tup ? 'text-emerald-600' : 'text-red-500' }}">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">@if($tup)<path d="M7 17 17 7M9 7h8v8"/>@else<path d="M7 7l10 10M9 17h8V9"/>@endif</svg>
                        어제 대비 {{ $tup ? '+' : '' }}{{ $stats['today_delta'] }}
                    </span>
                </div>
            </div>
            <div class="px-2 pb-2 pt-3">
                <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full h-auto" preserveAspectRatio="none" role="img" aria-label="최근 14일 신고 추이">
                    <defs>
                        <linearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#2563eb" stop-opacity="0.18"/>
                            <stop offset="1" stop-color="#2563eb" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    {{-- gridlines --}}
                    @for($g = 0; $g <= 3; $g++)
                        @php $gy = round($padT + $plotH * $g / 3, 1); @endphp
                        <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $W - $padR }}" y2="{{ $gy }}" stroke="#eef2f7" stroke-width="1"/>
                    @endfor
                    <polygon points="{{ $areaStr }}" fill="url(#areaFill)"/>
                    <polyline points="{{ $lineStr }}" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="{{ $lastPt[0] }}" cy="{{ $lastPt[1] }}" r="4.5" fill="#2563eb" stroke="#fff" stroke-width="2.5"/>
                    {{-- x labels --}}
                    @foreach($xTicks as $ti)
                        @php $tx = $padL + ($n > 1 ? $ti / ($n - 1) * $plotW : 0); @endphp
                        <text x="{{ round($tx, 1) }}" y="{{ $H - 10 }}" fill="#94a3b8" font-size="11" text-anchor="middle" font-family="inherit">{{ $daily[$ti]['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        </div>

        <!-- response distribution -->
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6 flex flex-col">
            <h2 class="text-base font-semibold text-slate-900">응답 현황</h2>
            {{-- stacked distribution bar --}}
            <div class="mt-4 flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="bg-amber-400" style="width: {{ $pendingP }}%"></div>
                <div class="bg-indigo-500" style="width: {{ $progressP }}%"></div>
                <div class="bg-emerald-500" style="width: {{ $completedP }}%"></div>
            </div>
            <div class="mt-5 space-y-3.5">
                <div class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-sm bg-amber-400"></span>대기</span>
                    <span class="tabular-nums"><b class="font-semibold text-slate-900">{{ $stats['pending_requests'] }}</b> <span class="text-slate-400">· {{ $pendingP }}%</span></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-sm bg-indigo-500"></span>진행중</span>
                    <span class="tabular-nums"><b class="font-semibold text-slate-900">{{ $stats['in_progress_requests'] }}</b> <span class="text-slate-400">· {{ $progressP }}%</span></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-slate-600"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span>완료</span>
                    <span class="tabular-nums"><b class="font-semibold text-slate-900">{{ $stats['completed_requests'] }}</b> <span class="text-slate-400">· {{ $completedP }}%</span></span>
                </div>
            </div>
            <div class="mt-auto pt-5 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <p class="text-xs text-slate-500">완료율</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-emerald-600">{{ $completedP }}%</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <p class="text-xs text-slate-500">전체 신고</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ $stats['total_requests'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent + side lists -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        <!-- Recent requests -->
        <div class="lg:col-span-2 rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-6 py-4 border-b border-slate-100">
                <h2 class="text-base font-semibold text-slate-900">최근 신고</h2>
                <span class="px-2 py-0.5 text-xs font-medium text-slate-500 bg-slate-100 rounded-full tabular-nums">{{ $stats['total_requests'] }}건</span>
                <a href="{{ route('admin.requests') }}" class="ml-auto text-sm font-medium text-blue-600 hover:text-blue-700">전체 보기 →</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse($recent_requests as $r)
                    @php $badge = $stBadge[$r->status->value] ?? $stBadge['cancelled']; @endphp
                    <li>
                        <a href="{{ route('admin.requests.show', $r->id) }}" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-6 py-4 hover:bg-slate-50 transition-colors">
                            <span class="w-1.5 h-8 rounded-full flex-none {{ $sevDot($r->priority?->value) }}"></span>
                            <div class="flex-1 min-w-0 basis-48">
                                <p class="text-sm font-medium text-slate-800 truncate">{{ $r->address ?? '위치 정보 없음' }}</p>
                                <p class="text-xs text-slate-400 truncate">#{{ $r->id }} · {{ $r->project?->name ?? '일반 신고' }} · {{ $r->user->name ?? '알 수 없음' }}</p>
                            </div>
                            @if($r->type)
                                <span class="px-2 py-0.5 text-xs font-medium text-slate-600 bg-slate-100 rounded-full">{{ $r->type->label() }}</span>
                            @endif
                            <span class="px-2.5 py-0.5 text-xs font-medium rounded-full {{ $badge }}">{{ $r->status->label() }}</span>
                            <span class="hidden sm:block text-xs text-slate-400 tabular-nums w-14 text-right flex-none">{{ $r->created_at->format('n/j H:i') }}</span>
                        </a>
                    </li>
                @empty
                    <li class="px-6 py-14 text-center text-sm text-slate-400">최근 신고가 없습니다.</li>
                @endforelse
            </ul>
        </div>

        <!-- Right lists -->
        <div class="space-y-5">
            <!-- Events -->
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
                <div class="flex items-center gap-2 px-6 py-4 border-b border-slate-100">
                    <h2 class="text-base font-semibold text-slate-900">행사 현황</h2>
                    <a href="{{ route('admin.projects.index') }}" class="ml-auto text-sm font-medium text-blue-600 hover:text-blue-700">관리 →</a>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse($project_stats as $p)
                        @php $live = $p->end_date && $p->end_date->endOfDay()->isFuture(); @endphp
                        <li>
                            <a href="{{ route('admin.projects.show', $p->id) }}" class="flex items-center gap-3 px-6 py-3.5 hover:bg-slate-50 transition-colors">
                                <span class="grid place-items-center w-9 h-9 rounded-lg bg-blue-50 text-blue-600 text-sm font-semibold flex-none">{{ mb_substr($p->name, 0, 1) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-slate-800 truncate">{{ $p->name }}</p>
                                        <span class="flex items-center gap-1 text-xs font-medium flex-none {{ $live ? 'text-emerald-600' : 'text-slate-400' }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $live ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>{{ $live ? '진행중' : '종료' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400">~ {{ $p->end_date?->format('Y-m-d') ?? '기간 미정' }}</p>
                                </div>
                                <div class="text-right flex-none">
                                    <p class="text-base font-bold tabular-nums leading-none text-slate-900">{{ $p->requests_count }}</p>
                                    <p class="text-[11px] text-slate-400">신고</p>
                                </div>
                            </a>
                        </li>
                    @empty
                        <li class="px-6 py-10 text-center text-sm text-slate-400">등록된 행사가 없습니다.</li>
                    @endforelse
                </ul>
            </div>

            <!-- Members -->
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6">
                <div class="flex items-center gap-2 mb-4">
                    <h2 class="text-base font-semibold text-slate-900">회원</h2>
                    <a href="{{ route('admin.members') }}" class="ml-auto text-sm font-medium text-blue-600 hover:text-blue-700">관리 →</a>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-slate-50 py-4">
                        <p class="text-2xl font-bold tabular-nums text-slate-900">{{ $stats['total_users'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">전체</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 py-4">
                        <p class="text-2xl font-bold tabular-nums text-slate-900">{{ $stats['total_admins'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">관리자</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 py-4">
                        <p class="text-2xl font-bold tabular-nums text-slate-900">{{ $stats['total_rescuers'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">구조대원</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</x-layouts.admin>
