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
        <div 
            v-show="show"
            class="bg-blue-800 fixed left-0 top-0 right-0 bottom-0 z-[9999] flex items-center justify-center"
            :class="{
                'transition-opacity duration-500 ease-out': true,
                'opacity-0': !show,
                'opacity-100': show
            }"
        >
            <div class="w-full text-center text-white px-8">
                <div class="mb-6">
                    <div class="w-20 h-20 mx-auto mb-4 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>
                <div class="text-3xl md:text-4xl font-bold mb-2 tracking-tight">GPS119</div>
                <div class="text-xl md:text-2xl font-light mb-4 opacity-90">(주)바른인명구조단</div>
                <div class="text-sm md:text-base opacity-75">{{ title }}</div>
                <div class="mt-8">
                    <div class="w-8 h-8 mx-auto border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                </div>
            </div>
        </div>
    `
};