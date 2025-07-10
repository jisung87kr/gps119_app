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
                'red': 'bg-red-50 border-red-200',
                'blue': 'bg-blue-50 border-blue-200',
                'gray': 'bg-gray-50 border-gray-200'
            };
            return colorMap[this.bgColor] || colorMap.gray;
        },
        textClasses() {
            const colorMap = {
                'red': 'text-red-800',
                'blue': 'text-blue-800',
                'gray': 'text-gray-800'
            };
            return colorMap[this.bgColor] || colorMap.gray;
        },
        addressTextClasses() {
            const colorMap = {
                'red': 'text-red-700',
                'blue': 'text-blue-700',
                'gray': 'text-gray-700'
            };
            return colorMap[this.bgColor] || colorMap.gray;
        },
        coordTextClasses() {
            const colorMap = {
                'red': 'text-red-600',
                'blue': 'text-blue-600',
                'gray': 'text-gray-600'
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
        <div class="rounded-xl p-4 mb-4 border" :class="bgClasses">
            <div class="flex items-center gap-2 mb-2">
                <svg 
                    v-if="icon === 'location'"
                    xmlns="http://www.w3.org/2000/svg" 
                    class="w-5 h-5"
                    :class="iconColor"
                    fill="none" 
                    viewBox="0 0 24 24" 
                    stroke="currentColor" 
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <svg 
                    v-else-if="icon === 'clock'"
                    xmlns="http://www.w3.org/2000/svg" 
                    class="w-5 h-5"
                    :class="iconColor"
                    fill="none" 
                    viewBox="0 0 24 24" 
                    stroke="currentColor" 
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-sm font-bold" :class="textClasses">{{ title }}</h3>
            </div>
            <div v-if="address" class="text-sm mb-2" :class="addressTextClasses">{{ address }}</div>
            <div class="flex gap-4 text-xs" :class="coordTextClasses">
                <div class="flex items-center gap-1">
                    <span class="font-medium">위도:</span>
                    <span class="font-mono">{{ latitude }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="font-medium">경도:</span>
                    <span class="font-mono">{{ longitude }}</span>
                </div>
            </div>
        </div>
    `
};