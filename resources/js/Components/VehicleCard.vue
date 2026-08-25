<script setup>
import { useTranslations } from '@/composables/useTranslations';
import { Link } from '@inertiajs/vue3';

const { t } = useTranslations();

defineProps({
    vehicle: { type: Object, required: true },
});

const LABELS = {
    citadine: 'Citadine', berline: 'Berline', suv: 'SUV', utilitaire: 'Utilitaire',
    '4x4': '4x4', luxe: 'Luxe', minibus: 'Minibus',
    manuelle: 'Manuelle', automatique: 'Automatique',
    essence: 'Essence', diesel: 'Diesel', gpl: 'GPL', hybride: 'Hybride', electrique: 'Électrique',
};

const price = (value) => (value === null ? null : Number(value).toLocaleString('fr-DZ'));
</script>

<template>
    <Link :href="route('vehicle.show', { vehicle: vehicle.id, slug: vehicle.slug })"
        class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-gray-300 hover:shadow-md">
        <div class="relative aspect-[4/3] bg-gray-100">
            <img v-if="vehicle.cover_url" :src="vehicle.cover_url" :alt="vehicle.title" loading="lazy"
                class="h-full w-full object-cover transition group-hover:scale-[1.02]" />
            <div v-else class="flex h-full items-center justify-center text-sm text-gray-400">
                {{ t('Photo à venir') }}
            </div>

            <span v-if="vehicle.badge" class="absolute left-2 top-2 rounded px-2 py-0.5 text-[11px] font-semibold text-white"
                :style="{ backgroundColor: vehicle.badge_color || '#111827' }">
                {{ vehicle.badge }}
            </span>

            <!-- Mention obligatoire (§4.2) : une annonce remontée par la formule
                 de son agence doit le dire, sinon le classement se fait passer
                 pour de la pertinence. -->
            <span v-if="vehicle.sponsored"
                class="absolute right-2 top-2 rounded bg-white/90 px-2 py-0.5 text-[11px] font-medium text-gray-600">
                {{ t('Sponsorisé') }}
            </span>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <h3 class="font-semibold text-gray-900">{{ vehicle.title }}</h3>
            <p class="mt-0.5 text-sm text-gray-500">
                {{ vehicle.commune }}<template v-if="vehicle.wilaya">, {{ vehicle.wilaya }}</template>
            </p>

            <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] text-gray-600">
                <span class="rounded bg-gray-100 px-1.5 py-0.5">{{ t(LABELS[vehicle.category]) }}</span>
                <span class="rounded bg-gray-100 px-1.5 py-0.5">{{ t(LABELS[vehicle.transmission]) }}</span>
                <span class="rounded bg-gray-100 px-1.5 py-0.5">{{ t(LABELS[vehicle.fuel]) }}</span>
                <span class="rounded bg-gray-100 px-1.5 py-0.5">{{ vehicle.seats }} {{ t('places') }}</span>
                <span v-if="vehicle.air_conditioning" class="rounded bg-gray-100 px-1.5 py-0.5">{{ t('Clim') }}</span>
            </div>

            <div class="mt-auto flex items-end justify-between pt-4">
                <div>
                    <p v-if="vehicle.daily_price" class="text-lg font-bold text-gray-900">
                        {{ price(vehicle.daily_price) }} <span class="text-sm font-medium text-gray-500">DA / {{ t('jour') }}</span>
                    </p>
                    <p v-else class="text-sm text-gray-500">{{ t('Prix sur demande') }}</p>
                    <p class="text-xs text-gray-500">{{ vehicle.agency }}</p>
                </div>
                <div v-if="vehicle.reviews_count > 0" class="text-right text-xs text-gray-600">
                    <span class="font-semibold text-gray-900">{{ Number(vehicle.rating).toFixed(1) }}</span> / 5
                    <p class="text-gray-400">{{ vehicle.reviews_count }} {{ t('avis') }}</p>
                </div>
            </div>
        </div>
    </Link>
</template>
