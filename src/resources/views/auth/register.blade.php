<x-layouts.app title="GPS119 - 회원가입">
    <div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-50/50 w-full">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center mb-6">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-200">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    </svg>
                </div>
            </div>
            <h2 class="text-center text-3xl font-black tracking-tight text-slate-900">회원가입</h2>
            <p class="mt-2 text-center text-sm font-medium text-slate-500">
                GPS119 서비스 이용을 위해 가입해 주세요.
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-[400px]">
            <div class="bg-white py-10 px-6 sm:px-10 rounded-3xl border border-slate-200/60 shadow-xl shadow-slate-200/40">
                <form class="space-y-6" action="{{ route('register') }}" method="POST">
                    @csrf
                    <div>
                        <label for="phone" class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">연락처</label>
                        <div class="relative">
                            <input id="phone" name="phone" type="tel" autocomplete="tel" required autofocus
                                   value="{{ old('phone') }}"
                                   class="appearance-none block w-full px-4 py-3 border @error('phone') border-red-300 @else border-slate-200 @enderror rounded-xl shadow-sm placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm font-medium"
                                   placeholder="010-1234-5678">
                        </div>
                        @error('phone')
                            <p class="mt-1.5 ml-1 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-1.5 ml-1">비밀번호</label>
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
                            회원가입
                        </button>
                    </div>
                </form>

                <div class="mt-8">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-slate-100"></div>
                        </div>
                        <div class="relative flex justify-center text-xs font-bold uppercase tracking-widest">
                            <span class="bg-white px-4 text-slate-300">간편 가입</span>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-3">
                        <a href="{{ route('login.social', 'naver') }}" class="flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-sm font-bold text-white bg-[#03C75A] hover:brightness-95 transition-all active:scale-[0.98]">
                            <span class="text-base font-black">N</span>
                            네이버로 시작하기
                        </a>
                    </div>
                </div>
            </div>

            <p class="mt-8 text-center text-sm font-bold text-slate-500">
                이미 계정이 있으신가요?
                <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-500 ml-1">로그인하기</a>
            </p>
        </div>
    </div>
</x-layouts.app>
