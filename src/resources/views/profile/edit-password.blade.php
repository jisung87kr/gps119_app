<x-layouts.app>
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Page Header -->
        <div class="mb-10 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">비밀번호 변경</h1>
                <p class="text-sm text-slate-500 font-medium mt-1">주기적으로 비밀번호를 변경하여 계정을 안전하게 보호하세요.</p>
            </div>
            <a href="{{ route('profile.show') }}" class="text-xs font-bold text-slate-400 hover:text-slate-900 transition-colors">돌아가기</a>
        </div>

        <div class="max-w-xl mx-auto">
            <div class="bg-slate-900 rounded-3xl p-8 sm:p-10 shadow-2xl overflow-hidden relative">
                <div class="absolute top-0 right-0 -mt-8 -mr-8 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl"></div>
                
                <div class="relative z-10">
                    @if(session('status'))
                        <div class="bg-white/10 border border-white/10 text-white px-4 py-3 rounded-xl mb-8 text-sm font-bold shadow-sm backdrop-blur-sm">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-8">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="current_password" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">현재 비밀번호</label>
                            <input type="password"
                                   class="appearance-none block w-full px-4 py-4 bg-white/5 border border-white/10 rounded-2xl text-white placeholder-slate-600 focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-bold"
                                   id="current_password" name="current_password" required placeholder="현재 사용 중인 비밀번호">
                            @error('current_password')
                                <p class="text-red-400 text-[10px] font-bold mt-2 ml-1 tracking-tight">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label for="password" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">새 비밀번호</label>
                                <input type="password"
                                       class="appearance-none block w-full px-4 py-4 bg-white/5 border border-white/10 rounded-2xl text-white placeholder-slate-600 focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-bold"
                                       id="password" name="password" required placeholder="8자 이상, 영문/숫자/특수문자 조합 권장">
                                @error('password')
                                    <p class="text-red-400 text-[10px] font-bold mt-2 ml-1 tracking-tight">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">새 비밀번호 확인</label>
                                <input type="password"
                                       class="appearance-none block w-full px-4 py-4 bg-white/5 border border-white/10 rounded-2xl text-white placeholder-slate-600 focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm font-bold"
                                       id="password_confirmation" name="password_confirmation" required placeholder="새 비밀번호 다시 입력">
                            </div>
                        </div>

                        <div class="pt-4 flex flex-col gap-3">
                            <button type="submit" class="w-full py-4 bg-white text-slate-900 font-black text-sm rounded-2xl hover:bg-slate-100 transition-all active:scale-[0.98] uppercase tracking-widest shadow-xl">
                                Update Password
                            </button>
                            <a href="{{ route('profile.show') }}" class="w-full py-4 text-slate-400 font-black text-[10px] rounded-2xl hover:text-white transition-all text-center uppercase tracking-widest">
                                Cancel and return to profile
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Tips -->
            <div class="mt-8 p-6 rounded-2xl border border-slate-200/60 bg-white">
                <div class="flex gap-4">
                    <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-width="2.5"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-2">Password Security Tips</h3>
                        <ul class="text-[11px] font-bold text-slate-500 space-y-1.5 list-disc list-inside">
                            <li>최소 8자 이상으로 설정하세요.</li>
                            <li>대문자, 소문자, 숫자, 특수문자를 혼합하면 더욱 안전합니다.</li>
                            <li>다른 사이트에서 사용하는 것과 동일한 비밀번호는 피하세요.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
