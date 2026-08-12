<x-layouts.admin title="구조요청 상세정보 - GPS119 관리자">
    <div class="p-5 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">구조요청 #{{ $rescueRequest->id }}</h1>
                <p class="mt-1 text-sm text-slate-500">구조 요청 상세 정보를 확인하고 관리하세요.</p>
            </div>
            <div class="sm:ml-auto flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.dashboard') }}"
                   class="inline-flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 px-4 py-2.5 rounded-xl hover:bg-slate-50 font-medium text-sm transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                    대시보드
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Request Information -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h2 class="text-base font-semibold text-slate-900">요청 정보</h2>
                    </div>

                    <div class="p-6 divide-y divide-slate-100">
                        <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                            <span class="text-sm text-slate-500">요청 ID</span>
                            <span class="text-sm font-medium text-slate-800 tabular-nums">#{{ $rescueRequest->id }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-4 py-3">
                            <span class="text-sm text-slate-500">현재 상태</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full
                                @if($rescueRequest->status->value === 'pending') bg-amber-50 text-amber-700
                                @elseif($rescueRequest->status->value === 'in_progress') bg-blue-50 text-blue-700
                                @elseif($rescueRequest->status->value === 'completed') bg-emerald-50 text-emerald-700
                                @elseif($rescueRequest->status->value === 'cancelled') bg-slate-100 text-slate-500
                                @else bg-slate-100 text-slate-600
                                @endif">
                                @if($rescueRequest->status->value === 'pending') 대기중
                                @elseif($rescueRequest->status->value === 'in_progress') 진행중
                                @elseif($rescueRequest->status->value === 'completed') 완료
                                @elseif($rescueRequest->status->value === 'cancelled') 취소됨
                                @else {{ $rescueRequest->status }}
                                @endif
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-4 py-3">
                            <span class="text-sm text-slate-500">요청일시</span>
                            <span class="text-sm font-medium text-slate-800 tabular-nums">{{ $rescueRequest->created_at->format('Y년 m월 d일 H:i') }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-4 py-3">
                            <span class="text-sm text-slate-500">담당 구조대원</span>
                            <span class="text-sm font-medium text-slate-800">{{ $rescueRequest->assignedRescuer->name ?? '미배정' }}</span>
                        </div>

                        @if($rescueRequest->location_latitude && $rescueRequest->location_longitude)
                            <div class="py-3">
                                <span class="block text-sm text-slate-500 mb-2">위치 정보</span>
                                <p class="text-sm font-medium text-slate-800 tabular-nums">
                                    위도: {{ $rescueRequest->location_latitude }},
                                    경도: {{ $rescueRequest->location_longitude }}
                                </p>
                                <a href="https://maps.google.com/?q={{ $rescueRequest->location_latitude }},{{ $rescueRequest->location_longitude }}"
                                   target="_blank"
                                   class="inline-flex items-center gap-1 mt-2 text-sm font-medium text-blue-600 hover:text-blue-700">Google 지도에서 보기</a>
                            </div>
                        @endif

                        @if($rescueRequest->description)
                            <div class="py-3 last:pb-0">
                                <span class="block text-sm text-slate-500 mb-2">요청 내용</span>
                                <p class="text-sm text-slate-800">{{ $rescueRequest->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Requester Information -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h2 class="text-base font-semibold text-slate-900">요청자 정보</h2>
                    </div>

                    <div class="p-6">
                        @if($rescueRequest->user)
                            <div class="divide-y divide-slate-100">
                                <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                                    <span class="text-sm text-slate-500">이름</span>
                                    <span class="text-sm font-medium text-slate-800">{{ $rescueRequest->user->name }}</span>
                                </div>

                                <div class="flex items-center justify-between gap-4 py-3">
                                    <span class="text-sm text-slate-500">연락처</span>
                                    <span class="text-sm font-medium text-slate-800 tabular-nums">{{ $rescueRequest->user->formatted_phone }}</span>
                                </div>

                                @if($rescueRequest->user->email)
                                    <div class="flex items-center justify-between gap-4 py-3">
                                        <span class="text-sm text-slate-500">이메일</span>
                                        <span class="text-sm font-medium text-slate-800">{{ $rescueRequest->user->email }}</span>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between gap-4 py-3 last:pb-0">
                                    <span class="text-sm text-slate-500">가입일</span>
                                    <span class="text-sm font-medium text-slate-800 tabular-nums">{{ $rescueRequest->user->created_at->format('Y년 m월 d일') }}</span>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-100">
                                <a href="{{ route('admin.members.show', $rescueRequest->user->id) }}"
                                   class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700">요청자 상세정보 보기</a>
                            </div>
                        @else
                            <p class="text-sm text-slate-400">요청자 정보를 찾을 수 없습니다.</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Management Actions -->
            <div class="space-y-6">
                <!-- Status Update -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="text-base font-semibold text-slate-900">상태 관리</h3>
                    </div>

                    <form method="POST" action="{{ route('admin.requests.update', $rescueRequest->id) }}" class="p-6 space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="status" class="block text-sm font-medium text-slate-700 mb-1.5">상태 변경</label>
                            <select name="status" id="status"
                                    class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                                <option value="pending" {{ $rescueRequest->status->value === 'pending' ? 'selected' : '' }}>대기중</option>
                                <option value="in_progress" {{ $rescueRequest->status->value === 'in_progress' ? 'selected' : '' }}>진행중</option>
                                <option value="completed" {{ $rescueRequest->status->value === 'completed' ? 'selected' : '' }}>완료</option>
                                <option value="cancelled" {{ $rescueRequest->status->value === 'cancelled' ? 'selected' : '' }}>취소됨</option>
                            </select>
                        </div>

                        <div>
                            <label for="assigned_rescuer_id" class="block text-sm font-medium text-slate-700 mb-1.5">담당 구조대원</label>
                            <select name="assigned_rescuer_id" id="assigned_rescuer_id"
                                    class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl bg-white text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-400">
                                <option value="">미배정</option>
                                @foreach($rescuers as $rescuer)
                                    <option value="{{ $rescuer->id }}"
                                            {{ $rescueRequest->assigned_rescuer_id == $rescuer->id ? 'selected' : '' }}>
                                        {{ $rescuer->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-1.5 bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors">
                            상태 업데이트
                        </button>
                    </form>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="text-base font-semibold text-slate-900">빠른 작업</h3>
                    </div>

                    <div class="p-6 space-y-3">
                        @if($rescueRequest->status->value === 'pending')
                            <form method="POST" action="{{ route('admin.requests.update', $rescueRequest->id) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="in_progress">
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-1.5 bg-blue-600 text-white px-4 py-2.5 rounded-xl hover:bg-blue-700 font-medium text-sm shadow-sm shadow-blue-600/20 transition-colors"
                                        onclick="return confirm('이 요청을 진행중으로 변경하시겠습니까?')">
                                    진행중으로 변경
                                </button>
                            </form>
                        @endif

                        @if($rescueRequest->status->value === 'in_progress')
                            <form method="POST" action="{{ route('admin.requests.update', $rescueRequest->id) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="completed">
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-1.5 bg-emerald-600 text-white px-4 py-2.5 rounded-xl hover:bg-emerald-700 font-medium text-sm shadow-sm shadow-emerald-600/20 transition-colors"
                                        onclick="return confirm('이 요청을 완료로 변경하시겠습니까?')">
                                    완료로 변경
                                </button>
                            </form>
                        @endif

                        {{-- ⚠️ 예전 조건은 `$rescueRequest->status !== 'cancelled'` 였다. status 는 enum 이라
                             문자열과의 비교가 «항상 참»이었고, 이미 취소·완료된 건에도 취소 버튼이 떴다.
                             비교는 enum 케이스로 — 판정은 모델이 이미 갖고 있다. --}}
                        @if($rescueRequest->canBeCancelled())
                            <form method="POST" action="{{ route('admin.requests.update', $rescueRequest->id) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="cancelled">
                                <input type="text" name="cancel_reason" maxlength="500"
                                       placeholder="취소 사유 (기록에 남습니다)"
                                       class="w-full mb-2 px-3 py-2 text-sm rounded-xl border border-slate-200 focus:border-red-300 focus:ring-1 focus:ring-red-200 outline-none">
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-1.5 bg-white text-red-600 border border-red-200 px-4 py-2.5 rounded-xl hover:bg-red-50 font-medium text-sm transition-colors"
                                        onclick="return confirm('이 요청을 취소하시겠습니까? 이 작업은 되돌릴 수 없습니다.')">
                                    요청 취소
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <!-- Request Timeline -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="text-base font-semibold text-slate-900">처리 기록</h3>
                    </div>

                    <div class="p-6 space-y-3">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-slate-800">요청 생성</p>
                                <p class="text-xs text-slate-400 tabular-nums">{{ $rescueRequest->created_at->format('Y-m-d H:i') }}</p>
                            </div>
                        </div>

                        @if($rescueRequest->updated_at > $rescueRequest->created_at)
                            <div class="flex items-start">
                                <div class="flex-shrink-0 w-2 h-2 bg-emerald-500 rounded-full mt-2"></div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-slate-800">상태 업데이트</p>
                                    <p class="text-xs text-slate-400 tabular-nums">{{ $rescueRequest->updated_at->format('Y-m-d H:i') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
