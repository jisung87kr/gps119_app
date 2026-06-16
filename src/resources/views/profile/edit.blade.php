<x-layouts.app>
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Page Header -->
        <div class="mb-10 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">프로필 수정</h1>
                <p class="text-sm text-slate-500 font-medium mt-1">개인 정보를 최신 상태로 유지하세요.</p>
            </div>
            <a href="{{ route('profile.show') }}" class="text-xs font-bold text-slate-400 hover:text-slate-900 transition-colors">돌아가기</a>
        </div>

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-3xl border border-slate-200/60 shadow-xl shadow-slate-200/40 overflow-hidden">
                <div class="p-8 sm:p-10">
                    @if(session('status'))
                        <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-xl mb-8 text-sm font-bold shadow-sm">
                            {{ session('status') }}
                        </div>
                    @endif

                    <!-- Social Provider Info -->
                    @if(auth()->user()->provider)
                        <div class="mb-10 p-5 rounded-2xl bg-blue-50/50 border border-blue-100 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 shadow-sm {{ auth()->user()->provider === 'naver' ? 'bg-[#03C75A]' : 'bg-[#FEE500]' }}">
                                <span class="text-white font-black text-lg {{ auth()->user()->provider === 'kakao' ? 'text-[#3C1E1E]' : '' }}">
                                    {{ mb_substr(ucfirst(auth()->user()->provider), 0, 1) }}
                                </span>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-tight">{{ ucfirst(auth()->user()->provider) }} 계정 연동됨</h3>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-600 uppercase">Verified</span>
                                </div>
                                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">
                                    소셜 계정의 기본 정보가 자동으로 동기화됩니다.
                                </p>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.update') }}" class="space-y-8">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="name" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">이름</label>
                            <div class="relative group">
                                <input type="text"
                                       class="appearance-none block w-full px-4 py-3.5 border border-slate-200 rounded-2xl shadow-sm placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm font-bold text-slate-900 {{ auth()->user()->provider ? 'bg-slate-50/50 cursor-not-allowed opacity-70' : '' }}"
                                       id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required 
                                       {{ auth()->user()->provider ? 'readonly' : '' }}>
                                @if(auth()->user()->provider)
                                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            @error('name')
                                <p class="text-red-500 text-[10px] font-bold mt-2 ml-1 uppercase tracking-tight">{{ $message }}</p>
                            @enderror
                            @if(auth()->user()->provider)
                                <p class="text-[10px] font-bold text-slate-400 mt-2 ml-1 italic">* 소셜 계정 정보는 연동된 사이트에서 변경 가능합니다.</p>
                            @endif
                        </div>

                        <div>
                            <label for="phone" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">연락처</label>
                            <input type="tel"
                                   class="appearance-none block w-full px-4 py-3.5 border border-slate-200 rounded-2xl shadow-sm placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm font-bold text-slate-900"
                                   id="phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required placeholder="010-0000-0000">
                            @error('phone')
                                <p class="text-red-500 text-[10px] font-bold mt-2 ml-1 uppercase tracking-tight">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pt-4 flex flex-col sm:flex-row gap-3">
                            <button type="submit" class="flex-1 py-4 px-6 bg-blue-600 text-white font-black text-sm rounded-2xl shadow-lg shadow-blue-500/20 hover:bg-blue-500 transition-all active:scale-[0.98] uppercase tracking-widest">
                                저장
                            </button>
                            <a href="{{ route('profile.show') }}" class="flex-1 py-4 px-6 bg-slate-100 text-slate-600 font-black text-sm rounded-2xl hover:bg-slate-200 transition-all text-center uppercase tracking-widest">
                                취소
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Redundant Password Update for non-social users refined -->
            @if(!auth()->user()->provider)
                <div class="mt-8 bg-slate-900 rounded-3xl p-8 sm:p-10 shadow-2xl overflow-hidden relative">
                    <div class="absolute top-0 right-0 -mt-8 -mr-8 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl"></div>
                    
                    <div class="relative z-10">
                        <div class="mb-8">
                            <h2 class="text-xl font-black text-white tracking-tight">비밀번호 변경</h2>
                            <p class="text-xs text-slate-400 font-medium mt-1 uppercase tracking-widest">Security Update</p>
                        </div>

                        <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-6">
                            @csrf
                            @method('PATCH')

                            <div>
                                <label for="current_password" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">현재 비밀번호</label>
                                <input type="password"
                                       class="appearance-none block w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-600 focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-bold"
                                       id="current_password" name="current_password" required placeholder="••••••••">
                                @error('current_password')
                                    <p class="text-red-400 text-[10px] font-bold mt-2 ml-1 tracking-tight">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label for="password" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">새 비밀번호</label>
                                    <input type="password"
                                           class="appearance-none block w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-600 focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-bold"
                                           id="password" name="password" required placeholder="••••••••">
                                    @error('password')
                                        <p class="text-red-400 text-[10px] font-bold mt-2 ml-1 tracking-tight">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="password_confirmation" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">새 비밀번호 확인</label>
                                    <input type="password"
                                           class="appearance-none block w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-600 focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-bold"
                                           id="password_confirmation" name="password_confirmation" required placeholder="••••••••">
                                </div>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full py-4 bg-white text-slate-900 font-black text-xs rounded-xl hover:bg-slate-100 transition-all active:scale-[0.98] uppercase tracking-widest shadow-xl">
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
