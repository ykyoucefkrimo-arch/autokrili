<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LineChart from '@/Components/LineChart.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    plan: { type: Object, required: true },
    days: { type: Number, default: 30 },
});

const number = (v) => Number(v ?? 0).toLocaleString('fr-DZ');

const LEVELS = { basic: 0, advanced: 1, premium: 2 };

/* Un bloc est verrouillé quand la formule n'atteint pas son niveau. Il reste
   affiché mais flouté, avec l'appel à monter en gamme (§8) : c'est un levier de
   conversion, pas une frustration — à condition que le chiffre soit réel
   derrière le flou, et pas un décor. */
const unlocked = (level) => LEVELS[props.plan.stats_level] >= LEVELS[level];

const changeRange = (value) => router.get(route('agency.stats'), { days: value }, { preserveState: false });

const viewSeries = computed(() => ({
    labels: props.stats.daily.map((d) => d.label),
    datasets: [
        {
            label: 'Vues',
            data: props.stats.daily.map((d) => d.views),
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79, 70, 229, .12)',
            fill: true,
            tension: 0.3,
            pointRadius: 0,
            pointHoverRadius: 4,
        },
        {
            label: 'Demandes',
            data: props.stats.daily.map((d) => d.bookings),
            borderColor: '#059669',
            backgroundColor: 'rgba(5, 150, 105, .12)',
            fill: true,
            tension: 0.3,
            pointRadius: 0,
            pointHoverRadius: 4,
        },
    ],
}));

const weekdaySeries = computed(() => ({
    labels: props.stats.weekdays.map((d) => d.label),
    datasets: [{
        label: 'Demandes reçues',
        data: props.stats.weekdays.map((d) => d.bookings),
        backgroundColor: '#4f46e5',
        borderRadius: 4,
    }],
}));

const hasAnyView = computed(() => props.stats.totals.views > 0);
</script>

<template>
    <Head title="Statistiques" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Statistiques</h2>
                <div class="flex gap-1 rounded-md bg-white p-1 shadow-sm">
                    <button v-for="range in [7, 30, 90]" :key="range" @click="changeRange(range)"
                        class="rounded px-3 py-1 text-sm font-medium"
                        :class="days === range ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'">
                        {{ range }} j
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Sans une seule vue, des graphiques plats ne disent rien :
                     mieux vaut expliquer pourquoi. -->
                <div v-if="!hasAnyView" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    Aucune vue enregistrée sur la période. Les compteurs démarrent à la première
                    visite d’une de vos annonces publiées.
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Vues</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number(stats.totals.views) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Contacts</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number(stats.totals.contact_clicks) }}</p>
                        <p class="text-xs text-gray-500">appels et WhatsApp</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Demandes</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number(stats.totals.bookings) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Confirmées</p>
                        <p class="mt-1 text-2xl font-semibold text-green-700">{{ number(stats.totals.confirmed) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Revenus estimés</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number(stats.totals.revenue) }}</p>
                        <p class="text-xs text-gray-500">DA, réservations retenues</p>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Évolution sur {{ days }} jours</h3>
                    <div class="mt-4">
                        <LineChart :labels="viewSeries.labels" :datasets="viewSeries.datasets" />
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Conversion : formules Gold et Platinium -->
                    <div class="relative overflow-hidden rounded-lg bg-white p-6 shadow-sm">
                        <div :class="unlocked('advanced') ? '' : 'pointer-events-none select-none blur-sm'">
                            <h3 class="font-semibold text-gray-900">Taux de conversion</h3>
                            <dl class="mt-4 space-y-3">
                                <div class="flex items-baseline justify-between">
                                    <dt class="text-sm text-gray-600">Vue → demande</dt>
                                    <dd class="text-xl font-semibold text-gray-900">{{ stats.conversion.view_to_booking }} %</dd>
                                </div>
                                <div class="flex items-baseline justify-between">
                                    <dt class="text-sm text-gray-600">Vue → contact</dt>
                                    <dd class="text-xl font-semibold text-gray-900">{{ stats.conversion.view_to_contact }} %</dd>
                                </div>
                                <div class="flex items-baseline justify-between">
                                    <dt class="text-sm text-gray-600">Demandes acceptées</dt>
                                    <dd class="text-xl font-semibold text-gray-900">{{ stats.conversion.acceptance }} %</dd>
                                </div>
                            </dl>
                        </div>

                        <div v-if="!unlocked('advanced')"
                            class="absolute inset-0 flex flex-col items-center justify-center bg-white/60 p-6 text-center">
                            <p class="font-semibold text-gray-900">Réservé aux formules Gold et Platinium</p>
                            <p class="mt-1 max-w-xs text-sm text-gray-600">
                                Savoir combien de vues deviennent des demandes vous dit si le
                                problème est le prix ou les photos.
                            </p>
                            <Link :href="route('agency.subscription')"
                                class="mt-3 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Voir les formules
                            </Link>
                        </div>
                    </div>

                    <!-- Périodes de forte demande -->
                    <div class="relative overflow-hidden rounded-lg bg-white p-6 shadow-sm">
                        <div :class="unlocked('advanced') ? '' : 'pointer-events-none select-none blur-sm'">
                            <h3 class="font-semibold text-gray-900">Jours de forte demande</h3>
                            <div class="mt-4">
                                <LineChart type="bar" :labels="weekdaySeries.labels"
                                    :datasets="weekdaySeries.datasets" height="200px" />
                            </div>
                        </div>

                        <div v-if="!unlocked('advanced')"
                            class="absolute inset-0 flex flex-col items-center justify-center bg-white/60 p-6 text-center">
                            <p class="font-semibold text-gray-900">Réservé aux formules Gold et Platinium</p>
                            <Link :href="route('agency.subscription')"
                                class="mt-3 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Voir les formules
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Véhicules les plus vus : accessible à toutes les formules -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 p-6 pb-4">
                        <h3 class="font-semibold text-gray-900">Vos véhicules les plus vus</h3>
                    </div>

                    <div v-if="!stats.topVehicles.length" class="p-8 text-center text-sm text-gray-600">
                        Aucune vue enregistrée pour l’instant.
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Véhicule</th>
                                <th class="px-6 py-3 text-right">Vues</th>
                                <th class="px-6 py-3 text-right">Demandes</th>
                                <th class="px-6 py-3 text-right">Conversion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="vehicle in stats.topVehicles" :key="vehicle.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3">
                                    <Link :href="route('agency.vehicles.edit', vehicle.id)"
                                        class="font-medium text-gray-900 hover:underline">
                                        {{ vehicle.title }}
                                    </Link>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-700">{{ number(vehicle.views) }}</td>
                                <td class="px-6 py-3 text-right text-gray-700">{{ vehicle.bookings }}</td>
                                <td class="px-6 py-3 text-right"
                                    :class="vehicle.rate > 0 ? 'text-gray-900' : 'text-gray-400'">
                                    {{ vehicle.rate }} %
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Comparaison wilaya : Platinium seulement -->
                <div class="relative overflow-hidden rounded-lg bg-white p-6 shadow-sm">
                    <div :class="unlocked('premium') ? '' : 'pointer-events-none select-none blur-sm'">
                        <h3 class="font-semibold text-gray-900">Comparaison avec votre wilaya</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Vues par annonce, pour ne pas avantager les grosses flottes.
                        </p>
                        <div v-if="stats.wilayaComparison" class="mt-4 flex flex-wrap gap-8">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Vous</p>
                                <p class="text-2xl font-semibold text-indigo-600">
                                    {{ stats.wilayaComparison.my_views_per_listing }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">Moyenne de la wilaya</p>
                                <p class="text-2xl font-semibold text-gray-900">
                                    {{ stats.wilayaComparison.average_views_per_listing }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    sur {{ stats.wilayaComparison.agencies }} agence(s)
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-gray-500">
                            Comparaison indisponible sur cette période.
                        </p>
                    </div>

                    <div v-if="!unlocked('premium')"
                        class="absolute inset-0 flex flex-col items-center justify-center bg-white/60 p-6 text-center">
                        <p class="font-semibold text-gray-900">Réservé à la formule Platinium</p>
                        <p class="mt-1 max-w-sm text-sm text-gray-600">
                            Vous situer face aux autres agences de votre wilaya, à taille de flotte
                            comparable.
                        </p>
                        <Link :href="route('agency.subscription')"
                            class="mt-3 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Passer en Platinium
                        </Link>
                    </div>
                </div>

                <p class="text-xs text-gray-500">
                    Formule {{ plan.name }} — statistiques
                    {{ { basic: 'basiques', advanced: 'avancées', premium: 'complètes' }[plan.stats_level] }}.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
