<script setup>
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import VehicleCard from '@/Components/VehicleCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import { useCommunes } from '@/composables/useCommunes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    stats: { type: Object, default: () => ({}) },
    featured: { type: Array, default: () => [] },
    latest: { type: Array, default: () => [] },
    wilayas: { type: Array, default: () => [] },
    categories: { type: Object, default: () => ({}) },
    searchWilayas: { type: Array, default: () => [] },
});

const { t } = useTranslations();

const CATEGORY_LABELS = {
    citadine: 'Citadines', berline: 'Berlines', suv: 'SUV', utilitaire: 'Utilitaires',
    '4x4': '4x4', luxe: 'Luxe', minibus: 'Minibus',
};

const wilayaId = ref('');
const communeId = ref('');

/*
 * Les dates, posees le 2026-10-18.
 *
 * Les grands loueurs les demandent des la premiere page, et ce n'est pas une
 * coquetterie : sans dates, la liste montre des voitures deja prises, et le
 * visiteur ne l'apprend qu'au bout du tunnel. Avec elles, tout ce qu'il voit
 * est reservable.
 *
 * Rien n'est obligatoire pour autant. Quelqu'un qui veut d'abord regarder les
 * prix cherche sans dates, et la liste complete lui repond.
 */
const aujourdhui = new Date().toISOString().slice(0, 10);
const depart = ref('');
const retour = ref('');

// Un retour anterieur au depart n'a pas de sens : on le pousse, sans jamais
// bloquer la saisie — corriger sous les doigts de quelqu'un est detestable.
watch(depart, (valeur) => {
    if (valeur && retour.value && retour.value < valeur) retour.value = valeur;
});

// Les communes arrivent par le point d'acces dedie quand la wilaya change :
// 1541 communes livrees avec la page d'accueil seraient payees par chaque
// visiteur, pour une liste que la plupart ne deroulent jamais.
const { communes, loading: loadingCommunes } = useCommunes(wilayaId, props.searchWilayas, {
    onChange: () => (communeId.value = ''),
});

const submitSearch = () => {
    const wilaya = props.searchWilayas.find((w) => w.id === Number(wilayaId.value));

    // Les dates restent en parametres de requete : le lieu fait la page qu'un
    // moteur indexe, un sejour precis n'a rien a y faire.
    const sejour = depart.value && retour.value
        ? { start: depart.value, end: retour.value }
        : {};

    // L'URL porte le lieu, pas des paramètres : c'est la forme indexable
    // demandée au 7.2, et celle qu'on peut envoyer par WhatsApp.
    if (!wilaya) return router.get(route('search'), sejour);

    const commune = communes.value.find((c) => c.id === Number(communeId.value));

    router.get(commune
        ? route('search.commune', { wilaya: wilaya.slug ?? wilaya.id, commune: commune.slug ?? commune.id })
        : route('search.wilaya', { wilaya: wilaya.slug ?? wilaya.id }), sejour);
};
</script>

<template>
    <Head title="Autokrili — Location de voitures en Algérie" />

    <div class="min-h-screen bg-creme">
        <!--
            L'en-tete flotte AU-DESSUS de l'image, sans fond, tant qu'on est en
            haut de page. Un bandeau blanc poserait une barre en travers du
            paysage et couperait la photo en deux.
        -->
        <header class="absolute inset-x-0 top-0 z-40">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                <Link href="/" class="shrink-0 text-lg font-bold tracking-tight text-white drop-shadow sm:text-xl">
                    Auto<span class="text-marque-400">krili</span>
                </Link>
                <!--
                    Le menu deborde a 390 px si on ne le reduit pas.
                    Constate a la capture : selecteur de langue + « Connexion »
                    + « Inscrire mon agence » a taille pleine depassaient la
                    largeur d'un telephone, et ce debordement decalait TOUTE la
                    page vers la droite — banniere, carte de recherche et
                    grille comprises. Un debordement d'en-tete ne reste jamais
                    dans l'en-tete.

                    D'ou l'ecart et les marges reduits en dessous de `sm`, et
                    un libelle court sur mobile.
                -->
                <nav class="flex min-w-0 items-center gap-1.5 text-xs sm:gap-4 sm:text-sm">
                    <LocaleSwitcher />
                    <Link :href="route('search')" class="hidden text-white/80 hover:text-white sm:block">
                        {{ t('Tous les véhicules') }}
                    </Link>
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')"
                        class="whitespace-nowrap rounded-bouton bg-marque px-2.5 py-1.5 font-semibold text-encre-900 shadow-marque transition hover:bg-marque-400 sm:px-5 sm:py-2">
                        {{ t('Mon espace') }}
                    </Link>
                    <template v-else>
                        <Link v-if="canLogin" :href="route('login')"
                            class="whitespace-nowrap text-white/80 hover:text-white">
                            Connexion
                        </Link>
                        <Link :href="route('agency.register')"
                            class="whitespace-nowrap rounded-bouton bg-marque px-2.5 py-1.5 font-semibold text-encre-900 shadow-marque transition hover:bg-marque-400 sm:px-5 sm:py-2">
                            <span class="sm:hidden">{{ t('Mon agence') }}</span>
                            <span class="hidden sm:inline">{{ t('Inscrire mon agence') }}</span>
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <!--
                La banniere.

                L'image est un `<img>` positionne, pas un `background-image` :
                c'est le seul moyen d'avoir un `srcset`, donc de servir 37 Ko a
                un telephone au lieu des 272 Ko de la version large. Sur une
                connexion algerienne en 3G, l'ecart se voit.

                `fetchpriority="high"` parce que c'est l'element le plus grand
                de l'ecran d'accueil : c'est lui que Google chronometre.
            -->
            <section class="relative isolate overflow-hidden">
                <picture>
                    <source
                        type="image/webp"
                        srcset="/images/hero-route-800.webp 800w, /images/hero-route-1280.webp 1280w, /images/hero-route-1920.webp 1920w"
                        sizes="100vw" />
                    <img
                        src="/images/hero-route-1920.jpg"
                        srcset="/images/hero-route-800.jpg 800w, /images/hero-route-1280.jpg 1280w, /images/hero-route-1920.jpg 1920w"
                        sizes="100vw"
                        alt=""
                        fetchpriority="high"
                        class="absolute inset-0 -z-10 h-full w-full object-cover" />
                </picture>

                <!--
                    Deux degrades, et AUCUN voile uniforme.
                    Le premier jet superposait un voile a 45 % sur toute la
                    surface : le contraste etait bon, mais la route et les
                    montagnes disparaissaient. On avait paye une photo pour
                    afficher du noir.

                    Le degrade HORIZONTAL assombrit la gauche, ou vit le texte,
                    et laisse le paysage visible a droite. Le vertical, plus
                    leger, detache l'en-tete en haut et raccorde au creme en bas.
                -->
                <div class="absolute inset-0 -z-10 bg-gradient-to-r from-encre-950/85 via-encre-950/45 to-encre-950/10"></div>
                <div class="absolute inset-0 -z-10 bg-gradient-to-b from-encre-950/55 via-transparent to-encre-950/45"></div>

                <div class="mx-auto max-w-6xl px-6 pb-14 pt-32 sm:pb-20 sm:pt-36">
                    <h1 class="max-w-3xl text-[2rem] font-bold leading-[1.15] tracking-tight text-white drop-shadow-sm sm:text-6xl">
                        Louez une voiture
                        <span class="text-marque-400">partout en Algérie</span>
                    </h1>
                    <p class="mt-5 max-w-xl text-lg leading-relaxed text-white/85">
                        {{ stats.vehicles ?? 0 }} véhicules proposés par
                        {{ stats.agencies ?? 0 }} agences vérifiées, dans {{ stats.wilayas ?? 58 }} wilayas.
                    </p>

                    <!-- Le formulaire monte SUR l'image, en carte claire : c'est
                         l'action principale, elle doit etre le point le plus
                         lumineux de l'ecran. -->
                    <div class="mt-10 max-w-3xl rounded-carte bg-white p-4 shadow-carte-survol sm:p-5 lg:max-w-4xl">
                        <form @submit.prevent="submitSearch" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_auto_auto]">
                            <!-- Chaque champ porte son libelle : sans eux les
                                 listes flottaient plus haut que les dates, et
                                 la rangee partait de travers. -->
                            <label class="flex flex-col text-left">
                                <span class="mb-1 text-[11px] font-medium uppercase tracking-wide text-encre-400">
                                    {{ t('Wilaya') }}
                                </span>
                                <select v-model="wilayaId"
                                    class="rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque">
                                    <option value="">{{ t('Toutes les wilayas') }}</option>
                                    <option v-for="w in searchWilayas" :key="w.id" :value="w.id">
                                        {{ w.code }} — {{ w.name_fr }}
                                    </option>
                                </select>
                            </label>
                            <label class="flex flex-col text-left">
                                <span class="mb-1 text-[11px] font-medium uppercase tracking-wide text-encre-400">
                                    {{ t('Commune') }}
                                </span>
                                <select v-model="communeId" :disabled="!communes.length || loadingCommunes"
                                    class="rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque disabled:bg-encre-50">
                                    <option value="">
                                        {{ loadingCommunes ? t('Chargement…') : t('Toutes les communes') }}
                                    </option>
                                    <option v-for="c in communes" :key="c.id" :value="c.id">{{ c.name_fr }}</option>
                                </select>
                            </label>

                            <!-- Les champs natifs ouvrent le selecteur du
                                 telephone, celui que le visiteur connait deja.
                                 `min` empeche une date passee sans message
                                 d'erreur a lire. -->
                            <label class="flex flex-col text-left">
                                <span class="mb-1 text-[11px] font-medium uppercase tracking-wide text-encre-400">
                                    {{ t('Départ') }}
                                </span>
                                <input v-model="depart" type="date" :min="aujourdhui"
                                    class="rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque" />
                            </label>
                            <label class="flex flex-col text-left">
                                <span class="mb-1 text-[11px] font-medium uppercase tracking-wide text-encre-400">
                                    {{ t('Retour') }}
                                </span>
                                <input v-model="retour" type="date" :min="depart || aujourdhui"
                                    class="rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque" />
                            </label>

                            <!--
                                `sm:col-span-2` : sur deux colonnes le bouton
                                se retrouvait seul sur une demi-ligne.

                                `border border-transparent` + `py-2` : NE PAS
                                retirer. Les champs font 38 px (20 de ligne,
                                16 de marge interne, 2 de bordure) ; sans cette
                                bordure il aurait fallu `py-2.5`, soit 40 px,
                                et le bouton depassait de 2 px au-dessus de la
                                rangee. Mesure, pas estime.
                            -->
                            <button type="submit"
                                class="self-end rounded-bouton border border-transparent bg-marque px-8 py-2 text-sm font-semibold text-encre-900 shadow-marque transition hover:bg-marque-400 sm:col-span-2 lg:col-span-1">
                                {{ t('Rechercher') }}
                            </button>
                        </form>
                        <p class="mt-2.5 text-left text-xs text-encre-400">
                            {{ t('Indiquez vos dates pour ne voir que les véhicules libres. Sans dates, tout le catalogue s’affiche.') }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Mise en avant Platinium : c'est l'emplacement que la formule paie. -->
            <section v-if="featured.length" class="border-t border-encre-100 bg-creme-100">
                <div class="mx-auto max-w-6xl px-6 py-14">
                    <div class="flex items-baseline justify-between">
                        <h2 class="text-2xl font-bold tracking-tight text-encre-900">{{ t('Véhicules mis en avant') }}</h2>
                        <Link :href="route('search')" class="text-sm font-medium text-encre-500 hover:text-marque-600">
                            {{ t('Tout voir') }}
                        </Link>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3">
                        <VehicleCard v-for="vehicle in featured" :key="vehicle.id"
                            :vehicle="{ ...vehicle, sponsored: true }" />
                    </div>
                </div>
            </section>

            <section v-if="latest.length" class="mx-auto max-w-6xl px-6 py-14">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-2xl font-bold tracking-tight text-encre-900">{{ t('Dernières annonces') }}</h2>
                    <Link :href="route('search', { sort: 'recent' })" class="text-sm font-medium text-encre-500 hover:text-marque-600">
                        {{ t('Tout voir') }}
                    </Link>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                    <VehicleCard v-for="vehicle in latest" :key="vehicle.id" :vehicle="vehicle" />
                </div>
            </section>

            <!-- Rien à louer encore : le dire franchement plutôt que d'afficher
                 une grille vide sous un titre optimiste. -->
            <section v-if="!featured.length && !latest.length" class="mx-auto max-w-3xl px-6 py-20 text-center">
                <p class="font-medium text-encre-900">Aucun véhicule en ligne pour le moment</p>
                <p class="mt-2 text-sm leading-relaxed text-encre-500">
                    Les premières agences finalisent leurs annonces. Inscrivez la vôtre pour
                    être présente dès le lancement.
                </p>
                <Link :href="route('agency.register')"
                    class="mt-6 inline-block rounded-bouton bg-marque px-5 py-2.5 text-sm font-semibold text-encre-900 shadow-marque transition hover:bg-marque-400">
                    Inscrire mon agence
                </Link>
            </section>

            <section v-if="Object.keys(categories).length" class="border-y border-encre-100 bg-creme-100">
                <div class="mx-auto max-w-6xl px-6 py-14">
                    <h2 class="text-2xl font-bold tracking-tight text-encre-900">{{ t('Par catégorie') }}</h2>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link v-for="(total, key) in categories" :key="key"
                            :href="route('search', { category: key })"
                            class="rounded-full border border-encre-200 bg-white px-4 py-2 text-sm font-medium text-encre-600 shadow-sm transition hover:border-marque hover:bg-marque-50 hover:text-encre-900">
                            {{ CATEGORY_LABELS[key] ?? key }}
                            <span class="text-encre-300">({{ total }})</span>
                        </Link>
                    </div>

                    <template v-if="wilayas.length">
                        <h2 class="mt-12 text-2xl font-bold tracking-tight text-encre-900">{{ t('Par wilaya') }}</h2>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link v-for="w in wilayas" :key="w.id"
                                :href="route('search.wilaya', { wilaya: w.slug })"
                                class="rounded-full border border-encre-200 bg-white px-4 py-2 text-sm font-medium text-encre-600 shadow-sm transition hover:border-marque hover:bg-marque-50 hover:text-encre-900">
                                {{ w.name_fr }}
                                <span class="text-encre-300">({{ w.vehicles_count }})</span>
                            </Link>
                        </div>
                    </template>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-6 py-16">
                <div class="grid gap-8 lg:grid-cols-2">
                    <div class="rounded-carte border border-encre-100 bg-white p-8 shadow-carte">
                        <h2 class="text-2xl font-bold tracking-tight text-encre-900">Vous cherchez une voiture</h2>
                        <p class="mt-3 text-sm leading-relaxed text-encre-500">
                            Créez votre compte : vous retrouverez vos réservations, vos bons de
                            location et pourrez laisser un avis après chaque location.
                        </p>
                        <Link v-if="canRegister" :href="route('register')"
                            class="mt-6 inline-block rounded-bouton border border-encre-200 px-5 py-2.5 text-sm font-semibold text-encre-900 transition hover:border-marque hover:bg-marque-50">
                            Créer un compte client
                        </Link>
                    </div>

                    <div class="rounded-carte bg-encre-900 p-8 text-white shadow-carte-survol">
                        <h2 class="text-xl font-semibold">Vous êtes une agence de location</h2>
                        <p class="mt-3 text-sm leading-relaxed text-encre-200">
                            Publiez vos véhicules, gérez votre calendrier de disponibilité et
                            recevez des demandes de réservation. L'inscription est gratuite ;
                            votre registre de commerce est vérifié sous 48&nbsp;heures ouvrées.
                        </p>
                        <Link :href="route('agency.register')"
                            class="mt-6 inline-block rounded-bouton bg-marque px-5 py-2.5 text-sm font-semibold text-encre-900 shadow-marque transition hover:bg-marque-400">
                            {{ t('Inscrire mon agence') }}
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-encre-100">
            <div class="mx-auto max-w-6xl px-6 py-8">
                <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-encre-500">
                    <Link :href="route('pages.about')" class="hover:text-encre-900">À propos</Link>
                    <Link :href="route('pages.faq')" class="hover:text-encre-900">Questions fréquentes</Link>
                    <Link :href="route('pages.terms')" class="hover:text-encre-900">Conditions générales</Link>
                    <Link :href="route('pages.privacy')" class="hover:text-encre-900">Confidentialité</Link>
                    <Link :href="route('pages.contact')" class="hover:text-encre-900">Contact</Link>
                </nav>
                <p class="mt-4 text-sm text-encre-400">Autokrili — location de voitures en Algérie.</p>
            </div>
        </footer>
    </div>
</template>
