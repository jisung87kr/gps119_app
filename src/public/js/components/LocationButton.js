export default {
    name: 'LocationButton',
    props: {
        loading: {
            type: Boolean,
            default: false
        }
    },
    methods: {
        handleClick() {
            this.$emit('get-location');
        }
    },
    template: `
        <div
            @click="handleClick"
            class="bg-blue-600 hover:bg-blue-700 absolute right-5 top-[-60px] w-10 h-10 rounded-full flex items-center justify-center cursor-pointer shadow-xl transform transition-all duration-300 hover:scale-110 active:scale-95 z-[100]"
        >
            <div class="absolute inset-0 rounded-full bg-blue-400 animate-ping opacity-20" v-show="!loading"></div>

            <svg
                v-show="!loading"
                xmlns="http://www.w3.org/2000/svg"
                class="icon icon-tabler icon-tabler-focus-2 relative z-10"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                stroke-width="2.5"
                stroke="#ffffff"
                fill="none"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <circle cx="12" cy="12" r=".5" fill="currentColor" />
                <path d="M12 12m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                <path d="M12 3l0 2" />
                <path d="M3 12l2 0" />
                <path d="M12 19l0 2" />
                <path d="M19 12l2 0" />
            </svg>

            <svg
                v-show="loading"
                xmlns="http://www.w3.org/2000/svg"
                class="icon icon-tabler icon-tabler-loader-2 animate-spin relative z-10"
                width="28"
                height="28"
                viewBox="0 0 24 24"
                stroke-width="2.5"
                stroke="#ffffff"
                fill="none"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M12 3a9 9 0 1 0 9 9" />
            </svg>
        </div>
    `
};
