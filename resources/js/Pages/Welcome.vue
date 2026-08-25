<script setup>
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import VehicleCard from '@/Components/VehicleCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import { useCommunes } from '@/composables/useCommunes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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

// Les communes arrivent par le point d'acces dedie quand la wilaya change :
// 1541 communes livrees avec la page d'accueil seraient payees par chaque
// visiteur, pour une liste que la plupart ne deroulent jamais.
const { communes, loading: loadingCommunes } = useCommunes(wilayaId, props.searchWilayas, {
    onChange: () => (communeId.value = ''),
});

const submitSearch = () => {
    const wilaya = props.searchWilayas.find((w) => w.id === Number(wilayaId.value));

    // L'URL porte le lieu, pas des paramètres : c'est la forme indexable
    // demandée au 7.2, et celle qu'on peut envoyer par WhatsApp.
    if (!wilaya) return router.get(route('search'));

    const commune = communes.value.find((c) => c.id === Number(communeId.value));

    router.get(commune
        ? route('search.commune', { wilaya: wilaya.slug ?? wilaya.id, commune: commune.slug ?? commune.id })
        : route('search.wilaya', { wilaya: wilaya.slug ?? wilaya.id }));
};
</script>

<template>
    <Head title="Autokrili — Location de voitures en Algérie" />

    <div class="min-h-screen bg-white">
        <header class="sticky top-0 z-40 border-b border-gray-100 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-xl font-bold tracking-tight text-gray-900">Autokrili</Link>
                <nav class="flex items-center gap-4 text-sm">
                    <LocaleSwitcher />
                    <Link :href="route('search')" class="hidden text-gray-600 hover:text-gray-900 sm:block">
                        {{ t('Tous les véhicules') }}
                    </Link>
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')"
                        class="rounded-md bg-gray-900 px-4 py-2 font-medium text-white hover:bg-gray-800">
                        {{ t('Mon espace') }}
                    </Link>
                    <template v-else>
                        <Link v-if="canLogin" :href="route('login')" class="text-gray-600 hover:text-gray-900">
                            Connexion
                        </Link>
                        <Link :href="route('agency.register')"
                            class="rounded-md bg-gray-900 px-4 py-2 font-medium text-white hover:bg-gray-800">
                            {{ t('Inscrire mon agence') }}
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-6 pb-12 pt-16 text-center">
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    Louez une voiture partout en Algérie
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-gray-600">
                    {{ stats.vehicles ?? 0 }} véhicules proposés par
                    {{ stats.agencies ?? 0 }} agences vérifiées, dans {{ stats.wilayas ?? 58 }} wilayas.
                </p>

                <div class="mx-auto mt-10 max-w-3xl rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <form @submit.prevent="submitSearch" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                        <select v-model="wilayaId"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                            <option value="">{{ t('Toutes les wilayas') }}</option>
                            <option v-for="w in searchWilayas" :key="w.id" :value="w.id">
                                {{ w.code }} — {{ w.name_fr }}
                            </option>
                        </select>
                        <select v-model="communeId" :disabled="!communes.length || loadingCommunes"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900 disabled:bg-gray-100">
                            <option value="">
                                {{ loadingCommunes ? t('Chargement…') : t('Toutes les communes') }}
                            </option>
                            <option v-for="c in communes" :key="c.id" :value="c.id">{{ c.name_fr }}</option>
                        </select>
                        <button type="submit"
                            class="rounded-md bg-gray-900 px-6 py-2 text-sm font-medium text-white hover:bg-gray-800">
                            {{ t('Rechercher') }}
                        </button>
                    </form>
                    <p class="mt-2 text-left text-xs text-gray-500">
                        Les dates de location se choisissent sur la fiche du véhicule.
                    </p>
                </div>
            </section>

            <!-- Mise en avant Platinium : c'est l'emplacement que la formule paie. -->
            <section v-if="featured.length" class="border-t border-gray-100 bg-gray-50">
                <div class="mx-auto max-w-6xl px-6 py-14">
                    <div class="flex items-baseline justify-between">
                        <h2 class="text-xl font-semibold text-gray-900">{{ t('Véhicules mis en avant') }}</h2>
                        <Link :href="route('search')" class="text-sm text-gray-600 hover:text-gray-900">
                            {{ t('Tout voir') }}
                        </Link>
                    </div>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <VehicleCard v-for="vehicle in featured" :key="vehicle.id"
                            :vehicle="{ ...vehicle, sponsored: true }" />
                    </div>
                </div>
            </section>

            <section v-if="latest.length" class="mx-auto max-w-6xl px-6 py-14">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">{{ t('Dernières annonces') }}</h2>
                    <Link :href="route('search', { sort: 'recent' })" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ t('Tout voir') }}
                    </Link>
                </div>
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <VehicleCard v-for="vehicle in latest" :key="vehicle.id" :vehicle="vehicle" />
                </div>
            </section>

            <!-- Rien à louer encore : le dire franchement plutôt que d'afficher
                 une grille vide sous un titre optimiste. -->
            <section v-if="!featured.length && !latest.length" class="mx-auto max-w-3xl px-6 py-20 text-center">
                <p class="font-medium text-gray-900">Aucun véhicule en ligne pour le moment</p>
                <p class="mt-2 text-sm leading-relaxed text-gray-600">
                    Les premières agences finalisent leurs annonces. Inscrivez la vôtre pour
                    être présente dès le lancement.
                </p>
                <Link :href="route('agency.register')"
                    class="mt-6 inline-block rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    Inscrire mon agence
                </Link>
            </section>

            <section v-if="Object.keys(categories).length" class="border-y border-gray-100 bg-gray-50">
                <div class="mx-auto max-w-6xl px-6 py-14">
                    <h2 class="text-xl font-semibold text-gray-900">{{ t('Par catégorie') }}</h2>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link v-for="(total, key) in categories" :key="key"
                            :href="route('search', { category: key })"
                            class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:border-gray-900 hover:text-gray-900">
                            {{ CATEGORY_LABELS[key] ?? key }}
                            <span class="text-gray-400">({{ total }})</span>
                        </Link>
                    </div>

                    <template v-if="wilayas.length">
                        <h2 class="mt-12 text-xl font-semibold text-gray-900">{{ t('Par wilaya') }}</h2>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link v-for="w in wilayas" :key="w.id"
                                :href="route('search.wilaya', { wilaya: w.slug })"
                                class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:border-gray-900 hover:text-gray-900">
                                {{ w.name_fr }}
                                <span class="text-gray-400">({{ w.vehicles_count }})</span>
                            </Link>
                        </div>
                    </template>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-6 py-16">
                <div class="grid gap-8 lg:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 p-8">
                        <h2 class="text-xl font-semibold text-gray-900">Vous cherchez une voiture</h2>
                        <p class="mt-3 text-sm leading-relaxed text-gray-600">
                            Créez votre compte : vous retrouverez vos réservations, vos bons de
                            location et pourrez laisser un avis après chaque location.
                        </p>
                        <Link v-if="canRegister" :href="route('register')"
                            class="mt-6 inline-block rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-50">
                            Créer un compte client
                        </Link>
                    </div>

                    <div class="rounded-xl border border-gray-900 bg-gray-900 p-8 text-white">
                        <h2 class="text-xl font-semibold">Vous êtes une agence de location</h2>
                        <p class="mt-3 text-sm leading-relaxed text-gray-300">
                            Publiez vos véhicules, gérez votre calendrier de disponibilité et
                            recevez des demandes de réservation. L'inscription est gratuite ;
                            votre registre de commerce est vérifié sous 48&nbsp;heures ouvrées.
                        </p>
                        <Link :href="route('agency.register')"
                            class="mt-6 inline-block rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100">
                            {{ t('Inscrire mon agence') }}
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-gray-100">
            <div class="mx-auto max-w-6xl px-6 py-8">
                <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-600">
                    <Link :href="route('pages.about')" class="hover:text-gray-900">À propos</Link>
                    <Link :href="route('pages.faq')" class="hover:text-gray-900">Questions fréquentes</Link>
                    <Link :href="route('pages.terms')" class="hover:text-gray-900">Conditions générales</Link>
                    <Link :href="route('pages.privacy')" class="hover:text-gray-900">Confidentialité</Link>
                    <Link :href="route('pages.contact')" class="hover:text-gray-900">Contact</Link>
                </nav>
                <p class="mt-4 text-sm text-gray-500">Autokrili — location de voitures en Algérie.</p>
            </div>
        </footer>
    </div>
</template>
