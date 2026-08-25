<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    booking: { type: Object, required: true },
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

const modal = ref(null);
const reasonForm = useForm({ reason: '' });

const open = (action) => {
    reasonForm.reset();
    reasonForm.clearErrors();
    modal.value = action;
};

const submitReason = () => {
    reasonForm.post(route(`agency.bookings.${modal.value}`, props.booking.id), {
        preserveScroll: true,
        onSuccess: () => (modal.value = null),
    });
};

const act = (action) =>
    router.post(route(`agency.bookings.${action}`, props.booking.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="`Réservation ${booking.reference}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ booking.reference }}</h2>
                <Link :href="route('agency.bookings.index')" class="text-sm text-gray-600 hover:underline">
                    Retour aux réservations
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.errors?.booking"
                    class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    {{ $page.props.errors.booking }}
                </div>

                <div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="STATUSES[booking.status].classes">
                        {{ STATUSES[booking.status].label }}
                    </span>
                    <p class="text-sm text-gray-600">Demande reçue le {{ booking.created_at }}</p>
                    <p v-if="booking.expires_in_hours !== null" class="text-sm font-medium"
                        :class="booking.expires_in_hours <= 6 ? 'text-red-600' : 'text-amber-700'">
                        Réponse attendue sous {{ booking.expires_in_hours }} h
                    </p>

                    <div v-if="canWrite" class="ml-auto flex flex-wrap gap-2">
                        <button v-if="booking.status === 'pending'" @click="act('confirm')"
                            class="rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">
                            Accepter
                        </button>
                        <button v-if="booking.status === 'pending'" @click="open('refuse')"
                            class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                            Refuser
                        </button>
                        <button v-if="booking.status === 'confirmed'" @click="act('start')"
                            class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                            Départ effectué
                        </button>
                        <button v-if="booking.status === 'in_progress'" @click="act('complete')"
                            class="rounded bg-gray-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800">
                            Retour effectué
                        </button>
                        <button v-if="['pending', 'confirmed'].includes(booking.status)" @click="open('cancel')"
                            class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                            Annuler
                        </button>
                    </div>
                </div>

                <div v-if="booking.cancellation_reason"
                    class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <span class="font-semibold">
                        Annulée par
                        {{ booking.cancelled_by === 'client' ? 'le client'
                            : booking.cancelled_by === 'agency' ? 'l’agence' : 'la plateforme' }} :
                    </span>
                    {{ booking.cancellation_reason }}
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="space-y-4 lg:col-span-2">
                        <div class="rounded-lg bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">Location</h3>
                            <div class="mt-3 flex gap-4">
                                <img v-if="booking.cover_url" :src="booking.cover_url" alt=""
                                    class="h-20 w-28 rounded object-cover" />
                                <dl class="flex-1 space-y-1 text-sm">
                                    <div class="flex justify-between"><dt class="text-gray-500">Véhicule</dt><dd class="text-gray-900">{{ booking.vehicle }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-gray-500">Du</dt><dd class="text-gray-900">{{ booking.start_date }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-gray-500">Au</dt><dd class="text-gray-900">{{ booking.end_date }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-gray-500">Durée</dt><dd class="text-gray-900">{{ booking.total_days }} jour(s)</dd></div>
                                    <div class="flex justify-between"><dt class="text-gray-500">Retrait</dt><dd class="text-gray-900">{{ booking.pickup_location }}</dd></div>
                                    <div v-if="booking.with_driver" class="flex justify-between">
                                        <dt class="text-gray-500">Option</dt><dd class="text-gray-900">Avec chauffeur</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div v-if="booking.client_note" class="rounded-lg bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">Message du client</h3>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ booking.client_note }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Les coordonnées sont ce que l'agence vient chercher :
                             elle rappelle le client dans la minute qui suit. -->
                        <div class="rounded-lg bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">Client</h3>
                            <dl class="mt-2 space-y-1 text-sm">
                                <div><dt class="text-gray-500">Nom</dt><dd class="text-gray-900">{{ booking.client_name }}</dd></div>
                                <div>
                                    <dt class="text-gray-500">Téléphone</dt>
                                    <dd><a :href="`tel:${booking.client_phone}`" class="text-indigo-600 hover:underline">{{ booking.client_phone }}</a></dd>
                                </div>
                                <div v-if="booking.client_email">
                                    <dt class="text-gray-500">Email</dt>
                                    <dd class="break-all text-gray-900">{{ booking.client_email }}</dd>
                                </div>
                                <div v-if="booking.driver_license_number">
                                    <dt class="text-gray-500">Permis n°</dt><dd class="text-gray-900">{{ booking.driver_license_number }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-lg bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">Montant</h3>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="line in booking.price_breakdown" :key="line.label" class="flex justify-between">
                                    <span class="text-gray-600">{{ line.label }} × {{ line.quantity }}</span>
                                    <span class="text-gray-900">{{ price(line.total) }} DA</span>
                                </li>
                            </ul>
                            <div class="mt-2 flex justify-between border-t border-gray-100 pt-2">
                                <span class="font-semibold text-gray-900">Total</span>
                                <span class="font-bold text-gray-900">{{ price(booking.total_price_dzd) }} DA</span>
                            </div>
                            <p v-if="booking.deposit_dzd" class="mt-1 text-xs text-gray-500">
                                Caution : {{ price(booking.deposit_dzd) }} DA
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="modal = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">
                    {{ modal === 'refuse' ? 'Refuser' : 'Annuler' }} {{ booking.reference }}
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    Le motif part par email au client, et les dates repartent au catalogue.
                </p>
                <textarea v-model="reasonForm.reason" rows="3" autofocus
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Ex. : le véhicule est immobilisé pour révision sur cette période."></textarea>
                <p v-if="reasonForm.errors.reason" class="mt-1 text-sm text-red-600">{{ reasonForm.errors.reason }}</p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="modal = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Revenir
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
