<x-layouts.admin title="회원 관리 - GPS119 관리자">
    <div class="p-5 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">회원 관리</h1>
                <p class="mt-1 text-sm text-slate-500">시스템 사용자들을 관리하세요.</p>
            </div>
            <div class="sm:ml-auto flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.members.create') }}"
                   class="inline-flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                    회원 등록
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="p-4 sm:p-5">
                {{--
                    flex-wrap 은 row 방향(sm 이상)에만 건다. flex-col 과 같이 두면
                    높이가 정해지지 않은 세로 래핑이 되어 버튼 줄이 42px → 62px 로 부풀었다.
                --}}
                <form method="GET" action="{{ route('admin.members') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <div class="flex-1 min-w-0 relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="이름, 이메일, 연락처로 검색..."
                               class="w-full pl-9 pr-3 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                    </div>
                    <select name="role" class="px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                        <option value="">모든 역할</option>
                        <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>관리자</option>
                        <option value="rescuer" {{ request('role') === 'rescuer' ? 'selected' : '' }}>구조대</option>
                    </select>
                    <div class="flex gap-2">
                        {{--
                            button 은 텍스트를 세로 중앙에 두지만 a(block)는 위로 붙는다.
                            둘 다 늘어나므로 inline-flex + items-center 로 정렬을 맞춘다.
                        --}}
                        <button type="submit" class="inline-flex flex-1 items-center justify-center sm:flex-none bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 transition-colors font-medium text-sm shadow-sm shadow-blue-600/20">
                            검색
                        </button>
                        <a href="{{ route('admin.members') }}" class="inline-flex flex-1 items-center justify-center sm:flex-none bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 transition-colors font-medium text-sm">
                            초기화
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Members -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            {{--
                모바일(<lg)은 카드 리스트. 테이블은 자연폭이 836px 라 375px 화면에서는
                가로 스크롤 없이는 「작업」 열(상세보기·수정)에 아예 도달할 수 없다.
            --}}
            <ul class="divide-y divide-slate-100 lg:hidden">
                @forelse($members as $member)
                    <li class="p-4 space-y-3">
                        <div class="flex items-start gap-3">
                            <span class="grid place-items-center w-10 h-10 rounded-full bg-blue-600 text-white text-sm font-semibold flex-none">{{ mb_substr($member->name, 0, 1) }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-slate-800 break-all">{{ $member->name }}</div>
                                @if($member->email)
                                    <div class="text-xs text-slate-400 break-all">{{ $member->email }}</div>
                                @endif
                            </div>
                            <div class="flex flex-wrap justify-end gap-1 flex-none">
                                @if($member->hasRole('admin'))
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-red-50 text-red-700 ring-1 ring-red-200">관리자</span>
                                @endif
                                @if($member->hasRole('rescuer'))
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">구조대</span>
                                @endif
                                @if(!$member->hasAnyRole(['admin', 'rescuer']))
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600">사용자</span>
                                @endif
                            </div>
                        </div>

                        <dl class="grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <dt class="text-slate-400">연락처</dt>
                                <dd class="mt-0.5 text-slate-700 tabular-nums">
                                    @if($member->formatted_phone)
                                        {{-- 구조 도메인이라 명단에서 바로 전화 거는 동선이 실제로 쓰인다 --}}
                                        <a href="tel:{{ $member->phone }}" class="text-blue-600 hover:text-blue-700">{{ $member->formatted_phone }}</a>
                                    @else
                                        -
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-slate-400">구조요청</dt>
                                <dd class="mt-0.5 text-slate-700 tabular-nums">{{ $member->requests_count }}건</dd>
                            </div>
                            <div>
                                <dt class="text-slate-400">가입일</dt>
                                <dd class="mt-0.5 text-slate-500 tabular-nums">{{ $member->created_at->format('Y-m-d') }}</dd>
                            </div>
                        </dl>

                        <div class="flex gap-2">
                            <a href="{{ route('admin.members.show', $member->id) }}"
                               class="flex-1 inline-flex h-11 items-center justify-center rounded-xl bg-blue-50 text-sm font-medium text-blue-700 active:bg-blue-100">상세보기</a>
                            <a href="{{ route('admin.members.edit', $member->id) }}"
                               class="flex-1 inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 text-sm font-medium text-slate-700 active:bg-slate-50">수정</a>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-14 text-center text-sm text-slate-400">검색된 회원이 없습니다.</li>
                @endforelse
            </ul>

            <div class="hidden lg:block overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/70">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">사용자</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">연락처</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">역할</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">구조요청</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">가입일</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">작업</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($members as $member)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <span class="grid place-items-center w-9 h-9 rounded-full bg-blue-600 text-white text-sm font-semibold flex-none">{{ mb_substr($member->name, 0, 1) }}</span>
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-slate-800 truncate">{{ $member->name }}</div>
                                            @if($member->email)
                                                <div class="text-sm text-slate-400 truncate">{{ $member->email }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 tabular-nums">
                                    {{ $member->formatted_phone ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        @if($member->hasRole('admin'))
                                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-red-50 text-red-700 ring-1 ring-red-200">관리자</span>
                                        @endif
                                        @if($member->hasRole('rescuer'))
                                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">구조대</span>
                                        @endif
                                        @if(!$member->hasAnyRole(['admin', 'rescuer']))
                                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600">사용자</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700 tabular-nums">
                                    {{ $member->requests_count }}건
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 tabular-nums">
                                    {{ $member->created_at->format('Y-m-d') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('admin.members.show', $member->id) }}"
                                           class="text-blue-600 hover:text-blue-700">상세보기</a>
                                        <a href="{{ route('admin.members.edit', $member->id) }}"
                                           class="text-slate-500 hover:text-slate-700">수정</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-14 text-center text-sm text-slate-400">
                                    검색된 회원이 없습니다.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($members->hasPages())
                <div class="px-6 py-4 border-t border-slate-100">
                    {{ $members->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
