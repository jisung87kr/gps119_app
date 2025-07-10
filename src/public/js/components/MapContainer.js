export default {
    name: 'MapContainer',
    props: {
        mapRef: {
            type: String,
            default: 'map'
        }
    },
    template: `
        <div 
            id="map"
            :ref="mapRef"
            class="w-full h-full"
        ></div>
    `
};