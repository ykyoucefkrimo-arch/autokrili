<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    vehicles: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    wilayas: { type: Array, default: () => [] },
    agencies: { type: Array, default: () => [] },
    reasons: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
});

const STATUSES = {
    pending: { label: 'En attente', classes: 'bg-amber-100 text-amber-800' },
    published: { label: 'En ligne', classes: 'bg-green-100 text-green-800' },
    rejected: { label: 'Refusée', classes: 'bg-red-100 text-red-800' },
    draft: { label: 'Brouillon', classes: 'bg-gray-200 text-gray-700' },
    archived: { label: 'Archivée', classes: 'bg-gray-100 text-gray-500' },
};

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'pending');
const wilayaId = ref(props.filters.wilaya_id ?? '');
const agencyId = ref(props.filters.agency_id ?? '');

let timer = null;
watch([search, status, wilayaId, agencyId], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.vehicles.index'), {
            search: search.value || undefined,
            status: status.value || undefined,
            wilaya_id: wilayaId.value || undefined,
            agency_id: agencyId.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
});

/* Sélection pour le traitement par lot. Une file de vingt annonces quasi
   identiques est le cas courant, pas l'exception. */
const selected = ref([]);
watch(() => props.vehicles.data, () => (selected.value = []));

const selectableIds = computed(() =>
    props.vehicles.data.filter((v) => v.status === 'pending').map((v) => v.id));
const allSelected = computed(() =>
    selectableIds.value.length > 0 && selected.value.length === selectableIds.value.length);

const toggleAll = () => {
    selected.value = allSelected.value ? [] : [...selectableIds.value];
};

// Le motif : liste prédéfinie plus champ libre, l'un ou l'autre suffit.
const rejecting = ref(null);
const rejectForm = useForm({ reason_code: '', reason_note: '' });

const openReject = (target) => {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejecting.value = target;
};

const confirmReject = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => (rejecting.value = null),
    };

    if (rejecting.value === 'bulk') {
        rejectForm.transform((data) => ({ ...data, ids: selected.value, action: 'reject' }))
            .post(route('admin.vehicles.bulk'), options);
    } else {
        rejectForm.post(route('admin.vehicles.reject', rejecting.value.id), options);
    }
};

const approve = (vehicle) =>
    router.post(route('admin.vehicles.approve', vehicle.id), {}, { preserveScroll: true });

const approveSelected = () =>
    router.post(route('admin.vehicles.bulk'),
        { ids: selected.value, action: 'approve' }, { preserveScroll: true });
</script>

<template>
    <Head title="Modération des annonces" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Annonces</h2>
                <span v-if="counts.pending" class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                    {{ counts.pending }} en attente
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <input v-model="search" type="search" placeholder="Marque ou modèle…"
                        class="w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <select v-model="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="(s, key) in STATUSES" :key="key" :value="key">{{ s.label }}</option>
                        <option value="all">Toutes</option>
                    </select>
                    <select v-model="wilayaId" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Toutes les wilayas</option>
                        <option v-for="w in wilayas" :key="w.id" :value="w.id">{{ w.name_fr }}</option>
                    </select>
                    <select v-model="agencyId" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Toutes les agences</option>
                        <option v-for="a in agencies" :key="a.id" :value="a.id">{{ a.commercial_name }}</option>
                    </select>
                </div>

                <div v-if="selected.length"
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                    <p class="text-sm font-medium text-indigo-900">{{ selected.length }} annonce(s) sélectionnée(s)</p>
                    <button @click="approveSelected"
                        class="rounded bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700">
                        Tout approuver
                    </button>
                    <button @click="openReject('bulk')"
                        class="rounded bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">
                        Tout rejeter
                    </button>
                    <button @click="selected = []" class="text-xs text-indigo-700 hover:underline">Annuler</button>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div v-if="!vehicles.data.length" class="p-10 text-center">
                        <p class="font-medium text-gray-900">Rien à modérer</p>
                        <p class="mt-1 text-sm text-gray-600">
                            La file est vide : les annonces soumises par les agences arrivent ici.
                        </p>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">
                                    <input v-if="selectableIds.length" type="checkbox" :checked="allSelected"
                                        @change="toggleAll"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </th>
                                <th class="px-4 py-3">Véhicule</th>
                                <th class="px-4 py-3">Agence</th>
                                <th class="px-4 py-3">Wilaya</th>
                                <th class="px-4 py-3">Tarif / jour</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="vehicle in vehicles.data" :key="vehicle.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <input v-if="vehicle.status === 'pending'" type="checkbox" :value="vehicle.id"
                                        v-model="selected"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img v-if="vehicle.cover_url" :src="vehicle.cover_url" alt=""
                                            class="h-10 w-14 rounded object-cover" />
                                        <div v-else class="flex h-10 w-14 items-center justify-center rounded bg-gray-100 text-[10px] text-gray-400">
                                            sans photo
                                        </div>
                                        <div>
                                            <Link :href="route('admin.vehicles.show', vehicle.id)"
                                                class="font-medium text-gray-900 hover:underline">
                                                {{ vehicle.title }}
                                            </Link>
                                            <p class="text-xs text-gray-500">Soumise le {{ vehicle.submitted_at }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ vehicle.agency }}
                                    <span v-if="vehicle.agency_is_trusted"
                                        class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">
                                        de confiance
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ vehicle.wilaya }}</td>
                                <td class="px-4 py-3 text-gray-700">
                                    <template v-if="vehicle.daily_price">
                                        {{ vehicle.daily_price.toLocaleString('fr-DZ') }} DA
                                    </template>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="STATUSES[vehicle.status].classes">
                                        {{ STATUSES[vehicle.status].label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <Link :href="route('admin.vehicles.show', vehicle.id)"
                                        class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        Prévisualiser
                                    </Link>
                                    <template v-if="vehicle.status === 'pending'">
                                        <button @click="approve(vehicle)"
                                            class="ml-1 rounded bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700">
                                            Approuver
                                        </button>
                                        <button @click="openReject(vehicle)"
                                            class="ml-1 rounded bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-700">
                                            Rejeter
                                        </button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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

        <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="rejecting = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">
                    {{ rejecting === 'bulk' ? `Rejeter ${selected.length} annonce(s)` : `Rejeter « ${rejecting.title} »` }}
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    Le motif part par email à l'agence, qui pourra corriger et soumettre à nouveau.
                </p>

                <div class="mt-3 space-y-2">
                    <label v-for="(label, code) in reasons" :key="code" class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" :value="code" v-model="rejectForm.reason_code"
                            class="border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        {{ label }}
                    </label>
                </div>
                <p v-if="rejectForm.errors.reason_code" class="mt-1 text-sm text-red-600">
                    {{ rejectForm.errors.reason_code }}
                </p>

                <textarea v-model="rejectForm.reason_note" rows="3"
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Précision facultative : ce qui doit être corrigé."></textarea>
                <p v-if="rejectForm.errors.reason_note" class="mt-1 text-sm text-red-600">
                    {{ rejectForm.errors.reason_note }}
                </p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="rejecting = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="confirmReject" :disabled="rejectForm.processing"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Confirmer le rejet
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
