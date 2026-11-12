<script setup>
/*
 * Le mot-symbole d'Autokrili, d'après le visuel publicitaire du 2026-11-12 :
 * capitales italiques très grasses, « AUTO » en clair ou en encre selon le
 * fond, « KRILI » en violet, et le profil d'une voiture tracé au-dessus.
 *
 * Un seul composant pour les en-têtes : le nom était écrit de quatre façons
 * différentes selon les pages (« Autokrili » en gras, « Auto » + « krili »
 * en couleur…), ce qui se voyait en passant d'une page à l'autre.
 *
 * Le visuel écrit « AUTOKRILLI » avec deux L : c'est une erreur du générateur
 * d'image. Le nom, le domaine et le site s'écrivent Autokrili.
 */
defineProps({
    // Sur fond nuit : « AUTO » en blanc et le violet éclairci, sinon il s'éteint.
    sombre: { type: Boolean, default: false },
    taille: { type: String, default: 'md' }, // sm | md
});
</script>

<template>
    <span class="inline-flex flex-col items-start leading-none" aria-label="Autokrili">
        <svg viewBox="0 0 120 18" aria-hidden="true" class="-mb-0.5 ml-1"
            :class="taille === 'sm' ? 'h-2.5 w-[5.5rem]' : 'h-3 w-[7.5rem]'">
            <!-- Le toit d'une berline, et trois traits de vitesse derrière elle. -->
            <path d="M18 15 C 30 4, 58 1, 82 5 C 92 7, 100 11, 112 13" fill="none"
                :class="sombre ? 'stroke-marque-400' : 'stroke-marque-500'" stroke-width="3.2" stroke-linecap="round" />
            <path d="M2 9 H 13 M 5 13 H 15" fill="none"
                :class="sombre ? 'stroke-marque-400' : 'stroke-marque-500'" stroke-width="2.4" stroke-linecap="round" />
        </svg>
        <span class="font-black uppercase italic tracking-tight"
            :class="taille === 'sm' ? 'text-lg' : 'text-xl sm:text-2xl'">
            <span :class="sombre ? 'text-white' : 'text-encre-900'">Auto</span><span
                :class="sombre ? 'text-marque-400' : 'text-marque-500'">krili</span>
        </span>
    </span>
</template>
