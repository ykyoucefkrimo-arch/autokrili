<script setup>
/*
 * Le logo d'Autokrili, fourni par le client le 2026-11-12.
 *
 * Deux versions découpées sur fond transparent (public/images/marque) :
 *   · « clair » — AUTO en blanc, pour les fonds sombres (bannière, en-tête nuit) ;
 *   · « fonce » — AUTO à l'encre, pour les fonds clairs.
 * KRILI et la voiture restent violets dans les deux.
 *
 * Le fichier d'origine écrivait « AUTOKRILLI » avec deux L ; un L a été retiré
 * à la demande du client : le nom et le domaine s'écrivent Autokrili.
 * Le slogan « L'Algérie à portée de volant ! » n'est pas repris ici : à la
 * hauteur d'un en-tête (32 à 48 px), il ferait 5 px et serait illisible.
 *
 * 32 px de haut sur téléphone, pas 40 : à 40 px le logo, plus large que
 * l'ancien mot écrit, faisait déborder l'en-tête de l'accueil de 17 px à
 * 390 px de large (mesuré le 2026-11-12).
 */
import { computed, ref } from 'vue';

const props = defineProps({
    sombre: { type: Boolean, default: false },
    taille: { type: String, default: 'md' }, // sm | md
});

const variante = computed(() => (props.sombre ? 'clair' : 'fonce'));
const jeu = (format) => `/images/marque/logo-${variante.value}-360.${format} 360w, /images/marque/logo-${variante.value}-720.${format} 720w`;
// Largeur réellement affichée (hauteur × 3,9) : le navigateur choisit le fichier.
const tailles = computed(() => (props.taille === 'sm' ? '126px' : '(min-width: 640px) 188px, 126px'));

// Image introuvable : on écrit le nom plutôt que d'afficher une icône cassée.
const casse = ref(false);
</script>

<template>
    <span v-if="casse" class="font-black uppercase italic tracking-tight leading-none"
        :class="taille === 'sm' ? 'text-lg' : 'text-xl sm:text-2xl'">
        <span :class="sombre ? 'text-white' : 'text-encre-900'">Auto</span><span
            :class="sombre ? 'text-marque-400' : 'text-marque-500'">krili</span>
    </span>
    <picture v-else class="block">
        <source type="image/webp" :srcset="jeu('webp')" :sizes="tailles" />
        <img :src="`/images/marque/logo-${variante}-360.png`" :srcset="jeu('png')" :sizes="tailles"
            alt="Autokrili" width="360" height="92" decoding="async"
            class="w-auto" :class="taille === 'sm' ? 'h-8' : 'h-8 sm:h-12'"
            @error="casse = true" />
    </picture>
</template>
