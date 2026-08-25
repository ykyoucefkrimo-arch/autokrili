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
        category: '', transmission: '', fuel: '', seats: '', price_max: '',
        air_conditioning: '', with_driver: '', search: '', sort: '',
    };
};

const activeCount = computed(
    () => Object.entries(form.value).filter(([key, v]) => key !== 'sort' && v !== '').length,
);

// Le lieu se choisit ici mais vit dans l'URL : changer de wilaya est une
// navigation vers une autre page indexable, pas un filtre de plus.
const selectedWilaya = ref(props.place.wilaya?.id ?? '');
const { communes, loading: loadingCommunes } = useCommunes(selectedWilaya, props.wilayas);

const changeWilaya = (event) => {
    const wilaya = props.wilayas.find((w) => w.id === Number(event.target.value));
    router.get(wilaya ? route('search.wilaya', { wilaya: wilaya.slug }) : route('search'));
};

const changeCommune = (event) => {
    const commune = communes.value.find((c) => c.id === Number(event.target.value));

    router.get(commune
        ? route('search.commune', { wilaya: props.place.wilaya.slug, commune: commune.slug })
        : route('search.wilaya', { wilaya: props.place.wilaya.slug }));
};
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-gray-50">
        <header class="sticky top-0 z-40 border-b border-gray-100 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-xl font-bold tracking-tight text-gray-900">Autokrili</Link>
                <nav class="flex items-center gap-4 text-sm">
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')"
                        class="rounded-md bg-gray-900 px-4 py-2 font-medium text-white hover:bg-gray-800">
                        Mon espace
                    </Link>
                    <Link v-else :href="route('login')" class="text-gray-600 hover:text-gray-900">{{ t('Connexion') }}</Link>
                </nav>
            </div>
        </header>

        <div class="mx-auto max-w-6xl px-6 py-8">
            <nav class="text-xs text-gray-500">
                <Link href="/" class="hover:text-gray-900">Accueil</Link>
                <template v-if="place.wilaya">
                    <span class="mx-1">/</span>
                    <Link :href="route('search.wilaya', { wilaya: place.wilaya.slug })" class="hover:text-gray-900">
                        {{ place.wilaya.name_fr }}
                    </Link>
                </template>
                <template v-if="place.commune">
                    <span class="mx-1">/</span><span class="text-gray-700">{{ place.commune.name_fr }}</span>
                </template>
            </nav>

            <div class="mt-2 flex flex-wrap items-baseline justify-between gap-3">
                <h1 class="text-2xl font-bold text-gray-900">{{ title }}</h1>
                <p class="text-sm text-gray-600">
                    {{ vehicles.total }} {{ vehicles.total > 1 ? t('véhicules') : t('véhicule') }}
                </p>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[260px_1fr]">
                <aside class="space-y-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">{{ t('Filtres') }}</h2>
                            <button v-if="activeCount" @click="reset" class="text-xs text-gray-500 hover:text-gray-900">
                                Effacer ({{ activeCount }})
                            </button>
                        </div>

                        <div class="mt-4 space-y-3 text-sm">
                            <input v-model="form.search" type="search" :placeholder="t('Marque ou modèle')"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900" />

                            <select :value="place.wilaya?.id ?? ''" @change="changeWilaya"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                                <option value="">{{ t('Toutes les wilayas') }}</option>
                                <option v-for="w in wilayas" :key="w.id" :value="w.id">{{ w.name_fr }}</option>
                            </select>

                            <select v-if="place.wilaya" :value="place.commune?.id ?? ''" @change="changeCommune"
                                :disabled="loadingCommunes"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900 disabled:bg-gray-100">
                                <option value="">
                                    {{ loadingCommunes ? t('Chargement…') : t('Toutes les communes') }}
                                </option>
                                <option v-for="c in communes" :key="c.id" :value="c.id">{{ c.name_fr }}</option>
                            </select>

                            <select v-model="form.category"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                                <option value="">{{ t('Toutes catégories') }}</option>
                                <option v-for="c in options.categories" :key="c" :value="c">{{ LABELS[c] }}</option>
                            </select>

                            <select v-model="form.transmission"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                                <option value="">{{ t('Toutes boîtes') }}</option>
                                <option v-for="t in options.transmissions" :key="t" :value="t">{{ LABELS[t] }}</option>
                            </select>

                            <select v-model="form.fuel"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                                <option value="">{{ t('Tous carburants') }}</option>
                                <option v-for="f in options.fuels" :key="f" :value="f">{{ LABELS[f] }}</option>
                            </select>

                            <select v-model="form.seats"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                                <option value="">{{ t('Nombre de places') }}</option>
                                <option value="4">4 places et plus</option>
                                <option value="5">5 places et plus</option>
                                <option value="7">7 places et plus</option>
                                <option value="9">9 places et plus</option>
                            </select>

                            <div>
                                <label class="text-xs text-gray-600">{{ t('Prix maximum par jour (DA)') }}</label>
                                <input v-model="form.price_max" type="number" step="500" :placeholder="t('Sans limite')"
                                    class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900" />
                            </div>

                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" true-value="1" false-value="" v-model="form.air_conditioning"
                                    class="rounded border-gray-300 text-gray-900 focus:ring-gray-900" />
                                {{ t('Climatisation') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" true-value="1" false-value="" v-model="form.with_driver"
                                    class="rounded border-gray-300 text-gray-900 focus:ring-gray-900" />
                                {{ t('Avec chauffeur') }}
                            </label>
                        </div>
                    </div>
                </aside>

                <div>
                    <div class="mb-4 flex items-center justify-end gap-2">
                        <label class="text-sm text-gray-600">{{ t('Trier par') }}</label>
                        <select v-model="form.sort"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                            <option v-for="(label, key) in SORT_LABELS" :key="key" :value="key">{{ t(label) }}</option>
                        </select>
                    </div>

                    <div v-if="!vehicles.data.length" class="rounded-xl border border-gray-200 bg-white p-12 text-center">
                        <p class="font-medium text-gray-900">{{ t('Aucun véhicule ne correspond') }}</p>
                        <p class="mx-auto mt-2 max-w-md text-sm text-gray-600">
                            {{ t('Élargissez la recherche : une autre commune, une autre catégorie, ou un prix maximum plus haut.') }}
                        </p>
                        <button v-if="activeCount" @click="reset"
                            class="mt-4 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            {{ t('Effacer les filtres') }}
                        </button>
                    </div>

                    <div v-else class="grid grid-cols-2 gap-3 sm:gap-5 xl:grid-cols-3">
                        <VehicleCard v-for="vehicle in vehicles.data" :key="vehicle.id" :vehicle="vehicle" />
                    </div>

                    <div v-if="vehicles.links.length > 3" class="mt-8 flex flex-wrap justify-center gap-1">
                        <component v-for="link in vehicles.links" :key="link.label"
                            :is="link.url ? Link : 'span'" :href="link.url"
                            class="rounded px-3 py-1.5 text-sm"
                            :class="link.active ? 'bg-gray-900 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-100' : 'text-gray-400'"
                            v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
