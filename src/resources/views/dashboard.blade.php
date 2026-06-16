<x-layouts.app>
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">

        {{-- 상단: 인사 + 핵심 CTA --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
                    안녕하세요, {{ Auth::user()->name }}님 👋
                </h1>
                <p class="mt-1 text-sm font-medium text-slate-500">내 구조 요청 현황을 한눈에 확인하세요.</p>
            </div>
            <a href="{{ route('request.create') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-red-600 text-white text-sm font-bold rounded-xl hover:bg-red-500 transition-all shadow-lg shadow-red-200 active:scale-95 shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                긴급 구조 요청
            </a>
        </div>

        {{-- 통계 카드 --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
            @php
                $cards = [
                    ['label' => '전체 요청', 'value' => $stats['total'], 'accent' => 'text-slate-900', 'ring' => 'ring-slate-200'],
                    ['label' => '접수 대기', 'value' => $stats['pending'], 'accent' => 'text-amber-600', 'ring' => 'ring-amber-200'],
                    ['label' => '진행중', 'value' => $stats['in_progress'], 'accent' => 'text-blue-600', 'ring' => 'ring-blue-200'],
                    ['label' => '완료', 'value' => $stats['completed'], 'accent' => 'text-emerald-600', 'ring' => 'ring-emerald-200'],
                ];
            @endphp
            @foreach ($cards as $card)
                <div class="bg-white rounded-2xl border border-slate-200/60 p-4 sm:p-5 shadow-sm">
                    <p class="text-xs font-semibold text-slate-500 mb-1">{{ $card['label'] }}</p>
                    <p class="text-2xl sm:text-3xl font-black tracking-tight {{ $card['accent'] }}">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- 진행 중인 요청 강조 --}}
        @if ($activeRequests->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-blue-500"></span>
                    </span>
                    처리 중인 요청 {{ $activeRequests->count() }}건
                </h2>
                <div class="space-y-3">
                    @foreach ($activeRequests as $request)
                        <a href="{{ route('request.show', $request) }}"
                           class="block bg-white rounded-2xl border border-slate-200/60 p-5 shadow-sm hover:shadow-md hover:border-blue-200 transition-all">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold {{ $request->status->badgeClasses() }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $request->status->dotClass() }}"></span>
                                            {{ $request->status->label() }}
                                        </span>
                                        <span class="inline-flex px-2 py-0.5 rounded-md text-xs font-semibold {{ $request->priority->badgeClasses() }}">
                                            {{ $request->priority->label() }}
                                        </span>
                                    </div>
                                    <p class="text-base font-bold text-slate-900 truncate">
                                        {{ $request->address ?? '위치 확인 중' }}
                                    </p>
                                    <p class="mt-1 text-xs font-medium text-slate-400">
                                        {{ $request->requested_at?->diffForHumans() }}
                                        @if ($request->project)
                                            · {{ $request->project->name }}
                                        @endif
                                        @if ($request->assignedRescuer)
                                            · 담당 {{ $request->assignedRescuer->name }}
                                        @endif
                                    </p>
                                </div>
                                <svg class="w-5 h-5 text-slate-300 shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- 내 요청 내역 --}}
        <section>
            <h2 class="text-sm font-bold text-slate-700 mb-3">내 요청 내역</h2>

            @forelse ($recentRequests as $request)
                @if ($loop->first)
                    <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm divide-y divide-slate-100 overflow-hidden">
                @endif
                    <a href="{{ route('request.show', $request) }}"
                       class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50 transition-colors">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold shrink-0 {{ $request->status->badgeClasses() }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $request->status->dotClass() }}"></span>
                            {{ $request->status->label() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-900 truncate">{{ $request->address ?? '위치 확인 중' }}</p>
                            <p class="text-xs font-medium text-slate-400 truncate">
                                {{ $request->requested_at?->format('Y.m.d H:i') }}
                                @if ($request->project)
                                    · {{ $request->project->name }}
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex px-2 py-0.5 rounded-md text-xs font-semibold shrink-0 {{ $request->priority->badgeClasses() }}">
                            {{ $request->priority->label() }}
                        </span>
                    </a>
                @if ($loop->last)
                    </div>
                @endif
            @empty
                {{-- 빈 상태: 기존 마케팅 카피 재활용 --}}
                <div class="relative overflow-hidden rounded-3xl bg-slate-900 px-6 py-12 sm:px-12 sm:py-14 shadow-2xl text-center">
                    <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-600 rounded-full blur-3xl opacity-20"></div>
                    <div class="relative z-10 max-w-md mx-auto">
                        <h3 class="text-2xl font-black text-white tracking-tight mb-3">
                            아직 구조 요청이 없습니다
                        </h3>
                        <p class="text-sm text-slate-400 font-medium mb-7 leading-relaxed">
                            긴급 상황에서 GPS 위치 정보를 활용해 신속하게 구조 요청을 보낼 수 있습니다.
                        </p>
                        <a href="{{ route('request.create') }}"
                           class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-red-600 text-white text-sm font-bold rounded-xl hover:bg-red-500 transition-all shadow-lg shadow-red-900/20 active:scale-95">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            첫 구조 요청하기
                        </a>
                    </div>
                </div>
            @endforelse
        </section>

    </div>
</x-layouts.app>
