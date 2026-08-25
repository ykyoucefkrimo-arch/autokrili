<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    upcoming: { type: Array, default: () => [] },
    past: { type: Array, default: () => [] },
});

const STATUSES = {
    pending: { label: 'En attente de réponse', classes: 'bg-amber-100 text-amber-800' },
    confirmed: { label: 'Confirmée', classes: 'bg-green-100 text-green-800' },
    in_progress: { label: 'En cours', classes: 'bg-indigo-100 text-indigo-800' },
    completed: { label: 'Terminée', classes: 'bg-gray-200 text-gray-700' },
    cancelled: { label: 'Annulée', classes: 'bg-red-100 text-red-800' },
    expired: { label: 'Expirée', classes: 'bg-gray-100 text-gray-500' },
};

const price = (v) => Number(v ?? 0).toLocaleString('fr-DZ');

const stars = (n) => '★'.repeat(n) + '☆'.repeat(5 - n);

/* Depot d'avis : possible une fois la location terminee, une seule fois. */
const reviewing = ref(null);
const reviewForm = useForm({ rating: 5, comment: '' });

const openReview = (booking) => {
    reviewForm.reset();
    reviewForm.clearErrors();
    reviewing.value = booking;
};

const submitReview = () => {
    reviewForm.post(route('bookings.review', reviewing.value.id), {
        preserveScroll: true,
        onSuccess: () => (reviewing.value = null),
    });
};

const cancelling = ref(null);
const cancelForm = useForm({ reason: '' });

const openCancel = (booking) => {
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelling.value = booking;
};

const confirmCancel = () => {
    cancelForm.post(route('bookings.cancel', cancelling.value.id), {
        preserveScroll: true,
        onSuccess: () => (cancelling.value = null),
    });
};

// wa.me veut un numéro international : 0555… devient 213555…
const whatsapp = (raw) => (raw ? `https://wa.me/${raw.replace(/[^0-9]/g, '').replace(/^0/, '213')}` : null);
</script>

<template>
    <Head title="Mes réservations" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Mes réservations</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <div v-if="!upcoming.length && !past.length"
                    class="rounded-xl bg-white p-12 text-center shadow-sm">
                    <p class="font-medium text-gray-900">Aucune réservation pour le moment</p>
                    <p class="mx-auto mt-2 max-w-md text-sm text-gray-600">
                        Cherchez un véhicule près de chez vous : la demande se fait en trois
                        étapes et le règlement à l’agence.
                    </p>
                    <Link :href="route('search')"
                        class="mt-4 inline-block rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                        Chercher un véhicule
                    </Link>
                </div>

                <template v-for="group in [
                    { key: 'upcoming', title: 'À venir et en cours', items: upcoming },
                    { key: 'past', title: 'Historique', items: past },
                ]" :key="group.key">
                    <section v-if="group.items.length">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                            {{ group.title }}
                        </h3>

                        <div class="mt-3 space-y-3">
                            <div v-for="booking in group.items" :key="booking.id"
                                class="overflow-hidden rounded-xl bg-white shadow-sm">
                                <div class="flex flex-col gap-4 p-4 sm:flex-row">
                                    <img v-if="booking.cover_url" :src="booking.cover_url" alt=""
                                        class="h-24 w-full rounded-lg object-cover sm:w-36" />

                                    <div class="flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="STATUSES[booking.status].classes">
                                                {{ STATUSES[booking.status].label }}
                                            </span>
                                            <span class="text-xs text-gray-400">{{ booking.reference }}</span>
                                        </div>

                                        <h4 class="mt-1 font-semibold text-gray-900">
                                            <Link v-if="booking.vehicle_id"
                                                :href="route('vehicle.show', { vehicle: booking.vehicle_id, slug: booking.vehicle_slug })"
                                                class="hover:underline">
                                                {{ booking.vehicle }}
                                            </Link>
                                            <span v-else>{{ booking.vehicle ?? 'Véhicule retiré du catalogue' }}</span>
                                        </h4>
                                        <p class="text-sm text-gray-600">
                                            Du {{ booking.start_date }} au {{ booking.end_date }}
                                            ({{ booking.total_days }} jour{{ booking.total_days > 1 ? 's' : '' }})
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            {{ booking.agency.commercial_name }} — retrait à {{ booking.pickup_location }}
                                        </p>

                                        <p v-if="booking.cancellation_reason"
                                            class="mt-2 rounded border border-red-200 bg-red-50 p-2 text-xs text-red-800">
                                            {{ booking.cancellation_reason }}
                                        </p>
                                    </div>

                                    <div class="text-right">
                                        <p class="text-lg font-bold text-gray-900">
                                            {{ price(booking.total_price_dzd) }} <span class="text-sm font-medium text-gray-500">DA</span>
                                        </p>
                                        <p v-if="booking.deposit_dzd" class="text-xs text-gray-500">
                                            + {{ price(booking.deposit_dzd) }} DA de caution
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 border-t border-gray-100 bg-gray-50 px-4 py-2.5">
                                    <a v-if="booking.has_voucher" :href="route('bookings.voucher', booking.id)"
                                        class="rounded bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">
                                        Bon de réservation (PDF)
                                    </a>
                                    <a v-if="booking.agency.phone" :href="`tel:${booking.agency.phone}`"
                                        class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-white">
                                        Appeler l’agence
                                    </a>
                                    <a v-if="whatsapp(booking.agency.whatsapp || booking.agency.phone)"
                                        :href="whatsapp(booking.agency.whatsapp || booking.agency.phone)"
                                        target="_blank" rel="noopener"
                                        class="rounded border border-green-600 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50">
                                        WhatsApp
                                    </a>
                                    <button v-if="booking.can_review" @click="openReview(booking)"
                                        class="rounded border border-amber-400 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50">
                                        Laisser un avis
                                    </button>
                                    <span v-else-if="booking.review" class="flex items-center gap-1.5 text-xs">
                                        <span class="text-amber-500">{{ stars(booking.review.rating) }}</span>
                                        <span class="text-gray-500">
                                            {{ booking.review.status === 'approved' ? 'avis publié'
                                                : booking.review.status === 'pending' ? 'avis en modération'
                                                : 'avis écarté' }}
                                        </span>
                                    </span>
                                    <button v-if="booking.can_cancel" @click="openCancel(booking)"
                                        class="ml-auto rounded px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                        Annuler
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </div>

        <div v-if="reviewing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="reviewing = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Votre avis sur {{ reviewing.vehicle }}</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Chez {{ reviewing.agency.commercial_name }}. Votre avis est relu avant
                    publication, et votre nom n'apparaît qu'en prénom et initiale.
                </p>

                <div class="mt-4 flex gap-1">
                    <button v-for="n in 5" :key="n" type="button" @click="reviewForm.rating = n"
                        class="text-3xl leading-none transition"
                        :class="n <= reviewForm.rating ? 'text-amber-400' : 'text-gray-200 hover:text-amber-200'">
                        ★
                    </button>
                </div>
                <p v-if="reviewForm.errors.rating" class="mt-1 text-sm text-red-600">{{ reviewForm.errors.rating }}</p>

                <textarea v-model="reviewForm.comment" rows="4"
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                    placeholder="État du véhicule, accueil, ponctualité… (facultatif)"></textarea>
                <p v-if="reviewForm.errors.comment" class="mt-1 text-sm text-red-600">{{ reviewForm.errors.comment }}</p>
                <p v-if="reviewForm.errors.review" class="mt-1 text-sm text-red-600">{{ reviewForm.errors.review }}</p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="reviewing = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Plus tard
                    </button>
                    <button @click="submitReview" :disabled="reviewForm.processing"
                        class="rounded bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50">
                        Envoyer mon avis
                    </button>
                </div>
            </div>
        </div>

        <div v-if="cancelling" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="cancelling = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Annuler {{ cancelling.reference }} ?</h3>
                <p class="mt-1 text-sm text-gray-600">
                    L’agence est prévenue et vos dates repartent au catalogue.
                    Indiquez brièvement pourquoi : elle planifie avec.
                </p>
                <textarea v-model="cancelForm.reason" rows="3" autofocus
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                    placeholder="Ex. : mon voyage est reporté."></textarea>
                <p v-if="cancelForm.errors.reason" class="mt-1 text-sm text-red-600">{{ cancelForm.errors.reason }}</p>
                <p v-if="cancelForm.errors.booking" class="mt-1 text-sm text-red-600">{{ cancelForm.errors.booking }}</p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="cancelling = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Garder ma réservation
                    </button>
                    <button @click="confirmCancel" :disabled="cancelForm.processing"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Confirmer l’annulation
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
