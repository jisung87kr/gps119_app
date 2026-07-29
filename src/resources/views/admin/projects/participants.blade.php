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
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-base font-semibold text-slate-900">행사 기능 바로가기</h2>
            <p class="mt-0.5 text-xs text-slate-400">참가자가 입장·활동하는 /events 화면과 입장 코드입니다.</p>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm text-slate-500 w-24">입장 코드</span>
                <span class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-800 font-mono font-bold tracking-widest">{{ $project->join_code }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm text-slate-500 w-24">참가자 입장</span>
                <a href="{{ route('events.join.code', $project->join_code) }}" target="_blank" class="text-sm font-medium text-blue-600 hover:text-blue-700 break-all">{{ route('events.join.code', $project->join_code) }}</a>
            </div>
            <div class="pt-3 border-t border-slate-100">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">참가자용 화면 (참고 · 로그인/참가 상태에 따라 접근)</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('events.join') }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-50 border border-slate-200 rounded-lg hover:bg-slate-100">행사 입장</a>
                    <a href="{{ route('events.active', $project->id) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-50 border border-slate-200 rounded-lg hover:bg-slate-100">참가자 활동(위치·신고)</a>
                    <a href="{{ route('events.dispatch', $project->id) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-50 border border-slate-200 rounded-lg hover:bg-slate-100">구급대원 지령</a>
                </div>
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

                {{-- 역할·상태 변경 폼 --}}
                <form action="{{ route('admin.projects.participants.update', [$project->id, $p->user_id]) }}" method="POST" class="flex items-center gap-2">
                    @csrf @method('PATCH')
                    <select name="role" class="px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" @selected($p->role === $role)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected($p->status === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-2 text-sm font-medium text-blue-600 border border-slate-200 rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-colors">저장</button>
                </form>

                {{-- 제외 --}}
                <form action="{{ route('admin.projects.participants.destroy', [$project->id, $p->user_id]) }}" method="POST" onsubmit="return confirm('이 참가자를 행사에서 제외하시겠습니까?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-3 py-2 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">제외</button>
                </form>
            </div>
        @empty
            <div class="px-6 py-14 text-center text-sm text-slate-400">아직 참가자가 없습니다. 위에서 추가하거나, 참가자가 입장 코드로 입장하면 표시됩니다.</div>
        @endforelse
    </div>
</div>
</x-layouts.admin>
