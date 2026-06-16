<x-layouts.app>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Hero Section -->
        <div class="relative overflow-hidden rounded-3xl bg-slate-900 px-6 py-12 sm:px-12 sm:py-16 shadow-2xl mb-12">
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20"></div>
            <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-600 rounded-full blur-3xl opacity-20"></div>
            
            <div class="relative z-10 max-w-2xl">
                <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight mb-4">
                    당신의 안전을 위한 <br class="hidden sm:block"> <span class="text-blue-400">가장 빠른 방법.</span>
                </h1>
                <p class="text-lg text-slate-400 font-medium mb-8 leading-relaxed">
                    긴급 상황에서 GPS 위치 정보를 활용하여 신속하게 구조 요청을 할 수 있는 GPS119 서비스입니다.
                </p>
                
                <div class="flex flex-wrap gap-4">
                    @guest
                        <a href="{{ route('login') }}" class="px-8 py-3.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-500 transition-all shadow-lg shadow-blue-900/20 active:scale-95">로그인하여 시작하기</a>
                        <a href="{{ route('register') }}" class="px-8 py-3.5 bg-white/10 text-white font-bold rounded-xl hover:bg-white/20 transition-all backdrop-blur-sm active:scale-95 border border-white/10">회원가입</a>
                    @else
                        <a href="{{ route('request.create') }}" class="px-8 py-3.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-500 transition-all shadow-lg shadow-red-900/20 active:scale-95 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            긴급 구조 요청
                        </a>
                        <a href="{{ route('profile.show') }}" class="px-8 py-3.5 bg-white/10 text-white font-bold rounded-xl hover:bg-white/20 transition-all backdrop-blur-sm active:scale-95 border border-white/10">내 프로필 관리</a>
                    @endguest
                </div>
            </div>
        </div>

        <!-- Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="group bg-white p-8 rounded-2xl border border-slate-200/60 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center mb-6 group-hover:bg-red-600 transition-colors duration-300">
                    <svg class="w-6 h-6 text-red-600 group-hover:text-white transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">신속한 위치 공유</h3>
                <p class="text-sm text-slate-500 font-medium leading-relaxed">복잡한 절차 없이 클릭 한 번으로 당신의 정확한 GPS 위치를 구조대에 전달합니다.</p>
            </div>

            <div class="group bg-white p-8 rounded-2xl border border-slate-200/60 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center mb-6 group-hover:bg-blue-600 transition-colors duration-300">
                    <svg class="w-6 h-6 text-blue-600 group-hover:text-white transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">실시간 대응 시스템</h3>
                <p class="text-sm text-slate-500 font-medium leading-relaxed">구조 대원이 실시간으로 요청을 확인하고, 대응 상황을 즉시 파악할 수 있는 관제 시스템을 제공합니다.</p>
            </div>

            <div class="group bg-white p-8 rounded-2xl border border-slate-200/60 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center mb-6 group-hover:bg-emerald-600 transition-colors duration-300">
                    <svg class="w-6 h-6 text-emerald-600 group-hover:text-white transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-2">검증된 보안 기술</h3>
                <p class="text-sm text-slate-500 font-medium leading-relaxed">모든 개인 정보와 위치 데이터는 강력한 암호화 기술로 보호되며, 인가된 인원만 접근 가능합니다.</p>
            </div>
        </div>
    </div>
</x-layouts.app>
