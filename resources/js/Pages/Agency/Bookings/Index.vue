<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    bookings: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
    canWrite: { type: Boolean, default: true },
});

const STATUSES = {
    pending: { label: 'À traiter', classes: 'bg-amber-100 text-amber-800' },
    confirmed: { label: 'Confirmée', classes: 'bg-green-100 text-green-800' },
    in_progress: { label: 'En cours', classes: 'bg-indigo-100 text-indigo-800' },
    completed: { label: 'Terminée', classes: 'bg-gray-200 text-gray-700' },
    cancelled: { label: 'Annulée', classes: 'bg-red-100 text-red-800' },
    expired: { label: 'Expirée', classes: 'bg-gray-100 text-gray-500' },
};

const price = (v) => Number(v ?? 0).toLocaleString('fr-DZ');

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

let timer = null;
watch([search, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('agency.bookings.index'), {
            search: search.value || undefined,
            status: status.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
});

const refusing = ref(null);
const refuseForm = useForm({ reason: '' });

const openRefuse = (booking) => {
    refuseForm.reset();
    refuseForm.clearErrors();
    refusing.value = booking;
};

const confirmRefuse = () => {
    refuseForm.post(route('agency.bookings.refuse', refusing.value.id), {
        preserveScroll: true,
        onSuccess: () => (refusing.value = null),
    });
};

const act = (booking, action) =>
    router.post(route(`agency.bookings.${action}`, booking.id), {}, { preserveScroll: true });
</script>

<template>
    <Head title="Réservations" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Réservations</h2>
                <div class="flex items-center gap-3">
                    <span v-if="counts.pending"
                        class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                        {{ counts.pending }} à traiter
                    </span>
                    <a :href="route('agency.bookings.export')"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Export CSV
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.errors?.booking"
                    class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    {{ $page.props.errors.booking }}
                </div>

                <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <input v-model="search" type="search" placeholder="Référence, nom ou téléphone…"
                        class="w-72 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <select v-model="status"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tous les statuts</option>
                        <option v-for="(s, key) in STATUSES" :key="key" :value="key">
                            {{ s.label }}<template v-if="counts[key]"> ({{ counts[key] }})</template>
                        </option>
                    </select>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div v-if="!bookings.data.length" class="p-10 text-center">
                        <p class="font-medium text-gray-900">Aucune réservation</p>
                        <p class="mt-1 text-sm text-gray-600">
                            Les demandes de vos clients arrivent ici. Vous avez 24 heures pour y
                            répondre, sinon les dates sont libérées automatiquement.
                        </p>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Référence</th>
                                <th class="px-4 py-3">Véhicule</th>
                                <th class="px-4 py-3">Client</th>
                                <th class="px-4 py-3">Dates</th>
                                <th class="px-4 py-3">Total</th>
                                <th class="px-4 py-3">Statut</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="booking in bookings.data" :key="booking.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <Link :href="route('agency.bookings.show', booking.id)"
                                        class="font-medium text-gray-900 hover:underline">
                                        {{ booking.reference }}
                                    </Link>
                                    <!-- Le compte à rebours est l'information qui décide de
                                         l'ordre dans lequel l'agence traite sa file. -->
                                    <p v-if="booking.expires_in_hours !== null"
                                        class="text-xs"
                                        :class="booking.expires_in_hours <= 6 ? 'text-red-600' : 'text-gray-500'">
                                        {{ booking.expires_in_hours > 0
                                            ? `expire dans ${booking.expires_in_hours} h`
                                            : 'expire d’un instant à l’autre' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ booking.vehicle }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-gray-900">{{ booking.client_name }}</p>
                                    <a :href="`tel:${booking.client_phone}`" class="text-xs text-gray-500 hover:underline">
                                        {{ booking.client_phone }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ booking.start_date }} → {{ booking.end_date }}
                                    <span class="block text-xs text-gray-500">{{ booking.total_days }} jour(s)</span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    {{ price(booking.total_price_dzd) }} DA
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium"
                                        :class="STATUSES[booking.status].classes">
                                        {{ STATUSES[booking.status].label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <template v-if="canWrite">
                                        <button v-if="booking.status === 'pending'" @click="act(booking, 'confirm')"
                                            class="rounded bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700">
                                            Accepter
                                        </button>
                                        <button v-if="booking.status === 'pending'" @click="openRefuse(booking)"
                                            class="ml-1 rounded bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-700">
                                            Refuser
                                        </button>
                                        <button v-if="booking.status === 'confirmed'" @click="act(booking, 'start')"
                                            class="rounded bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-700">
                                            Départ effectué
                                        </button>
                                        <button v-if="booking.status === 'in_progress'" @click="act(booking, 'complete')"
                                            class="rounded bg-gray-700 px-2.5 py-1 text-xs font-medium text-white hover:bg-gray-800">
                                            Retour effectué
                                        </button>
                                    </template>
                                    <Link :href="route('agency.bookings.show', booking.id)"
                                        class="ml-1 rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        Détail
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="bookings.links.length > 3" class="flex flex-wrap gap-1">
                    <component v-for="link in bookings.links" :key="link.label"
                        :is="link.url ? Link : 'span'" :href="link.url"
                        class="rounded px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-100' : 'text-gray-400'"
                        v-html="link.label" />
                </div>
            </div>
        </div>

        <div v-if="refusing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="refusing = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Refuser {{ refusing.reference }}</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Le motif part par email au client. Soyez assez précis pour qu’il sache s’il
                    doit changer de dates ou chercher ailleurs.
                </p>
                <textarea v-model="refuseForm.reason" rows="3" autofocus
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Ex. : le véhicule est immobilisé pour révision sur cette période."></textarea>
                <p v-if="refuseForm.errors.reason" class="mt-1 text-sm text-red-600">{{ refuseForm.errors.reason }}</p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="refusing = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="confirmRefuse" :disabled="refuseForm.processing"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Confirmer le refus
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
