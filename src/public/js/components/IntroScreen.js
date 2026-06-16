export default {
    name: 'IntroScreen',
    props: {
        show: {
            type: Boolean,
            default: true
        },
        title: {
            type: String,
            default: '응급상황 위치공유 서비스'
        }
    },
    template: `
        <transition
            enter-active-class="transition ease-out duration-500"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-700"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-[9999] flex items-center justify-center overflow-hidden"
                style="background: linear-gradient(to bottom right, #1d4ed8 0%, #1e40af 50%, #312e81 100%);"
            >
                <!-- Decorative background elements -->
                <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-white/5 rounded-full blur-3xl"></div>
                <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-blue-400/10 rounded-full blur-3xl"></div>

                <div class="w-full text-center text-white px-8 relative z-10">
                    <div class="mb-8">
                        <div class="w-24 h-24 mx-auto mb-6 bg-white/10 rounded-3xl flex items-center justify-center backdrop-blur-md border border-white/20 shadow-2xl transform rotate-12 transition-transform duration-1000">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-white -rotate-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h1 class="text-4xl md:text-5xl font-black tracking-tighter italic">
                            GPS<span class="text-blue-300">119</span>
                        </h1>
                        <p class="text-lg md:text-xl font-medium opacity-90 tracking-tight">(주)바른인명구조단</p>
                        <div class="w-12 h-0.5 bg-blue-400/50 mx-auto my-4"></div>
                        <p class="text-sm md:text-base font-light tracking-[0.2em] uppercase opacity-70">{{ title }}</p>
                    </div>

                    <div class="mt-12 flex flex-col items-center gap-4">
                        <div class="flex gap-1.5">
                            <div class="w-1.5 h-1.5 bg-white rounded-full animate-bounce" style="animation-delay: 0s"></div>
                            <div class="w-1.5 h-1.5 bg-white rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                            <div class="w-1.5 h-1.5 bg-white rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                        </div>
                    </div>
                </div>

                <div class="absolute bottom-10 left-0 right-0 text-center">
                    <p class="text-[10px] text-white/30 tracking-widest uppercase font-bold">Secure Location Service</p>
                </div>
            </div>
        </transition>
    `
};
