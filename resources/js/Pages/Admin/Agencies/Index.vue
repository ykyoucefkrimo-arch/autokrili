<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    agencies: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    wilayas: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
});

const STATUSES = {
    pending: { label: 'En attente', classes: 'bg-amber-100 text-amber-800' },
    approved: { label: 'Approuvée', classes: 'bg-green-100 text-green-800' },
    rejected: { label: 'Rejetée', classes: 'bg-red-100 text-red-800' },
    suspended: { label: 'Suspendue', classes: 'bg-gray-200 text-gray-700' },
};

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const wilayaId = ref(props.filters.wilaya_id ?? '');

let timer = null;
watch([search, status, wilayaId], () => {
    // La frappe est temporisée : sans cela chaque lettre déclencherait une requête.
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.agencies.index'), {
            search: search.value || undefined,
            status: status.value || undefined,
            wilaya_id: wilayaId.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
});

// Le motif est obligatoire côté serveur ; la fenêtre le rend obligatoire ici
// aussi, pour ne pas faire découvrir la règle par une erreur.
const moderation = ref(null);
const reasonForm = useForm({ reason: '' });

const openReason = (agency, action) => {
    reasonForm.reset();
    reasonForm.clearErrors();
    moderation.value = { agency, action };
};

const submitReason = () => {
    const { agency, action } = moderation.value;
    reasonForm.post(route(`admin.agencies.${action}`, agency.id), {
        preserveScroll: true,
        onSuccess: () => (moderation.value = null),
    });
};

const approve = (agency) => {
    router.post(route('admin.agencies.approve', agency.id), {}, { preserveScroll: true });
};

const reinstate = (agency) => {
    router.post(route('admin.agencies.reinstate', agency.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Agences" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Agences</h2>
                <span v-if="counts.pending" class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                    {{ counts.pending }} en attente
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">

                <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <input v-model="search" type="search" placeholder="Nom, gérant, registre de commerce…"
                        class="w-72 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <select v-model="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tous les statuts</option>
                        <option v-for="(s, key) in STATUSES" :key="key" :value="key">{{ s.label }}</option>
                    </select>
                    <select v-model="wilayaId" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Toutes les wilayas</option>
                        <option v-for="w in wilayas" :key="w.id" :value="w.id">{{ w.name_fr }}</option>
                    </select>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div v-if="!agencies.data.length" class="p-10 text-center">
                        <p class="font-medium text-gray-900">Aucune agence ne correspond</p>
                        <p class="mt-1 text-sm text-gray-600">
                            Les inscriptions apparaissent ici dès qu'une agence dépose son dossier.
                        </p>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Agence</th>
                                <th class="px-4 py-3">Wilaya</th>
                                <th class="px-4 py-3">Inscrite le</th>
                                <th class="px-4 py-3 text-center">Annonces</th>
                                <th class="px-4 py-3">Formule</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="agency in agencies.data" :key="agency.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img v-if="agency.logo_url" :src="agency.logo_url" alt=""
                                            class="h-9 w-9 rounded-full object-cover" />
                                        <div v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-xs font-semibold text-gray-600">
                                            {{ agency.commercial_name.slice(0, 2).toUpperCase() }}
                                        </div>
                                        <div>
                                            <Link :href="route('admin.agencies.show', agency.id)"
                                                class="font-medium text-gray-900 hover:underline">
                                                {{ agency.commercial_name }}
                                            </Link>
                                            <p class="text-xs text-gray-500">{{ agency.manager_name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ agency.wilaya }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ agency.created_at }}</td>
                                <td class="px-4 py-3 text-center text-gray-700">{{ agency.vehicles_count }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="agency.plan" class="rounded px-2 py-0.5 text-xs font-semibold text-white"
                                        :style="{ backgroundColor: agency.plan.badge_color || '#6b7280' }">
                                        {{ agency.plan.name }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="STATUSES[agency.status].classes">
                                        {{ STATUSES[agency.status].label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button v-if="agency.status === 'pending'" @click="approve(agency)"
                                        class="rounded bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700">
                                        Approuver
                                    </button>
                                    <button v-if="agency.status === 'pending'" @click="openReason(agency, 'reject')"
                                        class="ml-1 rounded bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-700">
                                        Rejeter
                                    </button>
                                    <button v-if="agency.status === 'approved'" @click="openReason(agency, 'suspend')"
                                        class="rounded bg-gray-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-gray-700">
                                        Suspendre
                                    </button>
                                    <button v-if="['suspended', 'rejected'].includes(agency.status)" @click="reinstate(agency)"
                                        class="rounded bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-700">
                                        Réactiver
                                    </button>
                                    <Link :href="route('admin.agencies.show', agency.id)"
                                        class="ml-1 rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        Voir
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="agencies.links.length > 3" class="flex flex-wrap gap-1">
                    <component v-for="link in agencies.links" :key="link.label"
                        :is="link.url ? Link : 'span'" :href="link.url"
                        class="rounded px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-100' : 'text-gray-400'"
                        v-html="link.label" />
                </div>
            </div>
        </div>

        <!-- Fenêtre de motif, partagée par le rejet et la suspension -->
        <div v-if="moderation" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="moderation = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">
                    {{ moderation.action === 'reject' ? 'Rejeter' : 'Suspendre' }}
                    « {{ moderation.agency.commercial_name }} »
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    Le motif est envoyé à l'agence par email. Soyez assez précis pour qu'elle
                    puisse corriger.
                </p>
                <textarea v-model="reasonForm.reason" rows="4" autofocus
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Ex. : le registre de commerce fourni est illisible."></textarea>
                <p v-if="reasonForm.errors.reason" class="mt-1 text-sm text-red-600">{{ reasonForm.errors.reason }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button @click="moderation = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="submitReason" :disabled="reasonForm.processing"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Confirmer
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
