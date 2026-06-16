<x-layouts.app>
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Page Header -->
        <div class="mb-10">
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">프로필 설정</h1>
            <p class="text-sm text-slate-500 font-medium mt-1">계정 정보를 확인하고 보안 설정을 관리하세요.</p>
        </div>

        @if(session('status'))
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-xl mb-8 text-sm font-bold shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left Column: Main Info -->
            <div class="lg:col-span-8 space-y-6">
                <div class="bg-white rounded-3xl border border-slate-200/60 shadow-sm overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest">개인 정보</h2>
                        <a href="{{ route('profile.edit') }}" class="text-xs font-bold text-blue-600 hover:underline">정보 수정 →</a>
                    </div>
                    <div class="p-8">
                        <!-- Social Login Status -->
                        @if(auth()->user()->provider)
                            <div class="mb-8 p-4 rounded-2xl bg-slate-50 border border-slate-200/60 flex items-center gap-4">
                                @if(auth()->user()->provider === 'naver')
                                    <div class="w-10 h-10 bg-[#03C75A] rounded-xl flex items-center justify-center shrink-0">
                                        <span class="text-white font-black text-sm">N</span>
                                    </div>
                                @elseif(auth()->user()->provider === 'kakao')
                                    <div class="w-10 h-10 bg-[#FEE500] rounded-xl flex items-center justify-center shrink-0">
                                        <span class="text-[#3C1E1E] font-black text-sm">K</span>
                                    </div>
                                @endif
                                <div>
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-0.5">{{ ucfirst(auth()->user()->provider) }} 계정 연동됨</p>
                                    <p class="text-sm font-bold text-slate-700">소셜 계정으로 간편하게 로그인 중입니다.</p>
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 gap-y-8">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">이름</label>
                                <p class="text-base font-bold text-slate-900 ml-1">{{ auth()->user()->name }}</p>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">연락처</label>
                                <p class="text-base font-bold text-slate-900 ml-1">{{ auth()->user()->formatted_phone }}</p>
                            </div>

                            @if(auth()->user()->email)
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">이메일 주소</label>
                                    <p class="text-base font-bold text-slate-900 ml-1">{{ auth()->user()->email }}</p>
                                </div>
                            @endif

                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">가입일</label>
                                <p class="text-base font-bold text-slate-900 ml-1">{{ auth()->user()->created_at->format('Y년 m월 d일') }}</p>
                            </div>

                            @if(auth()->user()->hasRole('rescuer'))
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">계정 권한</label>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-700 ml-1">구조대원</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="bg-white rounded-3xl border border-slate-200/60 shadow-sm overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50">
                        <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest">최근 구조 요청 내역</h2>
                    </div>
                    <div class="divide-y divide-slate-50">
                        @forelse(auth()->user()->requests()->latest()->limit(5)->get() as $request)
                            <div class="flex items-center justify-between p-6 hover:bg-slate-50/50 transition-colors">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" stroke-width="2"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-900 tracking-tight">{{ $request->address ?? '위치 정보 없음' }}</p>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight">{{ $request->created_at->format('Y-m-d H:i') }}</p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest
                                    @if($request->status === 'pending') bg-amber-100 text-amber-700
                                    @elseif($request->status === 'in_progress') bg-blue-100 text-blue-700
                                    @elseif($request->status === 'completed') bg-emerald-100 text-emerald-700
                                    @else bg-slate-100 text-slate-600
                                    @endif">
                                    {{ $request->status }}
                                </span>
                            </div>
                        @empty
                            <div class="py-16 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-3 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-width="1.5"/></svg>
                                <p class="text-xs font-bold italic">아직 구조 요청 내역이 없습니다.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right Column: Sidebar -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Actions -->
                <div class="bg-slate-900 p-8 rounded-3xl shadow-xl">
                    <h3 class="text-xs font-black text-white uppercase tracking-widest mb-6">Account Management</h3>
                    <div class="grid grid-cols-1 gap-3">
                        <a href="{{ route('profile.edit') }}" class="flex items-center justify-between p-3 px-4 bg-white/10 hover:bg-white/20 text-white rounded-xl transition-all group">
                            <span class="text-xs font-bold">프로필 수정</span>
                            <svg class="w-4 h-4 opacity-50 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                        </a>

                        @if(!auth()->user()->provider)
                            <a href="{{ route('profile.password.edit') }}" class="flex items-center justify-between p-3 px-4 bg-white/10 hover:bg-white/20 text-white rounded-xl transition-all group">
                                <span class="text-xs font-bold">비밀번호 변경</span>
                                <svg class="w-4 h-4 opacity-50 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                            </a>
                        @endif

                        <a href="{{ route('profile.delete') }}" class="flex items-center justify-between p-3 px-4 bg-orange-600/20 hover:bg-orange-600/30 text-orange-400 rounded-xl transition-all group">
                            <span class="text-xs font-bold">회원 탈퇴</span>
                            <svg class="w-4 h-4 opacity-50 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5l7 7-7 7" stroke-width="2.5"/></svg>
                        </a>
                        
                        <div class="my-2 border-t border-white/5"></div>
                        
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center justify-between p-3 px-4 bg-red-600 hover:bg-red-500 text-white rounded-xl transition-all group">
                                <span class="text-xs font-bold">로그아웃</span>
                                <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-width="2.5"/></svg>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Stats -->
                <div class="bg-white p-8 rounded-3xl border border-slate-200/60 shadow-sm">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-6">Activity Stats</h3>
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-tight">총 구조 요청</span>
                            <span class="text-xl font-black text-slate-900 tracking-tighter">{{ auth()->user()->requests()->count() }}건</span>
                        </div>
                        
                        @if(auth()->user()->hasRole('rescuer'))
                            <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-tight">처리 완료 건수</span>
                                <span class="text-xl font-black text-emerald-600 tracking-tighter">{{ auth()->user()->assignedRequests()->where('status', 'completed')->count() }}건</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
