<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    vehicles: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
    quota: { type: Object, required: true },
    canWrite: { type: Boolean, default: true },
});

const STATUSES = {
    draft: { label: 'Brouillon', classes: 'bg-gray-200 text-gray-700' },
    pending: { label: 'En modération', classes: 'bg-amber-100 text-amber-800' },
    published: { label: 'En ligne', classes: 'bg-green-100 text-green-800' },
    rejected: { label: 'À corriger', classes: 'bg-red-100 text-red-800' },
    archived: { label: 'Archivée', classes: 'bg-gray-100 text-gray-500' },
};

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

let timer = null;
watch([search, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('agency.vehicles.index'), {
            search: search.value || undefined,
            status: status.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
});

// « Illimité » plutôt qu'un grand nombre : le quota Platinium n'a pas de valeur
// à afficher, et une barre pleine à 99 % mentirait.
const unlimited = computed(() => props.quota.max_listings === null);
const usedRatio = computed(() =>
    unlimited.value ? 0 : Math.min(100, Math.round((props.quota.used_listings / props.quota.max_listings) * 100)),
);

const confirmingDelete = ref(null);

const submit = (vehicle) => router.post(route('agency.vehicles.submit', vehicle.id), {}, { preserveScroll: true });
const archive = (vehicle) => router.post(route('agency.vehicles.archive', vehicle.id), {}, { preserveScroll: true });
const destroy = () => {
    router.delete(route('agency.vehicles.destroy', confirmingDelete.value.id), {
        onFinish: () => (confirmingDelete.value = null),
    });
};
</script>

<template>
    <Head title="Mes véhicules" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Mes véhicules</h2>
                <Link v-if="canWrite && quota.can_add_listing" :href="route('agency.vehicles.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Nouvelle annonce
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <div v-if="!canWrite" class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    Votre compte est suspendu : vos annonces sont masquées du site public et cet
                    espace est en lecture seule.
                </div>

                <!-- Le quota est une information permanente, pas un message d'erreur :
                     l'agence doit savoir où elle en est avant de buter dessus. -->
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm text-gray-700">
                            <span class="font-semibold text-gray-900">{{ quota.used_listings }}</span>
                            annonce(s) active(s) sur
                            <span class="font-semibold text-gray-900">{{ unlimited ? 'un nombre illimité' : quota.max_listings }}</span>
                            — formule {{ quota.plan_name }}
                        </p>
                        <p class="text-xs text-gray-500">{{ quota.max_photos }} photo(s) par annonce</p>
                    </div>
                    <div v-if="!unlimited" class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full transition-all"
                            :class="usedRatio >= 100 ? 'bg-red-500' : usedRatio >= 80 ? 'bg-amber-500' : 'bg-indigo-500'"
                            :style="{ width: usedRatio + '%' }" />
                    </div>
                    <p v-if="!quota.can_add_listing" class="mt-2 text-sm text-amber-800">
                        {{ quota.listing_message }}
                        Vous pouvez aussi archiver une annonce pour libérer une place.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <input v-model="search" type="search" placeholder="Marque ou modèle…"
                        class="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <select v-model="status"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tous les statuts</option>
                        <option v-for="(s, key) in STATUSES" :key="key" :value="key">
                            {{ s.label }}<template v-if="counts[key]"> ({{ counts[key] }})</template>
                        </option>
                    </select>
                </div>

                <div v-if="!vehicles.data.length" class="rounded-lg bg-white p-10 text-center shadow-sm">
                    <p class="font-medium text-gray-900">Aucune annonce pour le moment</p>
                    <p class="mx-auto mt-1 max-w-md text-sm text-gray-600">
                        Créez votre première annonce : décrivez le véhicule, fixez vos tarifs,
                        ajoutez des photos, puis soumettez-la à la modération.
                    </p>
                    <Link v-if="canWrite && quota.can_add_listing" :href="route('agency.vehicles.create')"
                        class="mt-4 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Nouvelle annonce
                    </Link>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="vehicle in vehicles.data" :key="vehicle.id"
                        class="flex flex-col overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="relative aspect-[4/3] bg-gray-100">
                            <img v-if="vehicle.cover_url" :src="vehicle.cover_url" alt=""
                                class="h-full w-full object-cover" />
                            <div v-else class="flex h-full items-center justify-center text-sm text-gray-400">
                                Aucune photo
                            </div>
                            <span class="absolute left-2 top-2 rounded-full px-2 py-1 text-xs font-medium"
                                :class="STATUSES[vehicle.status].classes">
                                {{ STATUSES[vehicle.status].label }}
                            </span>
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <h3 class="font-semibold text-gray-900">{{ vehicle.title }}</h3>
                            <p class="text-sm text-gray-500">{{ vehicle.commune }}</p>
                            <p class="mt-1 text-sm font-medium text-gray-900">
                                <template v-if="vehicle.daily_price">
                                    {{ vehicle.daily_price.toLocaleString('fr-DZ') }} DA / jour
                                </template>
                                <span v-else class="text-amber-700">Tarif à renseigner</span>
                            </p>

                            <p v-if="vehicle.status === 'rejected' && vehicle.rejection_reason"
                                class="mt-2 rounded border border-red-200 bg-red-50 p-2 text-xs text-red-800">
                                {{ vehicle.rejection_reason }}
                            </p>

                            <div class="mt-2 flex gap-3 text-xs text-gray-500">
                                <span>{{ vehicle.photos_count }} photo(s)</span>
                                <span>{{ vehicle.views_count }} vue(s)</span>
                                <span class="ml-auto">{{ vehicle.updated_at }}</span>
                            </div>

                            <div v-if="canWrite" class="mt-4 flex flex-wrap gap-1 border-t border-gray-100 pt-3">
                                <Link :href="route('agency.vehicles.edit', vehicle.id)"
                                    class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    Modifier
                                </Link>
                                <button v-if="['draft', 'rejected', 'archived'].includes(vehicle.status)"
                                    @click="submit(vehicle)"
                                    class="rounded bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-700">
                                    Soumettre
                                </button>
                                <button v-if="['published', 'pending'].includes(vehicle.status)" @click="archive(vehicle)"
                                    class="rounded bg-gray-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-gray-700">
                                    Retirer
                                </button>
                                <button @click="confirmingDelete = vehicle"
                                    class="ml-auto rounded px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="vehicles.links.length > 3" class="flex flex-wrap gap-1">
                    <component v-for="link in vehicles.links" :key="link.label"
                        :is="link.url ? Link : 'span'" :href="link.url"
                        class="rounded px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-100' : 'text-gray-400'"
                        v-html="link.label" />
                </div>
            </div>
        </div>

        <div v-if="confirmingDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="confirmingDelete = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Supprimer « {{ confirmingDelete.title }} » ?</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Les photos sont effacées définitivement. Si vous voulez seulement retirer
                    l'annonce du site, archivez-la : elle reste modifiable et republiable.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button @click="confirmingDelete = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="destroy"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
