<script setup>
import { useTranslations } from '@/composables/useTranslations';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const { t } = useTranslations();

const props = defineProps({
    vehicle: { type: Object, required: true },
    // Le sejour choisi en amont. La carte ne l'affiche pas, elle le TRANSPORTE :
    // sans cela le visiteur redonnerait ses dates sur la fiche, alors qu'il
    // vient de les saisir.
    start: { type: String, default: '' },
    end: { type: String, default: '' },
    /*
     * Presentation en liste, pour la page de resultats.
     *
     * Sur telephone, la grille a deux colonnes donnait des cartes de 170 px :
     * la photo etait un timbre, le titre tenait sur trois lignes et le prix
     * tombait sous le pli. En liste — photo a gauche, texte a droite, une
     * voiture par ligne — on lit le nom, l'agence et le prix d'un seul coup
     * d'oeil, comme sur la publicite. A partir de `sm`, la carte redevient
     * verticale et la grille reprend.
     *
     * L'accueil garde la grille : il montre des vitrines, pas une reponse.
     */
    liste: { type: Boolean, default: false },
});

const avecSejour = computed(() => Boolean(props.start && props.end));
const sejour = computed(() => (avecSejour.value ? { start: props.start, end: props.end } : {}));

const lien = computed(() => route('vehicle.show', {
    vehicle: props.vehicle.id,
    slug: props.vehicle.slug,
    ...sejour.value,
}));

/*
 * Le raccourci vers le formulaire de reservation, dates comprises.
 *
 * Il n'apparait qu'avec un sejour : sans dates, on ne sait pas si la voiture
 * est libre, et la fiche reste le bon passage. Avec des dates, la liste ne
 * contient QUE des voitures libres (filtre serveur) : on peut proposer de
 * reserver tout de suite, sans repasser par la fiche.
 */
const lienReserver = computed(() => route('bookings.create', { vehicle: props.vehicle.id, ...sejour.value }));

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
    <!--
        La carte n'est plus un seul lien : elle porte deux actions (la fiche, et
        « Reserver » quand les dates sont connues), et un lien ne peut pas en
        contenir un autre. Le lien de la fiche est etire sur toute la carte par
        son pseudo-element `after:` ; le bouton passe au-dessus (`relative z-10`).
    -->
    <article
        class="group relative flex overflow-hidden rounded-carte border border-encre-100 bg-white shadow-carte transition hover:-translate-y-0.5 hover:border-marque-200 hover:shadow-carte-survol"
        :class="liste ? 'flex-row sm:flex-col' : 'flex-col'">
        <div class="relative shrink-0 overflow-hidden bg-creme-100"
            :class="liste ? 'm-2.5 w-32 self-stretch rounded-bouton sm:m-0 sm:aspect-[4/3] sm:w-auto sm:self-auto sm:rounded-none' : 'aspect-[4/3]'">
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
            <div v-else class="flex h-full min-h-[6rem] items-center justify-center px-2 text-center text-xs text-encre-300 sm:text-sm">
                {{ t('Photo à venir') }}
            </div>

            <span v-if="vehicle.badge" class="absolute left-2 top-2 rounded px-2 py-0.5 text-[11px] font-semibold text-white"
                :style="{ backgroundColor: vehicle.badge_color || '#111113' }">
                {{ vehicle.badge }}
            </span>

            <!-- Mention obligatoire (§4.2) : une annonce remontée par la formule
                 de son agence doit le dire, sinon le classement se fait passer
                 pour de la pertinence. -->
            <span v-if="vehicle.sponsored"
                class="absolute right-2 top-2 rounded bg-white/95 px-2 py-0.5 text-[11px] font-medium text-encre-500 backdrop-blur"
                :class="liste ? 'max-sm:bottom-1.5 max-sm:left-1.5 max-sm:right-auto max-sm:top-auto' : ''">
                {{ t('Sponsorisé') }}
            </span>
        </div>

        <div class="flex min-w-0 flex-1 flex-col p-3 sm:p-4" :class="liste ? 'max-sm:py-3 max-sm:pl-1 max-sm:pr-3' : ''">
            <div class="flex items-start justify-between gap-2">
                <h3 class="line-clamp-2 text-sm font-semibold text-encre-900 sm:text-base" :class="liste ? 'max-sm:text-base max-sm:font-bold' : ''">
                    <Link :href="lien" class="after:absolute after:inset-0 focus:outline-none">
                        {{ vehicle.title }}
                    </Link>
                </h3>
                <!-- Vrai par construction : avec des dates, le serveur ne renvoie
                     que des voitures sans reservation ni blocage sur la periode. -->
                <span v-if="avecSejour"
                    class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                    {{ t('Libre') }}
                </span>
            </div>
            <p class="mt-0.5 truncate text-sm text-encre-400">
                {{ vehicle.commune }}<template v-if="vehicle.wilaya">, {{ vehicle.wilaya }}</template>
            </p>

            <!-- L'agence, et la verification qui la precede : toute annonce en
                 ligne appartient a une agence approuvee par l'administration. -->
            <p class="mt-1 flex min-w-0 items-center gap-1 text-xs font-semibold text-emerald-700">
                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                    <path d="M5 12.5l4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <span class="truncate">{{ vehicle.agency }}</span>
            </p>

            <div class="mt-2 flex-wrap gap-1.5 text-[11px] text-encre-500" :class="liste ? 'hidden sm:flex' : 'flex'">
                <span class="rounded bg-creme-100 px-1.5 py-0.5">{{ t(LABELS[vehicle.category]) }}</span>
                <span class="rounded bg-creme-100 px-1.5 py-0.5">{{ t(LABELS[vehicle.transmission]) }}</span>
                <span class="rounded bg-creme-100 px-1.5 py-0.5">{{ t(LABELS[vehicle.fuel]) }}</span>
                <span class="hidden rounded bg-creme-100 px-1.5 py-0.5 sm:inline">{{ vehicle.seats }} {{ t('places') }}</span>
                <span v-if="vehicle.air_conditioning" class="hidden rounded bg-creme-100 px-1.5 py-0.5 sm:inline">{{ t('Clim') }}</span>
            </div>

            <!--
                `min-w-0` sur l'enfant de gauche, et ce n'est pas cosmetique :
                un enfant flex a `min-width: auto`, donc la largeur de son texte
                insecable. Sans lui la carte ne descendait pas sous ~210 px et
                toute la page debordait sur un telephone de 390 px (2026-10-18).
            -->
            <div class="mt-auto flex flex-wrap items-end justify-between gap-x-2 gap-y-2 pt-3 sm:pt-4">
                <div class="min-w-0">
                    <!-- Le prix est l'information qu'on cherche : il porte la
                         couleur de la marque, comme sur la publicite. -->
                    <p v-if="vehicle.daily_price"
                        class="inline-block rounded-md border-2 border-marque-300 bg-marque-50 px-1.5 text-base font-bold text-encre-900 sm:text-lg">
                        {{ price(vehicle.daily_price) }} <span class="text-xs font-medium text-encre-500 sm:text-sm">DA / {{ t('jour') }}</span>
                    </p>
                    <p v-else class="text-sm text-encre-400">{{ t('Prix sur demande') }}</p>
                </div>
                <div v-if="vehicle.reviews_count > 0 && !avecSejour" class="shrink-0 text-right text-xs text-encre-500">
                    <span class="font-semibold text-encre-900">{{ Number(vehicle.rating).toFixed(1) }}</span> / 5
                    <p class="text-encre-300">{{ vehicle.reviews_count }} {{ t('avis') }}</p>
                </div>
                <Link v-if="avecSejour" :href="lienReserver"
                    class="relative z-10 shrink-0 rounded-bouton bg-marque px-4 py-2 text-sm font-bold text-encre-900 shadow-marque transition hover:bg-marque-400">
                    {{ t('Réserver') }}
                </Link>
            </div>
        </div>
    </article>
</template>
