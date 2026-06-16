<x-layouts.app title="GPS119 - 비밀번호 재설정">
    <div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-50/50 w-full">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center mb-6">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-200">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                </div>
            </div>
            <h2 class="text-center text-3xl font-black tracking-tight text-slate-900">비밀번호 재설정</h2>
            <p class="mt-2 text-center text-sm font-medium text-slate-500">
                새로운 비밀번호를 설정해 주세요.
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-[400px]">
            <div class="bg-white py-10 px-6 sm:px-10 rounded-3xl border border-slate-200/60 shadow-xl shadow-slate-200/40">
                <form class="space-y-6" action="{{ route('password.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <div>
                        <label for="phone" class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">연락처</label>
                        <input id="phone" name="phone" type="tel" autocomplete="tel" required
                               value="{{ old('phone', $request->phone) }}"
                               class="appearance-none block w-full px-4 py-3 border @error('phone') border-red-300 @else border-slate-200 @enderror rounded-xl shadow-sm placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm font-medium"
                               placeholder="010-1234-5678">
                        @error('phone')
                            <p class="mt-1.5 ml-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">새 비밀번호</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required
                               class="appearance-none block w-full px-4 py-3 border @error('password') border-red-300 @else border-slate-200 @enderror rounded-xl shadow-sm placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm font-medium"
                               placeholder="••••••••">
                        @error('password')
                            <p class="mt-1.5 ml-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">비밀번호 확인</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                               class="appearance-none block w-full px-4 py-3 border border-slate-200 rounded-xl shadow-sm placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm font-medium"
                               placeholder="••••••••">
                    </div>

                    <div>
                        <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-lg shadow-blue-500/20 text-sm font-black text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/20 transition-all active:scale-[0.98]">
                            비밀번호 재설정
                        </button>
                    </div>
                </form>
            </div>

            <p class="mt-8 text-center text-sm font-bold text-slate-500">
                <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-500">로그인으로 돌아가기</a>
            </p>
        </div>
    </div>
</x-layouts.app>
