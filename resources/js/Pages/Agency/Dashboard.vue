<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    agency: { type: Object, required: true },
    plan: { type: Object, default: null },
    listings: { type: Object, required: true },
});
</script>

<template>
    <Head title="Tableau de bord agence" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ agency.commercial_name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="agency.is_suspended"
                    class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    Votre compte est suspendu : ce tableau de bord est en lecture seule et vos
                    annonces ne sont pas visibles du public.
                </div>

                <!-- Ce qui demande une action passe en premier : une annonce
                     refusée qui dort est une annonce qui ne rapporte rien. -->
                <div v-if="listings.rejected || listings.draft"
                    class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                    <span v-if="listings.rejected">
                        {{ listings.rejected }} annonce(s) refusée(s) à corriger.
                    </span>
                    <span v-if="listings.draft">
                        {{ listings.draft }} brouillon(s) jamais soumis.
                    </span>
                    <Link :href="route('agency.vehicles.index', { status: listings.rejected ? 'rejected' : 'draft' })"
                        class="ml-1 font-semibold underline">
                        Voir
                    </Link>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Formule</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ plan?.name ?? '—' }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ plan?.max_photos ?? '—' }} photo(s) par annonce</p>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Annonces actives</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ listings.used }}
                            <span class="text-base font-normal text-gray-500">
                                / {{ plan?.max_listings ?? '∞' }}
                            </span>
                        </p>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">En ligne</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ listings.published }}</p>
                        <p v-if="listings.pending" class="mt-1 text-xs text-amber-700">
                            {{ listings.pending }} en modération
                        </p>
                    </div>
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Vues cumulées</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ listings.views.toLocaleString('fr-DZ') }}</p>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Vos véhicules</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Gérez votre flotte : caractéristiques, tarifs dégressifs, photos et
                        soumission à la modération.
                        <template v-if="agency.commune">
                            Votre agence est basée à {{ agency.commune }}, {{ agency.wilaya }}.
                        </template>
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link :href="route('agency.vehicles.index')"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Mes véhicules
                        </Link>
                        <Link v-if="!agency.is_suspended && listings.can_add" :href="route('agency.vehicles.create')"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Nouvelle annonce
                        </Link>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Réservations</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Le calendrier et le suivi des réservations arrivent à la prochaine étape
                        du projet.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
