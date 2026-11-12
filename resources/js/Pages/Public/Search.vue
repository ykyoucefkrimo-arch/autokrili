<script setup>
import VehicleCard from '@/Components/VehicleCard.vue';
import { useCommunes } from '@/composables/useCommunes';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const { t } = useTranslations();

const props = defineProps({
    vehicles: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    place: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    wilayas: { type: Array, default: () => [] },
});

const LABELS = {
    citadine: 'Citadine', berline: 'Berline', suv: 'SUV', utilitaire: 'Utilitaire',
    '4x4': '4x4', luxe: 'Luxe', minibus: 'Minibus',
    manuelle: 'Manuelle', automatique: 'Automatique',
    essence: 'Essence', diesel: 'Diesel', gpl: 'GPL', hybride: 'Hybride', electrique: 'Électrique',
};

const SORT_LABELS = {
    '': 'Pertinence',
    price_asc: 'Prix croissant',
    price_desc: 'Prix décroissant',
    recent: 'Plus récentes',
};

const form = ref({
    category: props.filters.category ?? '',
    transmission: props.filters.transmission ?? '',
    fuel: props.filters.fuel ?? '',
    seats: props.filters.seats ?? '',
    price_max: props.filters.price_max ?? '',
    air_conditioning: props.filters.air_conditioning ?? '',
    with_driver: props.filters.with_driver ?? '',
    search: props.filters.search ?? '',
    sort: props.filters.sort ?? '',
    // Le sejour, arrive de l'accueil. Dans le formulaire pour que le modifier
    // relance la recherche comme n'importe quel filtre ; hors de `reset()` et
    // du compteur, parce que ce n'est pas un filtre de confort : l'effacer
    // ferait silencieusement reapparaitre des voitures deja prises.
    start: props.filters.start ?? '',
    end: props.filters.end ?? '',
});

const title = computed(() => {
    if (props.place.commune) return `Location de voiture à ${props.place.commune.name_fr}`;
    if (props.place.wilaya) return `Location de voiture à ${props.place.wilaya.name_fr}`;
    return 'Tous les véhicules disponibles';
});

// Le lieu reste dans le chemin, les filtres dans la requête : changer un
// filtre ne doit pas faire perdre la wilaya, ni l'inverse.
const currentUrl = computed(() => {
    if (props.place.commune) {
        return route('search.commune', {
            wilaya: props.place.wilaya.slug,
            commune: props.place.commune.slug,
        });
    }
    if (props.place.wilaya) return route('search.wilaya', { wilaya: props.place.wilaya.slug });
    return route('search');
});

let timer = null;
watch(form, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const params = {};
        Object.entries(value).forEach(([key, v]) => {
            if (v !== '' && v !== null) params[key] = v;
        });
        router.get(currentUrl.value, params, { preserveState: true, replace: true, preserveScroll: true });
    }, 350);
}, { deep: true });

const reset = () => {
    form.value = {
        ...form.value,
        category: '', transmission: '', fuel: '', seats: '', price_max: '',
        air_conditioning: '', with_driver: '', search: '', sort: '',
    };
};

const aujourdhui = new Date().toISOString().slice(0, 10);

const HORS_COMPTEUR = ['sort', 'start', 'end'];

const activeCount = computed(
    () => Object.entries(form.value).filter(([key, v]) => !HORS_COMPTEUR.includes(key) && v !== '').length,
);

const sejour = computed(() => {
    if (!form.value.start || !form.value.end) return null;

    const depart = new Date(form.value.start);
    const retour = new Date(form.value.end);
    const jours = Math.round((retour - depart) / 86400000) + 1;

    return { jours, depart, retour };
});

// « 14 → 17 nov. » : la forme courte de la publicite, lisible d'un coup d'oeil.
const court = (date) => date.toLocaleDateString('fr-DZ', { day: 'numeric', month: 'short' });

/*
 * Les filtres de confort, replies sur telephone — 2026-11-11.
 *
 * Ils occupaient tout le premier ecran : onze champs empiles AVANT la
 * premiere voiture. Le visiteur venait de choisir sa wilaya et ses dates ;
 * il voyait un formulaire, pas une reponse. Ils restent a un toucher, et le
 * bouton dit combien sont actifs pour qu'un filtre oublie ne cache pas des
 * voitures en silence. Sur ordinateur, la colonne reste ouverte.
 */
const filtresOuverts = ref(false);

// Le sejour suit le visiteur sur la fiche du vehicule, ou le formulaire de
// reservation l'attend deja sous `start` et `end`.
const avecSejour = (params = {}) =>
    sejour.value ? { ...params, start: form.value.start, end: form.value.end } : params;

// Le lieu se choisit ici mais vit dans l'URL : changer de wilaya est une
// navigation vers une autre page indexable, pas un filtre de plus.
const selectedWilaya = ref(props.place.wilaya?.id ?? '');
const { communes, loading: loadingCommunes } = useCommunes(selectedWilaya, props.wilayas);

const changeWilaya = (event) => {
    const wilaya = props.wilayas.find((w) => w.id === Number(event.target.value));
    // `avecSejour` : changer de wilaya est une navigation, et une navigation
    // perdrait les dates si on ne les reposait pas dans l'URL.
    router.get(wilaya ? route('search.wilaya', { wilaya: wilaya.slug }) : route('search'), avecSejour());
};

const changeCommune = (event) => {
    const commune = communes.value.find((c) => c.id === Number(event.target.value));

    router.get(commune
        ? route('search.commune', { wilaya: props.place.wilaya.slug, commune: commune.slug })
        : route('search.wilaya', { wilaya: props.place.wilaya.slug }), avecSejour());
};
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-creme">
        <header class="sticky top-0 z-40 border-b border-encre-800 bg-encre-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
                <Link href="/" class="flex items-center gap-2 text-lg font-bold tracking-tight text-white">
                    <svg viewBox="0 0 48 48" class="h-6 w-6 fill-marque" aria-hidden="true">
                        <path d="M24 4 L45 44 H34.6 L24 23.2 L13.4 44 H3 Z" /><rect x="15" y="31.5" width="18" height="5.5" rx="1.5" />
                    </svg>
                    <span>Auto<span class="text-marque-400">krili</span></span>
                </Link>
                <nav class="flex items-center gap-4 text-sm">
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')"
                        class="rounded-bouton bg-marque px-4 py-2 font-semibold text-encre-900 transition hover:bg-marque-400">
                        {{ t('Mon espace') }}
                    </Link>
                    <Link v-else :href="route('login')" class="text-encre-200 hover:text-white">{{ t('Connexion') }}</Link>
                </nav>
            </div>
        </header>

        <div class="mx-auto max-w-6xl px-4 py-5 sm:px-6 sm:py-8">
            <!--
                La recherche, en haut et modifiable sur place : la meme que sur
                l'accueil — wilaya, depart, retour. Un visiteur arrive par un
                lien indexe n'est jamais passe par l'accueil ; c'est ici qu'il
                decouvre qu'il peut donner ses dates.
            -->
            <div class="grid grid-cols-2 gap-3 rounded-carte bg-white p-3 shadow-carte sm:p-4 lg:grid-cols-[minmax(0,1fr)_11rem_11rem]">
                <label class="col-span-2 flex min-w-0 flex-col lg:col-span-1">
                    <span class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-encre-400">{{ t('Wilaya') }}</span>
                    <select :value="place.wilaya?.id ?? ''" @change="changeWilaya"
                        class="h-12 rounded-bouton border-2 border-encre-100 text-base font-semibold text-encre-900 focus:border-marque focus:ring-marque">
                        <option value="">{{ t('Toutes les wilayas') }}</option>
                        <option v-for="w in wilayas" :key="w.id" :value="w.id">{{ w.name_fr }}</option>
                    </select>
                </label>
                <label class="flex min-w-0 flex-col">
                    <span class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-encre-400">{{ t('Départ') }}</span>
                    <input v-model="form.start" type="date" :min="aujourdhui"
                        class="h-12 min-w-0 rounded-bouton border-2 border-encre-100 text-base font-semibold text-encre-900 focus:border-marque focus:ring-marque" />
                </label>
                <label class="flex min-w-0 flex-col">
                    <span class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-encre-400">{{ t('Retour') }}</span>
                    <input v-model="form.end" type="date" :min="form.start || aujourdhui"
                        class="h-12 min-w-0 rounded-bouton border-2 border-encre-100 text-base font-semibold text-encre-900 focus:border-marque focus:ring-marque" />
                </label>
            </div>

            <!-- Le resume de la publicite : « Alger · 14 → 17 nov. · 3 jours »,
                 puis ce que la liste contient. -->
            <div class="mt-5 flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-encre-400">
                        {{ place.commune?.name_fr ?? place.wilaya?.name_fr ?? t('Toute l’Algérie') }}
                        <template v-if="sejour">
                            · {{ court(sejour.depart) }} → {{ court(sejour.retour) }}
                            · {{ sejour.jours }} {{ sejour.jours > 1 ? t('jours') : t('jour') }}
                        </template>
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight text-encre-900 sm:text-3xl">
                        {{ sejour ? t('Véhicules libres') : title }}
                    </h1>
                </div>
                <p class="text-sm text-encre-500">
                    {{ vehicles.total }} {{ vehicles.total > 1 ? t('véhicules') : t('véhicule') }}
                </p>
            </div>
            <p v-if="!sejour" class="mt-1 text-sm text-encre-500">
                {{ t('Indiquez vos dates pour ne voir que les véhicules libres.') }}
            </p>

            <div class="mt-5 grid gap-6 lg:grid-cols-[260px_1fr]">
                <aside>
                    <!-- Sur telephone : un bouton, et le panneau s'ouvre dessous. -->
                    <div class="flex items-center gap-2 lg:hidden">
                        <button type="button" @click="filtresOuverts = !filtresOuverts"
                            class="flex h-11 flex-1 items-center justify-center gap-2 rounded-bouton border-2 border-encre-100 bg-white text-sm font-semibold text-encre-900"
                            :aria-expanded="filtresOuverts">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                <path d="M4 6h16M7 12h10M10 18h4" stroke-linecap="round" />
                            </svg>
                            {{ t('Filtres') }}
                            <span v-if="activeCount" class="rounded-full bg-marque px-2 text-xs font-bold text-encre-900">{{ activeCount }}</span>
                        </button>
                        <select v-model="form.sort" :aria-label="t('Trier par')"
                            class="h-11 flex-1 rounded-bouton border-2 border-encre-100 bg-white text-sm font-semibold text-encre-900 focus:border-marque focus:ring-marque">
                            <option v-for="(label, key) in SORT_LABELS" :key="key" :value="key">{{ t(label) }}</option>
                        </select>
                    </div>

                    <div class="mt-3 rounded-carte border border-encre-100 bg-white p-4 shadow-carte lg:mt-0 lg:block"
                        :class="filtresOuverts ? 'block' : 'hidden'">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-encre-900">{{ t('Filtres') }}</h2>
                            <button v-if="activeCount" @click="reset" class="text-xs font-medium text-encre-500 hover:text-encre-900">
                                Effacer ({{ activeCount }})
                            </button>
                        </div>

                        <div class="mt-4 space-y-3 text-sm">
                            <input v-model="form.search" type="search" :placeholder="t('Marque ou modèle')"
                                class="w-full rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque" />

                            <select v-if="place.wilaya" :value="place.commune?.id ?? ''" @change="changeCommune"
                                :disabled="loadingCommunes"
                                class="w-full rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque disabled:bg-encre-50">
                                <option value="">
                                    {{ loadingCommunes ? t('Chargement…') : t('Toutes les communes') }}
                                </option>
                                <option v-for="c in communes" :key="c.id" :value="c.id">{{ c.name_fr }}</option>
                            </select>

                            <select v-model="form.category"
                                class="w-full rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque">
                                <option value="">{{ t('Toutes catégories') }}</option>
                                <option v-for="c in options.categories" :key="c" :value="c">{{ LABELS[c] }}</option>
                            </select>

                            <select v-model="form.transmission"
                                class="w-full rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque">
                                <option value="">{{ t('Toutes boîtes') }}</option>
                                <option v-for="t in options.transmissions" :key="t" :value="t">{{ LABELS[t] }}</option>
                            </select>

                            <select v-model="form.fuel"
                                class="w-full rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque">
                                <option value="">{{ t('Tous carburants') }}</option>
                                <option v-for="f in options.fuels" :key="f" :value="f">{{ LABELS[f] }}</option>
                            </select>

                            <select v-model="form.seats"
                                class="w-full rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque">
                                <option value="">{{ t('Nombre de places') }}</option>
                                <option value="4">4 places et plus</option>
                                <option value="5">5 places et plus</option>
                                <option value="7">7 places et plus</option>
                                <option value="9">9 places et plus</option>
                            </select>

                            <div>
                                <label class="text-xs text-encre-500">{{ t('Prix maximum par jour (DA)') }}</label>
                                <input v-model="form.price_max" type="number" step="500" :placeholder="t('Sans limite')"
                                    class="mt-1 w-full rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque" />
                            </div>

                            <label class="flex items-center gap-2 text-sm text-encre-700">
                                <input type="checkbox" true-value="1" false-value="" v-model="form.air_conditioning"
                                    class="rounded border-encre-300 text-marque-600 focus:ring-marque" />
                                {{ t('Climatisation') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm text-encre-700">
                                <input type="checkbox" true-value="1" false-value="" v-model="form.with_driver"
                                    class="rounded border-encre-300 text-marque-600 focus:ring-marque" />
                                {{ t('Avec chauffeur') }}
                            </label>
                        </div>
                    </div>
                </aside>

                <div>
                    <div class="mb-4 hidden items-center justify-end gap-2 lg:flex">
                        <label class="text-sm text-encre-500">{{ t('Trier par') }}</label>
                        <select v-model="form.sort"
                            class="rounded-bouton border-encre-200 text-sm shadow-sm focus:border-marque focus:ring-marque">
                            <option v-for="(label, key) in SORT_LABELS" :key="key" :value="key">{{ t(label) }}</option>
                        </select>
                    </div>

                    <div v-if="!vehicles.data.length" class="rounded-carte border border-encre-100 bg-white p-10 text-center shadow-carte">
                        <p class="font-semibold text-encre-900">{{ t('Aucun véhicule ne correspond') }}</p>
                        <p class="mx-auto mt-2 max-w-md text-sm text-encre-500">
                            {{ t('Élargissez la recherche : une autre commune, une autre catégorie, ou un prix maximum plus haut.') }}
                        </p>
                        <button v-if="activeCount" @click="reset"
                            class="mt-4 rounded-bouton border-2 border-encre-100 px-4 py-2 text-sm font-semibold text-encre-900 hover:border-marque hover:bg-marque-50">
                            {{ t('Effacer les filtres') }}
                        </button>
                    </div>

                    <!-- Une voiture par ligne sur telephone, la grille a partir de `sm`. -->
                    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-5 xl:grid-cols-3">
                        <VehicleCard v-for="vehicle in vehicles.data" :key="vehicle.id" :vehicle="vehicle"
                            :start="form.start" :end="form.end" liste />
                    </div>

                    <div v-if="vehicles.links.length > 3" class="mt-8 flex flex-wrap justify-center gap-1">
                        <component v-for="link in vehicles.links" :key="link.label"
                            :is="link.url ? Link : 'span'" :href="link.url"
                            class="rounded-bouton px-3 py-1.5 text-sm"
                            :class="link.active ? 'bg-encre-900 font-semibold text-white' : link.url ? 'bg-white text-encre-700 hover:bg-marque-50' : 'text-encre-300'"
                            v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
