export default {
    name: 'LocationInfo',
    props: {
        latitude: {
            type: [String, Number],
            default: ''
        },
        longitude: {
            type: [String, Number],
            default: ''
        },
        address: {
            type: String,
            default: ''
        },
        title: {
            type: String,
            default: '위치 정보'
        },
        bgColor: {
            type: String,
            default: 'gray'
        },
        icon: {
            type: String,
            default: 'location'
        }
    },
    computed: {
        bgClasses() {
            const colorMap = {
                'red': 'bg-red-50/80 border-red-100',
                'blue': 'bg-blue-50/80 border-blue-100',
                'gray': 'bg-gray-50/80 border-gray-100'
            };
            return colorMap[this.bgColor] || colorMap.gray;
        },
        iconBgClass() {
            const colorMap = {
                'red': 'bg-red-100',
                'blue': 'bg-blue-100',
                'gray': 'bg-gray-100'
            };
            return colorMap[this.bgColor] || colorMap.gray;
        },
        textClasses() {
            const colorMap = {
                'red': 'text-red-600',
                'blue': 'text-blue-600',
                'gray': 'text-gray-600'
            };
            return colorMap[this.bgColor] || colorMap.gray;
        },
        addressTextClasses() {
            const colorMap = {
                'red': 'text-red-900',
                'blue': 'text-blue-900',
                'gray': 'text-gray-900'
            };
            return colorMap[this.bgColor] || colorMap.gray;
        },
        coordTextClasses() {
            const colorMap = {
                'red': 'text-red-700/70',
                'blue': 'text-blue-700/70',
                'gray': 'text-gray-700/70'
            };
            return colorMap[this.bgColor] || colorMap.gray;
        },
        iconColor() {
            const colorMap = {
                'red': 'text-red-600',
                'blue': 'text-blue-600',
                'gray': 'text-gray-600'
            };
            return colorMap[this.bgColor] || colorMap.gray;
        }
    },
    template: `
        <div class="rounded-2xl p-4 mb-3 border shadow-sm transition-all duration-300" :class="bgClasses">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg" :class="iconBgClass">
                        <svg 
                            v-if="icon === 'location'"
                            xmlns="http://www.w3.org/2000/svg" 
                            class="w-4 h-4"
                            :class="iconColor"
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke="currentColor" 
                            stroke-width="2.5"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg 
                            v-else-if="icon === 'clock'"
                            xmlns="http://www.w3.org/2000/svg" 
                            class="w-4 h-4"
                            :class="iconColor"
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke="currentColor" 
                            stroke-width="2.5"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xs font-bold tracking-tight uppercase" :class="textClasses">{{ title }}</h3>
                </div>
            </div>
            
            <div v-if="address" class="text-[0.925rem] font-semibold mb-2 leading-snug break-all" :class="addressTextClasses">
                {{ address }}
            </div>
            <div v-else class="text-sm italic mb-2 opacity-50" :class="addressTextClasses">
                위치 정보를 가져오는 중...
            </div>

            <div class="flex gap-3 text-[10px] font-medium" :class="coordTextClasses">
                <div class="flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/50 border border-black/5">
                    <span class="opacity-60">LAT</span>
                    <span class="font-mono">{{ latitude }}</span>
                </div>
                <div class="flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/50 border border-black/5">
                    <span class="opacity-60">LNG</span>
                    <span class="font-mono">{{ longitude }}</span>
                </div>
            </div>
        </div>
    `
};
