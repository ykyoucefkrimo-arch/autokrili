<script setup>
import {
    CategoryScale, Chart, Filler, Legend, LinearScale, LineController,
    LineElement, PointElement, Tooltip, BarController, BarElement,
} from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Un graphique Chart.js (§8), enregistré à la pièce plutôt qu'avec
 * `Chart.register(...registerables)` : la version complète embarque tous les
 * types, y compris ceux que le projet n'affiche pas.
 */
Chart.register(
    LineController, LineElement, PointElement, Filler,
    BarController, BarElement,
    CategoryScale, LinearScale, Tooltip, Legend,
);

const props = defineProps({
    type: { type: String, default: 'line' },
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true },
    height: { type: String, default: '260px' },
    stepSize: { type: Number, default: null },
});

const canvas = ref(null);
let chart = null;

const build = () => {
    if (chart) chart.destroy();

    chart = new Chart(canvas.value, {
        type: props.type,
        data: { labels: props.labels, datasets: props.datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: props.datasets.length > 1, position: 'bottom' },
                tooltip: { padding: 10, boxPadding: 4 },
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 16 } },
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    // Des vues se comptent en entiers : une graduation à 2,5
                    // n'a pas de sens.
                    ticks: { precision: 0, stepSize: props.stepSize ?? undefined },
                },
            },
        },
    });
};

onMounted(build);
watch(() => [props.labels, props.datasets], build, { deep: true });
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div :style="{ height }"><canvas ref="canvas" /></div>
</template>
