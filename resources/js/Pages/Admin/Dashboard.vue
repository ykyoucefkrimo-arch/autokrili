<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    stats: { type: Object, required: true },
    byPlan: { type: Array, default: () => [] },
    latest: { type: Array, default: () => [] },
});

const STATUS_LABELS = {
    pending: 'En attente',
    approved: 'Approuvée',
    rejected: 'Rejetée',
    suspended: 'Suspendue',
};
</script>

<template>
    <Head title="Administration" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Administration</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">

                <!-- Les deux files d'attente en premier : c'est la raison d'ouvrir cet écran. -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <Link :href="route('admin.agencies.index', { status: 'pending' })"
                        class="block rounded-lg p-6 shadow-sm transition"
                        :class="stats.pending ? 'bg-amber-50 ring-1 ring-amber-300 hover:bg-amber-100' : 'bg-white hover:bg-gray-50'">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Inscriptions à traiter</p>
                        <p class="mt-1 text-3xl font-semibold" :class="stats.pending ? 'text-amber-700' : 'text-gray-900'">
                            {{ stats.pending }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ stats.pending ? 'Des agences attendent une décision.' : 'Rien en attente.' }}
                        </p>
                    </Link>

                    <Link :href="route('admin.vehicles.index', { status: 'pending' })"
                        class="block rounded-lg p-6 shadow-sm transition"
                        :class="stats.vehicles_pending ? 'bg-amber-50 ring-1 ring-amber-300 hover:bg-amber-100' : 'bg-white hover:bg-gray-50'">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Annonces à modérer</p>
                        <p class="mt-1 text-3xl font-semibold" :class="stats.vehicles_pending ? 'text-amber-700' : 'text-gray-900'">
                            {{ stats.vehicles_pending }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ stats.vehicles_published }} annonce(s) en ligne.
                        </p>
                    </Link>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Approuvées</p>
                        <p class="mt-1 text-2xl font-semibold text-green-700">{{ stats.approved }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rejetées</p>
                        <p class="mt-1 text-2xl font-semibold text-red-700">{{ stats.rejected }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Suspendues</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-700">{{ stats.suspended }}</p>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Répartition par formule</h3>
                        <ul class="mt-4 space-y-3">
                            <li v-for="plan in byPlan" :key="plan.id" class="flex items-center justify-between text-sm">
                                <span class="rounded px-2 py-0.5 text-xs font-semibold text-white"
                                    :style="{ backgroundColor: plan.badge_color || '#6b7280' }">{{ plan.name }}</span>
                                <span class="font-medium text-gray-900">{{ plan.agencies_count }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Dernières inscriptions</h3>
                        <ul class="mt-4 divide-y divide-gray-100 text-sm">
                            <li v-for="a in latest" :key="a.id" class="flex items-center justify-between py-2">
                                <Link :href="route('admin.agencies.show', a.id)" class="hover:underline">
                                    <span class="font-medium text-gray-900">{{ a.commercial_name }}</span>
                                    <span class="ml-2 text-xs text-gray-500">{{ a.wilaya }}</span>
                                </Link>
                                <span class="text-xs text-gray-500">{{ STATUS_LABELS[a.status] }}</span>
                            </li>
                            <li v-if="!latest.length" class="py-3 text-sm text-gray-500">Aucune agence inscrite.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
