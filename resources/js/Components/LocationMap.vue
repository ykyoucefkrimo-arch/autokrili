<script setup>
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Carte Leaflet sur fond OpenStreetMap : aucune clé d'API, comme demandé au
 * §7.1. En mode `editable`, le marqueur se déplace au clic et remonte sa
 * position — c'est ainsi qu'une agence indique où elle se trouve.
 */
const props = defineProps({
    latitude: { type: Number, default: null },
    longitude: { type: Number, default: null },
    label: { type: String, default: '' },
    editable: { type: Boolean, default: false },
    zoom: { type: Number, default: 14 },
    height: { type: String, default: '320px' },
});

const emit = defineEmits(['moved']);

const container = ref(null);
let map = null;
let marker = null;

/* Leaflet va chercher ses icônes par une URL relative à la feuille de style,
   ce que le hachage des noms de fichiers par Vite casse. Les importer
   explicitement est la seule façon fiable de garder un marqueur visible. */
const icon = L.icon({
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});

const place = (lat, lng) => {
    if (!map) return;

    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { icon, draggable: props.editable }).addTo(map);
        if (props.label) marker.bindPopup(props.label);
        if (props.editable) {
            marker.on('dragend', () => {
                const { lat: a, lng: b } = marker.getLatLng();
                emit('moved', { latitude: +a.toFixed(7), longitude: +b.toFixed(7) });
            });
        }
    }
};

onMounted(() => {
    const lat = props.latitude ?? 36.7538;
    const lng = props.longitude ?? 3.0588;

    map = L.map(container.value, { scrollWheelZoom: false }).setView([lat, lng], props.zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    if (props.latitude !== null && props.longitude !== null) {
        place(props.latitude, props.longitude);
    }

    if (props.editable) {
        map.on('click', (event) => {
            const { lat: a, lng: b } = event.latlng;
            place(a, b);
            emit('moved', { latitude: +a.toFixed(7), longitude: +b.toFixed(7) });
        });
    }
});

watch(() => [props.latitude, props.longitude], ([lat, lng]) => {
    if (lat !== null && lng !== null) {
        place(lat, lng);
        map?.setView([lat, lng]);
    }
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <div ref="container" :style="{ height }" class="z-0 w-full rounded-lg" />
</template>
