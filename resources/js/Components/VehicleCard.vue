<script setup>
import { useTranslations } from '@/composables/useTranslations';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

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

// Passe a vrai si le navigateur n'arrive pas a charger la photo.
const photoCassee = ref(false);
</script>

<template>
    <Link :href="route('vehicle.show', { vehicle: vehicle.id, slug: vehicle.slug })"
        class="group flex flex-col overflow-hidden rounded-carte border border-encre-100 bg-white shadow-carte transition hover:-translate-y-0.5 hover:border-marque-200 hover:shadow-carte-survol">
        <div class="relative aspect-[4/3] overflow-hidden bg-creme-100">
            <!--
                `@error` et non seulement `v-if` : le repli ne couvrait que le
                cas « aucune photo renseignée ». Quand `cover_url` existe mais
                que le fichier a disparu du disque, le navigateur affichait son
                icône d'image brisée avec le texte alternatif en travers de la
                carte — c'est pire qu'une absence de photo. Constaté sur la
                page d'accueil le 2026-10-18.
            -->
            <img v-if="vehicle.cover_url && !photoCassee" :src="vehicle.cover_url" :alt="vehicle.title"
                loading="lazy" @error="photoCassee = true"
                class="h-full w-full object-cover transition group-hover:scale-[1.02]" />
            <div v-else class="flex h-full items-center justify-center text-sm text-encre-300">
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
                class="absolute right-2 top-2 rounded bg-white/95 px-2 py-0.5 text-[11px] font-medium text-encre-500 backdrop-blur">
                {{ t('Sponsorisé') }}
            </span>
        </div>

        <div class="flex flex-1 flex-col p-3 sm:p-4">
            <h3 class="line-clamp-2 text-sm font-semibold text-encre-900 sm:text-base">{{ vehicle.title }}</h3>
            <p class="mt-0.5 text-sm text-encre-400">
                {{ vehicle.commune }}<template v-if="vehicle.wilaya">, {{ vehicle.wilaya }}</template>
            </p>

            <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] text-encre-500">
                <span class="rounded bg-creme-100 px-1.5 py-0.5">{{ t(LABELS[vehicle.category]) }}</span>
                <span class="rounded bg-creme-100 px-1.5 py-0.5">{{ t(LABELS[vehicle.transmission]) }}</span>
                <span class="rounded bg-creme-100 px-1.5 py-0.5">{{ t(LABELS[vehicle.fuel]) }}</span>
                <span class="hidden rounded bg-creme-100 px-1.5 py-0.5 sm:inline">{{ vehicle.seats }} {{ t('places') }}</span>
                <span v-if="vehicle.air_conditioning" class="hidden rounded bg-creme-100 px-1.5 py-0.5 sm:inline">{{ t('Clim') }}</span>
            </div>

            <!--
                `min-w-0` sur l'enfant de gauche, et ce n'est pas cosmetique.

                Le nom de l'agence porte `truncate`, donc `white-space:
                nowrap`. Dans un conteneur flex, un enfant a `min-width: auto`
                par defaut : sa largeur minimale devient celle du texte
                INSECABLE. La carte ne pouvait donc pas descendre sous ~210 px,
                la grille a deux colonnes exigeait ~470 px, et sur un telephone
                de 390 px c'est TOUTE la page qui debordait — banniere et
                formulaire de recherche compris.

                Constate a la capture le 2026-10-18. Le symptome etait loin de
                la cause : on cherchait le defaut dans l'en-tete.
            -->
            <div class="mt-auto flex flex-wrap items-end justify-between gap-x-2 gap-y-1 pt-3 sm:pt-4">
                <div class="min-w-0 flex-1">
                    <p v-if="vehicle.daily_price" class="text-base font-bold text-encre-900 sm:text-lg">
                        {{ price(vehicle.daily_price) }} <span class="text-xs font-medium text-encre-400 sm:text-sm">DA / {{ t('jour') }}</span>
                    </p>
                    <p v-else class="text-sm text-encre-400">{{ t('Prix sur demande') }}</p>
                    <p class="truncate text-xs text-encre-400">{{ vehicle.agency }}</p>
                </div>
                <div v-if="vehicle.reviews_count > 0" class="shrink-0 text-right text-xs text-encre-500">
                    <span class="font-semibold text-encre-900">{{ Number(vehicle.rating).toFixed(1) }}</span> / 5
                    <p class="text-encre-300">{{ vehicle.reviews_count }} {{ t('avis') }}</p>
                </div>
            </div>
        </div>
    </Link>
</template>
