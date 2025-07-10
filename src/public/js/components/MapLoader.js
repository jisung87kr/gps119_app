export default {
    name: 'MapLoader',
    props: {
        apiKey: {
            type: String,
            default: '509c2656c00fa9af4782197a888763f6'
        }
    },
    data() {
        return {
            scriptsLoaded: false
        }
    },
    mounted() {
        this.loadMapScripts();
    },
    methods: {
        loadMapScripts() {
            if (this.scriptsLoaded) return;
            
            // Load Kakao Map SDK
            const mapScript = document.createElement('script');
            mapScript.src = `//dapi.kakao.com/v2/maps/sdk.js?appkey=${this.apiKey}&libraries=services,clusterer,drawing&autoload=false`;
            mapScript.onload = () => {
                // Wait for kakao maps to be available
                const checkKakao = () => {
                    if (window.kakao && window.kakao.maps) {
                        kakao.maps.load(() => {
                            // Load Postcode script
                            const postcodeScript = document.createElement('script');
                            postcodeScript.src = '//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
                            postcodeScript.onload = () => {
                                this.scriptsLoaded = true;
                                this.$emit('scripts-loaded');
                            };
                            document.head.appendChild(postcodeScript);
                        });
                    } else {
                        setTimeout(checkKakao, 100);
                    }
                };
                checkKakao();
            };
            document.head.appendChild(mapScript);
        }
    },
    template: `<div></div>`
};