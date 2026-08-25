<script setup>
import VehicleCard from '@/Components/VehicleCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const { t } = useTranslations();

const props = defineProps({
    vehicle: { type: Object, required: true },
    similar: { type: Array, default: () => [] },
    structuredData: { type: Object, default: () => ({}) },
});

const LABELS = {
    citadine: 'Citadine', berline: 'Berline', suv: 'SUV', utilitaire: 'Utilitaire',
    '4x4': '4x4', luxe: 'Luxe', minibus: 'Minibus',
    manuelle: 'Manuelle', automatique: 'Automatique',
    essence: 'Essence', diesel: 'Diesel', gpl: 'GPL', hybride: 'Hybride', electrique: 'Électrique',
    daily: 'À la journée', weekly: 'À la semaine', monthly: 'Au mois',
};

const active = ref(props.vehicle.photos[0] ?? null);

const price = (v) => Number(v).toLocaleString('fr-DZ');

const daily = computed(() => props.vehicle.pricing.find((r) => r.duration_type === 'daily'));

/* Un clic sur le téléphone ou WhatsApp est le signal le plus fort du site
   public : le navigateur est seul à savoir qu'il a eu lieu. `keepalive` pour
   que la requête survive à la navigation vers l'application téléphone. */
const trackContact = () => {
    fetch(route('vehicles.contact', props.vehicle.id), {
        method: 'POST',
        keepalive: true,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        },
    }).catch(() => {
        // Un compteur perdu ne doit jamais empêcher un client d'appeler.
    });
};

// Le numéro WhatsApp algérien se compose en international : 0555… devient
// 213555…, sinon le lien wa.me ne résout pas.
const whatsappLink = computed(() => {
    const raw = props.vehicle.agency.whatsapp || props.vehicle.agency.phone;
    if (!raw) return null;
    const digits = raw.replace(/[^0-9]/g, '').replace(/^0/, '213');
    return `https://wa.me/${digits}`;
});
</script>

<template>
    <Head>
        <title>{{ vehicle.title }} — location à {{ vehicle.pickup.commune }}</title>
        <meta name="description"
            :content="`Louez une ${vehicle.title} à ${vehicle.pickup.commune}, ${vehicle.pickup.wilaya}${daily ? ' à partir de ' + price(daily.price_dzd) + ' DA par jour' : ''}.`" />
        <component is="script" type="application/ld+json">{{ JSON.stringify(structuredData) }}</component>
    </Head>

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-100 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-xl font-bold tracking-tight text-gray-900">Autokrili</Link>
                <nav class="flex items-center gap-4 text-sm">
                    <Link :href="route('search')" class="text-gray-600 hover:text-gray-900">{{ t('Tous les véhicules') }}</Link>
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
                <Link href="/" class="hover:text-gray-900">{{ t('Accueil') }}</Link>
                <span class="mx-1">/</span>
                <Link :href="route('search.wilaya', { wilaya: vehicle.pickup.wilaya_slug })" class="hover:text-gray-900">
                    {{ vehicle.pickup.wilaya }}
                </Link>
                <span class="mx-1">/</span><span class="text-gray-700">{{ vehicle.title }}</span>
            </nav>

            <div class="mt-4 grid gap-6 lg:grid-cols-[1fr_340px]">
                <div class="space-y-6">
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                        <div class="aspect-[16/10] bg-gray-100">
                            <img v-if="active" :src="active.url" :alt="vehicle.title"
                                class="h-full w-full object-cover" />
                            <div v-else class="flex h-full items-center justify-center text-sm text-gray-400">
                                Photo à venir
                            </div>
                        </div>
                        <div v-if="vehicle.photos.length > 1" class="flex gap-2 overflow-x-auto p-3">
                            <button v-for="photo in vehicle.photos" :key="photo.id" @click="active = photo"
                                class="shrink-0 overflow-hidden rounded-lg border-2"
                                :class="active?.id === photo.id ? 'border-gray-900' : 'border-transparent'">
                                <img :src="photo.thumb" alt="" class="h-16 w-24 object-cover" />
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <h1 class="text-2xl font-bold text-gray-900">{{ vehicle.title }}</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ t('Retrait à') }} {{ vehicle.pickup.commune }}, {{ vehicle.pickup.wilaya }}
                        </p>

                        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                            <div><dt class="text-gray-500">{{ t('Catégorie') }}</dt><dd class="font-medium text-gray-900">{{ t(LABELS[vehicle.category]) }}</dd></div>
                            <div><dt class="text-gray-500">{{ t('Boîte') }}</dt><dd class="font-medium text-gray-900">{{ t(LABELS[vehicle.transmission]) }}</dd></div>
                            <div><dt class="text-gray-500">{{ t('Carburant') }}</dt><dd class="font-medium text-gray-900">{{ t(LABELS[vehicle.fuel]) }}</dd></div>
                            <div><dt class="text-gray-500">{{ t('Places') }}</dt><dd class="font-medium text-gray-900">{{ vehicle.seats }}</dd></div>
                            <div><dt class="text-gray-500">{{ t('Portes') }}</dt><dd class="font-medium text-gray-900">{{ vehicle.doors }}</dd></div>
                            <div><dt class="text-gray-500">{{ t('Climatisation') }}</dt><dd class="font-medium text-gray-900">{{ vehicle.air_conditioning ? t('Oui') : t('Non') }}</dd></div>
                            <div>
                                <dt class="text-gray-500">{{ t('Kilométrage') }}</dt>
                                <dd class="font-medium text-gray-900">
                                    {{ vehicle.mileage_limit_per_day ? vehicle.mileage_limit_per_day + ' km / ' + t('jour') : t('Illimité') }}
                                </dd>
                            </div>
                            <div v-if="vehicle.year"><dt class="text-gray-500">{{ t('Année') }}</dt><dd class="font-medium text-gray-900">{{ vehicle.year }}</dd></div>
                        </dl>

                        <div v-if="vehicle.description" class="mt-6 border-t border-gray-100 pt-6">
                            <h2 class="text-sm font-semibold text-gray-900">{{ t('Description') }}</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-700">
                                {{ vehicle.description }}
                            </p>
                        </div>

                        <div v-if="vehicle.agency.rental_conditions" class="mt-6 border-t border-gray-100 pt-6">
                            <h2 class="text-sm font-semibold text-gray-900">{{ t('Conditions de location') }}</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-700">
                                {{ vehicle.agency.rental_conditions }}
                            </p>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-4 border-t border-gray-100 pt-6 text-sm text-gray-700">
                            <p v-if="vehicle.agency.min_driver_age">
                                Âge minimum du conducteur : <span class="font-medium">{{ vehicle.agency.min_driver_age }} ans</span>
                            </p>
                            <p v-if="vehicle.agency.default_deposit_dzd">
                                Caution : <span class="font-medium">{{ price(vehicle.agency.default_deposit_dzd) }} DA</span>
                            </p>
                        </div>
                    </div>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <p v-if="daily" class="text-3xl font-bold text-gray-900">
                            {{ price(daily.price_dzd) }} <span class="text-base font-medium text-gray-500">DA / {{ t('jour') }}</span>
                        </p>
                        <p v-else class="text-lg font-medium text-gray-900">Prix sur demande</p>

                        <!-- Les tarifs dégressifs affichent leur équivalent journalier :
                             une remise qu'il faut calculer soi-même n'en est pas une. -->
                        <ul v-if="vehicle.pricing.length > 1" class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm">
                            <li v-for="rule in vehicle.pricing" :key="rule.duration_type" class="flex justify-between">
                                <span class="text-gray-600">{{ t(LABELS[rule.duration_type]) }}</span>
                                <span class="text-right">
                                    <span class="font-medium text-gray-900">{{ price(rule.price_dzd) }} DA</span>
                                    <span v-if="rule.duration_type !== 'daily'" class="block text-xs text-gray-500">
                                        {{ t('soit') }} {{ price(rule.daily_equivalent) }} DA / {{ t('jour') }}
                                    </span>
                                </span>
                            </li>
                        </ul>

                        <p v-if="vehicle.with_driver_available" class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                            {{ t('Option chauffeur') }} : <span class="font-medium">+ {{ price(vehicle.driver_price_per_day) }} DA / {{ t('jour') }}</span>
                        </p>

                        <div class="mt-6 space-y-2">
                            <Link :href="route('bookings.create', vehicle.id)"
                                class="block w-full rounded-md bg-gray-900 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-gray-800">
                                {{ t('Réserver ce véhicule') }}
                            </Link>
                            <p class="text-center text-xs text-gray-500">
                                Sans paiement en ligne : l'agence a 24 h pour répondre, le règlement
                                se fait au départ.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <div class="flex items-center gap-3">
                            <img v-if="vehicle.agency.logo_url" :src="vehicle.agency.logo_url" alt=""
                                class="h-11 w-11 rounded-full object-cover" />
                            <div v-else class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-600">
                                {{ vehicle.agency.commercial_name.slice(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <Link :href="route('agency.public', vehicle.agency.slug)"
                                    class="font-semibold text-gray-900 hover:underline">
                                    {{ vehicle.agency.commercial_name }}
                                </Link>
                                <p class="text-xs text-gray-500">
                                    {{ vehicle.agency.commune }}, {{ vehicle.agency.wilaya }}
                                </p>
                            </div>
                        </div>

                        <p v-if="vehicle.agency.reviews_count > 0" class="mt-3 text-sm text-gray-700">
                            <span class="font-semibold">{{ Number(vehicle.agency.average_rating).toFixed(1) }}</span> / 5
                            <span class="text-gray-500">({{ vehicle.agency.reviews_count }} avis)</span>
                        </p>
                        <p v-else class="mt-3 text-sm text-gray-500">Pas encore d'avis</p>

                        <div class="mt-4 space-y-2">
                            <a v-if="vehicle.agency.phone" :href="`tel:${vehicle.agency.phone}`" @click="trackContact"
                                class="block rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">
                                {{ vehicle.agency.phone }}
                            </a>
                            <a v-if="whatsappLink" :href="whatsappLink" target="_blank" rel="noopener" @click="trackContact"
                                class="block rounded-md bg-green-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-green-700">
                                Contacter par WhatsApp
                            </a>
                        </div>
                    </div>
                </aside>
            </div>

            <section v-if="similar.length" class="mt-12">
                <h2 class="text-xl font-semibold text-gray-900">{{ t('Véhicules similaires') }}</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <VehicleCard v-for="item in similar" :key="item.id" :vehicle="item" />
                </div>
            </section>
        </div>

        <footer class="border-t border-gray-100 bg-white">
            <div class="mx-auto max-w-6xl px-6 py-8 text-sm text-gray-500">
                <p>Autokrili — location de voitures en Algérie.</p>
            </div>
        </footer>
    </div>
</template>
