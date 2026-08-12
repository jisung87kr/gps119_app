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
                        {{-- 셀렉트 옵션은 컴포넌트를 못 쓰므로 여기서 직접 가린다.
                             동명이인 구분에 필요한 뒤 4자리만 남긴다. --}}
                        <option value="{{ $u->id }}">{{ $u->name }}@if($u->phone) · ***{{ substr(preg_replace('/[^0-9]/', '', $u->phone), -4) }}@endif</option>
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
            <h2 class="text-base font-semibold text-slate-900">운영진 명단 일괄 등록</h2>
            <p class="mt-0.5 text-xs text-slate-400">
                운영진·경찰·자원봉사·구급대 등 <b class="text-slate-500">역할이 있는 사람</b>의 명단을 올립니다.
                일반 참가자는 올릴 필요가 없습니다 — 입장 QR 로 들어오면 자동으로 참가자가 됩니다.
                <b class="text-slate-500">엑셀에서 「다른 이름으로 저장 → CSV」</b>로 저장해 올려주세요(.xlsx 는 지원하지 않습니다).
            </p>
        </div>
        <div class="p-6 space-y-4">
            {{-- 형식 안내 --}}
            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200/70 px-4 py-3.5 text-xs text-slate-500 space-y-1.5">
                <p><b class="text-slate-700">컬럼 순서</b> · 이름, 전화번호, 역할 <span class="text-slate-400">(첫 줄에 제목행이 있어도 됩니다)</span></p>
                <p><b class="text-slate-700">역할</b> · {{ collect($roles)->map(fn($r) => $r->label())->implode(' / ') }} <span class="text-slate-400">— 비워두면 참가자로 등록됩니다.</span></p>
                <p><b class="text-slate-700">전화번호</b> · <span class="tabular-nums">010-1234-5678</span> 처럼 하이픈이 있어도 됩니다. <b class="text-slate-700">이 번호가 명단의 기준</b>이라 본인이 입장할 때 쓰는 번호와 같아야 합니다.</p>
                <p><b class="text-slate-700">계정</b> · 계정을 미리 만들지 않습니다. 명단만 등록해 두면 <b class="text-slate-700">본인이 입장 QR 로 들어오는 순간 그 역할이 자동으로 붙습니다.</b> 이미 가입된 회원은 업로드 즉시 역할이 배정됩니다.</p>
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
                        <p class="text-xs text-emerald-600/70">역할 배정 완료</p>
                        <p class="mt-0.5 text-lg font-bold text-emerald-700 tabular-nums">{{ number_format($report['joined']) }}</p>
                    </div>
                    <div class="rounded-xl bg-blue-50 ring-1 ring-blue-200/70 px-4 py-3">
                        <p class="text-xs text-blue-600/70">입장 대기</p>
                        <p class="mt-0.5 text-lg font-bold text-blue-700 tabular-nums">{{ number_format($report['pending']) }}</p>
                    </div>
                    <div class="rounded-xl {{ $report['failed'] ? 'bg-red-50 ring-red-200/70' : 'bg-slate-50 ring-slate-200/70' }} ring-1 px-4 py-3">
                        <p class="text-xs {{ $report['failed'] ? 'text-red-600/70' : 'text-slate-400' }}">실패</p>
                        <p class="mt-0.5 text-lg font-bold {{ $report['failed'] ? 'text-red-700' : 'text-slate-800' }} tabular-nums">{{ number_format($report['failed']) }}</p>
                    </div>
                </div>

                {{-- 🔴 상황실은 전원의 실시간 위치와 신고자 연락처를 보는 자리다.
                     엑셀로 부여하는 것을 허용했으므로, 몇 명에게 줬는지는 반드시 보이게 한다 —
                     붙여넣기 사고는 단서가 없으면 영영 발견되지 않는다. --}}
                @if(($report['controllers'] ?? 0) > 0)
                    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200/70 px-4 py-3 text-sm text-amber-800">
                        <b>상황실 권한 {{ number_format($report['controllers']) }}명</b>이 이 명단으로 부여됐습니다.
                        상황실은 전원의 실시간 위치와 신고자 연락처를 볼 수 있습니다 — 의도한 인원이 맞는지 아래 목록에서 확인해 주세요.
                    </div>
                @endif

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

    {{-- 명단에 있는데 아직 입장하지 않은 사람.

         🔑 이게 «전화번호 오타»를 잡는 유일한 장치다. 명단 매칭은 전화번호 기준이라
            한 자리가 틀리면 그 운영진은 조용히 «참가자»로 입장하고 아무도 모른다.
            행사 시작 전에 이 목록이 비어 가는지 보면 발견할 수 있다. --}}
    @if($rosterPending->isNotEmpty())
        <div class="bg-white rounded-2xl border border-amber-200/80 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-amber-100 bg-amber-50/60 flex items-baseline justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-amber-900">입장 대기 명단</h2>
                    <p class="mt-0.5 text-xs text-amber-700/80">
                        명단에는 있지만 아직 입장하지 않았습니다. 행사 시작 전에 이 목록이 줄어드는지 확인하세요 —
                        <b>전화번호가 한 자리라도 다르면 그 사람은 «참가자»로 입장하고 이 줄은 그대로 남습니다.</b>
                    </p>
                </div>
                <span class="flex-none text-sm font-bold text-amber-800 tabular-nums">
                    {{ number_format($rosterPending->count()) }} / {{ number_format($rosterTotal) }}
                </span>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($rosterPending as $row)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-3">
                        <span class="font-medium text-slate-800 min-w-[6rem]">{{ $row->name ?: '이름 없음' }}</span>
                        <x-ui.phone :value="$row->phone" class="font-mono text-sm text-slate-500" />
                        <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $row->role->badgeClasses() }}">
                            {{ $row->role->label() }}
                        </span>
                        <form action="{{ route('admin.projects.roster.destroy', [$project->id, $row->id]) }}" method="POST" class="ml-auto">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('이 줄을 명단에서 삭제할까요?')"
                                    class="text-xs font-medium text-slate-400 hover:text-red-600 transition-colors">삭제</button>
                        </form>
                    </div>
                @endforeach
            </div>
            @error('roster')
                <p class="px-6 py-3 text-sm text-red-600 border-t border-slate-100">{{ $message }}</p>
            @enderror
        </div>
    @endif

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
                        <p class="text-xs text-slate-400"><x-ui.phone :value="$p->user->phone" /></p>
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
