<x-layouts.admin :title="'참가자 관리 - ' . $project->name">
<div class="p-5 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">참가자 관리</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $project->name }} · 행사 역할(EventRole)을 배정·관리합니다.</p>
        </div>
        <div class="sm:ml-auto flex items-center gap-2">
            <a href="{{ route('admin.projects.show', $project->id) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                행사 상세
            </a>
            <a href="{{ route('admin.control', ['project' => $project->id]) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 shadow-sm shadow-blue-600/20 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M19.5 12c0 1.2-.3 2.3-.9 3.3M4.5 12c0-1.2.3-2.3.9-3.3"/></svg>
                실시간 관제
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="flex items-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm font-medium ring-1 ring-emerald-200">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- 행사 기능 바로가기 --}}
    @php $joinUrl = route('events.join.code', $project->join_code); @endphp
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm" x-data="{ copied: null }">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-900">운영 인력 입장 안내</h2>
            <p class="mt-0.5 text-xs text-slate-400">아래 입장 링크·코드를 구급대·경찰·자원봉사 등 <b class="text-slate-500">운영 인력에게 공유</b>하면, 입장 후 관제 지도에 표시되고 역할을 배정할 수 있습니다.</p>
        </div>
        <div class="p-6 space-y-4">
            {{-- 입장 링크 (핵심 배포 대상) --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">참가자 입장 링크</label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $joinUrl }}" x-ref="joinUrl" onclick="this.select()"
                           class="flex-1 min-w-0 px-3.5 py-2.5 border border-slate-200 rounded-xl bg-slate-50 text-sm text-slate-700 font-mono">
                    <button type="button"
                            @click="$refs.joinUrl.select(); document.execCommand('copy'); try { navigator.clipboard.writeText(@js($joinUrl)); } catch (e) {} copied='url'; setTimeout(() => copied = null, 1500)"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 shadow-sm shadow-blue-600/20 flex-none transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        <span x-text="copied === 'url' ? '복사됨' : '링크 복사'"></span>
                    </button>
                </div>
            </div>
            {{-- 입장 코드 (링크 대신 구두/수기 공유용) --}}
            <div class="flex flex-wrap items-center gap-3 pt-1">
                <span class="text-sm text-slate-500 w-24">입장 코드</span>
                <span class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-800 font-mono font-bold tracking-widest">{{ $project->join_code }}</span>
                <button type="button"
                        @click="navigator.clipboard.writeText(@js($project->join_code)); copied='code'; setTimeout(() => copied = null, 1500)"
                        class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700">
                    <span x-text="copied === 'code' ? '복사됨' : '코드 복사'"></span>
                </button>
                <span class="text-xs text-slate-400">링크 없이 코드만 알려주고 <a href="{{ route('events.join') }}" target="_blank" class="text-blue-600 hover:underline">events/join</a> 에서 입력하게 할 수도 있습니다.</span>
            </div>
        </div>
    </div>

    {{-- 참가자 추가 --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-900">참가자 추가</h2>
        </div>
        <form action="{{ route('admin.projects.participants.store', $project->id) }}" method="POST" class="p-6 flex flex-col sm:flex-row flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">회원</label>
                <select name="user_id" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                    <option value="">회원 선택…</option>
                    @foreach($addableUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} @if($u->phone)· {{ $u->phone }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[160px]">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">행사 역할</label>
                <select name="role" required class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                    @foreach($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 shadow-sm shadow-blue-600/20 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                추가
            </button>
        </form>
        @error('user_id')<p class="px-6 pb-4 -mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- 명단 일괄 등록 (CSV) --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm" x-data="{ fileName: '' }">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-900">명단 일괄 등록</h2>
            <p class="mt-0.5 text-xs text-slate-400">
                참가자가 많으면 명단 파일로 한 번에 등록하세요. <b class="text-slate-500">엑셀에서 「다른 이름으로 저장 → CSV」로 저장해 올려주세요</b>(.xlsx 는 지원하지 않습니다).
            </p>
        </div>
        <div class="p-6 space-y-4">
            {{-- 형식 안내 --}}
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200/70 px-4 py-3.5 text-xs text-slate-500 space-y-1.5">
                <p><b class="text-slate-700">컬럼 순서</b> · 이름, 전화번호, 역할 <span class="text-slate-400">(첫 줄에 제목행이 있어도 됩니다)</span></p>
                <p><b class="text-slate-700">역할</b> · {{ collect($roles)->map(fn($r) => $r->label())->implode(' / ') }} <span class="text-slate-400">— 비워두면 참가자로 등록됩니다.</span></p>
                <p><b class="text-slate-700">전화번호</b> · <span class="tabular-nums">010-1234-5678</span> 처럼 하이픈이 있어도 됩니다. 이미 가입된 회원은 새로 만들지 않고 역할만 배정합니다.</p>
                <p><b class="text-slate-700">한도</b> · 최대 {{ number_format(\App\Services\ParticipantImportService::MAX_ROWS) }}행 / 1MB. 넘으면 파일을 나눠 올려주세요.</p>
            </div>

            <form action="{{ route('admin.projects.participants.import', $project->id) }}" method="POST" enctype="multipart/form-data"
                  class="flex flex-col sm:flex-row sm:items-center gap-3">
                @csrf
                <label class="flex-1 min-w-0 flex items-center gap-2.5 px-3.5 py-2.5 border border-dashed border-slate-300 rounded-xl bg-white cursor-pointer hover:border-blue-400 hover:bg-blue-50/40 transition-colors">
                    <svg class="w-4 h-4 text-slate-400 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg>
                    <span class="text-sm truncate" :class="fileName ? 'text-slate-800 font-medium' : 'text-slate-400'"
                          x-text="fileName || 'CSV 파일 선택…'">CSV 파일 선택…</span>
                    <input type="file" name="file" accept=".csv,text/csv,text/plain" required class="sr-only"
                           @change="fileName = $event.target.files.length ? $event.target.files[0].name : ''">
                </label>
                <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 shadow-sm shadow-blue-600/20 flex-none transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/></svg>
                    업로드
                </button>
                <a href="{{ route('admin.projects.participants.template', $project->id) }}"
                   class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 flex-none transition-colors">
                    양식 다운로드
                </a>
            </form>

            @error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- 결과 리포트 --}}
        @if($report = session('importReport'))
            <div class="border-t border-slate-100 p-6 space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200/70 px-4 py-3">
                        <p class="text-xs text-slate-400">총 행</p>
                        <p class="mt-0.5 text-lg font-bold text-slate-800 tabular-nums">{{ number_format($report['total']) }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200/70 px-4 py-3">
                        <p class="text-xs text-emerald-600/70">성공</p>
                        <p class="mt-0.5 text-lg font-bold text-emerald-700 tabular-nums">{{ number_format($report['success']) }}</p>
                    </div>
                    <div class="rounded-xl bg-blue-50 ring-1 ring-blue-200/70 px-4 py-3">
                        <p class="text-xs text-blue-600/70">신규 회원</p>
                        <p class="mt-0.5 text-lg font-bold text-blue-700 tabular-nums">{{ number_format($report['created_users']) }}</p>
                    </div>
                    <div class="rounded-xl {{ $report['failed'] ? 'bg-red-50 ring-red-200/70' : 'bg-slate-50 ring-slate-200/70' }} ring-1 px-4 py-3">
                        <p class="text-xs {{ $report['failed'] ? 'text-red-600/70' : 'text-slate-400' }}">실패</p>
                        <p class="mt-0.5 text-lg font-bold {{ $report['failed'] ? 'text-red-700' : 'text-slate-800' }} tabular-nums">{{ number_format($report['failed']) }}</p>
                    </div>
                </div>

                @if(!empty($report['errors']))
                    <div class="rounded-xl ring-1 ring-red-200/70 overflow-hidden">
                        <div class="px-4 py-2.5 bg-red-50 text-xs font-semibold text-red-700">
                            처리하지 못한 행 — 아래 줄만 고쳐서 다시 올리면 됩니다 (성공한 행은 다시 올려도 중복되지 않습니다)
                        </div>
                        <div class="divide-y divide-red-100 max-h-80 overflow-y-auto bg-white">
                            @foreach($report['errors'] as $err)
                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-4 py-2.5 text-xs">
                                    <span class="font-mono font-semibold text-red-600 tabular-nums flex-none">{{ $err['line'] }}행</span>
                                    <span class="text-slate-700 flex-1 min-w-[140px] truncate">{{ $err['raw'] }}</span>
                                    <span class="text-red-600">{{ $err['reason'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if($report['hidden_errors'] > 0)
                            <div class="px-4 py-2.5 bg-red-50 text-xs text-red-600">
                                외 {{ number_format($report['hidden_errors']) }}행이 더 실패했습니다. 위 항목부터 고친 뒤 다시 올려주세요.
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- 참가자 목록 --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="flex items-center gap-2 px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-900">참가자 목록</h2>
            <span class="px-2 py-0.5 text-xs font-medium text-slate-500 bg-slate-100 rounded-full tabular-nums">{{ $participants->count() }}명</span>
        </div>

        @forelse($participants as $p)
            <div class="flex flex-wrap items-center gap-x-3 gap-y-3 px-6 py-4 border-b border-slate-100 last:border-b-0">
                {{-- 이름/연락처 --}}
                <div class="flex items-center gap-3 flex-1 min-w-[180px]">
                    <span class="grid place-items-center w-9 h-9 rounded-full bg-blue-600 text-white text-sm font-semibold flex-none">{{ mb_substr($p->user->name ?? '?', 0, 1) }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ $p->user->name ?? '알 수 없음' }}</p>
                        <p class="text-xs text-slate-400 tabular-nums">{{ $p->user->phone ?? '-' }}</p>
                    </div>
                </div>

                {{-- 위치공유 / 마지막 접속 --}}
                <div class="flex items-center gap-2 text-xs text-slate-400 min-w-[120px]">
                    @if($p->sharing_location)
                        <span class="inline-flex items-center gap-1 {{ $p->isOnline() ? 'text-emerald-600' : 'text-slate-400' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $p->isOnline() ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                            {{ $p->isOnline() ? '온라인' : ($p->last_seen_at ? $p->last_seen_at->diffForHumans() : '위치 없음') }}
                        </span>
                    @else
                        <span class="text-slate-300">위치 공유 꺼짐</span>
                    @endif
                </div>

                {{--
                    역할·상태 변경 + 제외를 한 덩어리로 묶는다. 모바일에서는 셀렉트를
                    한 줄(각 50%), 저장·제외를 그 아래 한 줄로 흘린다.
                    셀렉트에 min-w-0 이 없으면 버튼이 밀려 라벨이 「저/장」으로 세로로 쪼개진다.
                --}}
                <div class="grid w-full grid-cols-2 items-center gap-2 sm:flex sm:w-auto">
                    {{--
                        모바일에서는 form 을 display:contents 로 만들어 자식(셀렉트 2개 + 저장)이
                        바깥 그리드의 셀이 되게 한다 → [역할][상태] / [저장][제외] 2행 2열로 떨어진다.
                        @csrf·@method 가 만드는 hidden input 은 display:none 이라 셀을 먹지 않는다.
                    --}}
                    <form action="{{ route('admin.projects.participants.update', [$project->id, $p->user_id]) }}" method="POST"
                          class="contents sm:flex sm:items-center sm:gap-2">
                        @csrf @method('PATCH')
                        <select name="role" class="w-full min-w-0 px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 sm:w-auto">
                            @foreach($roles as $role)
                                <option value="{{ $role->value }}" @selected($p->role === $role)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                        <select name="status" class="w-full min-w-0 px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400 sm:w-auto">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected($p->status === $status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full whitespace-nowrap px-3 py-2 text-sm font-medium text-blue-600 border border-slate-200 rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors sm:w-auto">저장</button>
                    </form>

                    <form action="{{ route('admin.projects.participants.destroy', [$project->id, $p->user_id]) }}" method="POST"
                          class="contents sm:block" onsubmit="return confirm('이 참가자를 행사에서 제외하시겠습니까?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full whitespace-nowrap px-3 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors sm:w-auto">제외</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-6 py-14 text-center text-sm text-slate-400">아직 참가자가 없습니다. 위에서 추가하거나, 참가자가 입장 코드로 입장하면 표시됩니다.</div>
        @endforelse
    </div>
</div>
</x-layouts.admin>
