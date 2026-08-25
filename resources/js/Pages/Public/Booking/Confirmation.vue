<script setup>
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link } from '@inertiajs/vue3';

const { t } = useTranslations();

defineProps({
    booking: { type: Object, required: true },
});

const price = (v) => Number(v ?? 0).toLocaleString('fr-DZ');
</script>

<template>
    <Head :title="`Demande ${booking.reference} envoyée`" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-100 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-xl font-bold tracking-tight text-gray-900">Autokrili</Link>
                <Link :href="route('bookings.index')" class="text-sm text-gray-600 hover:text-gray-900">
                    {{ t('Mes réservations') }}
                </Link>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-12">
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-6 w-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <h1 class="mt-4 text-2xl font-bold text-gray-900">{{ t('Demande envoyée') }}</h1>
                <p class="mt-2 text-sm text-gray-600">
                    {{ t('Votre référence') }} : <span class="font-semibold text-gray-900">{{ booking.reference }}</span>
                </p>

                <!-- Dire clairement que ce n'est pas encore une réservation :
                     un client qui croit sa voiture acquise ne cherche plus. -->
                <p class="mx-auto mt-4 max-w-md rounded-md bg-amber-50 p-3 text-sm text-amber-900">
                    Ce n’est pas encore une réservation ferme.
                    {{ booking.agency.commercial_name }} doit l’accepter
                    <template v-if="booking.expires_at">avant le {{ booking.expires_at }}</template>.
                    Vous recevrez un email dès qu’elle aura répondu.
                </p>
            </div>

            <div class="mt-4 rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-semibold text-gray-900">{{ t('Votre demande') }}</h2>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Véhicule</dt><dd class="text-gray-900">{{ booking.vehicle }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ t('Du') }}</dt><dd class="text-gray-900">{{ booking.start_date }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ t('Au') }}</dt><dd class="text-gray-900">{{ booking.end_date }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ t('Durée') }}</dt><dd class="text-gray-900">{{ booking.total_days }} jour(s)</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ t('Retrait') }}</dt><dd class="text-gray-900">{{ booking.pickup_location }}</dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2">
                        <dt class="font-semibold text-gray-900">Total à régler à l’agence</dt>
                        <dd class="font-bold text-gray-900">{{ price(booking.total_price_dzd) }} DA</dd>
                    </div>
                    <div v-if="booking.deposit_dzd" class="flex justify-between">
                        <dt class="text-gray-500">{{ t('Caution') }}</dt>
                        <dd class="text-gray-900">{{ price(booking.deposit_dzd) }} DA</dd>
                    </div>
                </dl>
            </div>

            <div class="mt-4 rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="text-sm font-semibold text-gray-900">Besoin de joindre l’agence ?</h2>
                <p class="mt-1 text-sm text-gray-600">{{ booking.agency.commercial_name }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a v-if="booking.agency.phone" :href="`tel:${booking.agency.phone}`"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-50">
                        {{ booking.agency.phone }}
                    </a>
                    <Link :href="route('bookings.index')"
                        class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                        {{ t('Mes réservations') }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
