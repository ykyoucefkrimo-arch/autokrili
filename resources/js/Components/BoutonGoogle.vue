<script setup>
/**
 * « Continuer avec Google ».
 *
 * Un lien, pas un bouton de formulaire : la destination est une page de
 * Google, pas une action de l'application. Inertia ne doit donc PAS
 * l'intercepter — d'où `<a>` et non `<Link>`, sinon le navigateur reçoit du
 * JSON à la place d'une redirection.
 *
 * Le composant ne s'affiche pas si les identifiants ne sont pas configurés :
 * un bouton qui mène à une erreur de Google est pire que pas de bouton.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();
const disponible = computed(() => page.props.googleAuth === true);
</script>

<template>
    <div v-if="disponible">
        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-encre-100"></div>
            </div>
            <div class="relative flex justify-center">
                <span class="bg-white px-3 text-xs uppercase tracking-wide text-encre-400">
                    {{ t('ou') }}
                </span>
            </div>
        </div>

        <a :href="route('auth.google')"
            class="flex w-full items-center justify-center gap-3 rounded-bouton border border-encre-200 bg-white px-4 py-2.5 text-sm font-semibold text-encre-900 shadow-sm transition hover:border-encre-300 hover:bg-creme">
            <!-- Le logo officiel, en SVG : une image distante ferait dépendre
                 l'écran de connexion d'un serveur tiers. -->
            <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            {{ t('Continuer avec Google') }}
        </a>
    </div>
</template>
