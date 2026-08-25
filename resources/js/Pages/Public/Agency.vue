<script setup>
import LocationMap from '@/Components/LocationMap.vue';
import VehicleCard from '@/Components/VehicleCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const { t } = useTranslations();

const props = defineProps({
    agency: { type: Object, required: true },
    vehicles: { type: Object, required: true },
    reviews: { type: Array, default: () => [] },
    ratingDistribution: { type: Object, default: () => ({}) },
    structuredData: { type: Object, default: () => ({}) },
});

const stars = (n) => '★'.repeat(n) + '☆'.repeat(5 - n);

const maxBar = computed(() => Math.max(1, ...Object.values(props.ratingDistribution ?? {})));

const price = (v) => Number(v ?? 0).toLocaleString('fr-DZ');

const whatsappLink = computed(() => {
    const raw = props.agency.whatsapp || props.agency.phone;
    if (!raw) return null;
    return `https://wa.me/${raw.replace(/[^0-9]/g, '').replace(/^0/, '213')}`;
});
</script>

<template>
    <Head>
        <title>{{ agency.commercial_name }} — location de voiture à {{ agency.commune }}</title>
        <meta name="description"
            :content="`${agency.commercial_name}, agence de location de voitures à ${agency.commune}, ${agency.wilaya}. ${agency.vehicles_count} véhicule(s) disponible(s)${agency.cheapest_price ? ' à partir de ' + price(agency.cheapest_price) + ' DA par jour' : ''}.`" />
        <!-- Données structurées lues par les robots, qui n'exécutent pas Vue. -->
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
                <Link :href="route('search.wilaya', { wilaya: agency.wilaya_slug })" class="hover:text-gray-900">
                    {{ agency.wilaya }}
                </Link>
                <span class="mx-1">/</span><span class="text-gray-700">{{ agency.commercial_name }}</span>
            </nav>

            <div class="mt-4 grid gap-6 lg:grid-cols-[1fr_320px]">
                <div class="space-y-6">
                    <div class="rounded-xl border border-gray-200 bg-white p-6">
                        <div class="flex flex-wrap items-center gap-4">
                            <img v-if="agency.logo_url" :src="agency.logo_url" :alt="agency.commercial_name"
                                class="h-16 w-16 rounded-full object-cover" />
                            <div v-else
                                class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-lg font-semibold text-gray-600">
                                {{ agency.commercial_name.slice(0, 2).toUpperCase() }}
                            </div>

                            <div class="flex-1">
                                <h1 class="text-2xl font-bold text-gray-900">{{ agency.commercial_name }}</h1>
                                <p class="text-sm text-gray-600">
                                    {{ agency.address }} — {{ agency.commune }}, {{ agency.wilaya }}
                                </p>
                                <p v-if="agency.member_since" class="text-xs capitalize text-gray-400">
                                    Sur Autokrili depuis {{ agency.member_since }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-6 border-t border-gray-100 pt-4 text-sm">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">{{ t('Véhicules') }}</p>
                                <p class="text-lg font-semibold text-gray-900">{{ agency.vehicles_count }}</p>
                            </div>
                            <div v-if="agency.cheapest_price">
                                <p class="text-xs uppercase tracking-wide text-gray-500">{{ t('À partir de') }}</p>
                                <p class="text-lg font-semibold text-gray-900">{{ price(agency.cheapest_price) }} DA</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-gray-500">{{ t('Avis') }}</p>
                                <p class="text-lg font-semibold text-gray-900">
                                    <template v-if="agency.reviews_count">
                                        {{ agency.average_rating.toFixed(1) }} / 5
                                        <span class="text-sm font-normal text-gray-500">({{ agency.reviews_count }})</span>
                                    </template>
                                    <span v-else class="text-sm font-normal text-gray-500">{{ t("Pas encore d'avis") }}</span>
                                </p>
                            </div>
                        </div>

                        <p v-if="agency.description" class="mt-4 whitespace-pre-line text-sm leading-relaxed text-gray-700">
                            {{ agency.description }}
                        </p>
                    </div>

                    <div v-if="agency.rental_conditions" class="rounded-xl border border-gray-200 bg-white p-6">
                        <h2 class="text-sm font-semibold text-gray-900">{{ t('Conditions de location') }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-gray-700">
                            {{ agency.rental_conditions }}
                        </p>
                        <div class="mt-4 flex flex-wrap gap-4 border-t border-gray-100 pt-4 text-sm text-gray-700">
                            <p v-if="agency.min_driver_age">
                                {{ t('Âge minimum du conducteur') }} : <span class="font-medium">{{ agency.min_driver_age }} {{ t('ans') }}</span>
                            </p>
                            <p v-if="agency.default_deposit_dzd">
                                {{ t('Caution') }} : <span class="font-medium">{{ price(agency.default_deposit_dzd) }} DA</span>
                            </p>
                        </div>
                    </div>

                    <!-- Avis : seuls les approuvés arrivent ici (§9). -->
                    <section v-if="reviews.length" class="rounded-xl border border-gray-200 bg-white p-6">
                        <h2 class="text-sm font-semibold text-gray-900">
                            {{ t('Avis clients') }}
                            <span class="font-normal text-gray-500">({{ agency.reviews_count }})</span>
                        </h2>

                        <div class="mt-3 flex flex-wrap items-center gap-6">
                            <p class="text-3xl font-bold text-gray-900">
                                {{ agency.average_rating.toFixed(1) }}
                                <span class="text-base font-normal text-gray-500">/ 5</span>
                            </p>
                            <div class="min-w-[180px] flex-1">
                                <div v-for="n in [5, 4, 3, 2, 1]" :key="n" class="flex items-center gap-2 text-xs">
                                    <span class="w-3 text-gray-500">{{ n }}</span>
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-full rounded-full bg-amber-400"
                                            :style="{ width: ((ratingDistribution?.[n] ?? 0) / maxBar * 100) + '%' }" />
                                    </div>
                                    <span class="w-5 text-right text-gray-500">{{ ratingDistribution?.[n] ?? 0 }}</span>
                                </div>
                            </div>
                        </div>

                        <ul class="mt-5 space-y-4 border-t border-gray-100 pt-4">
                            <li v-for="review in reviews" :key="review.id">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <span class="text-amber-500">{{ stars(review.rating) }}</span>
                                    <span class="text-sm font-medium text-gray-900">{{ review.author }}</span>
                                    <span class="text-xs capitalize text-gray-400">{{ review.created_at }}</span>
                                </div>
                                <p v-if="review.comment" class="mt-1 whitespace-pre-line text-sm text-gray-700">
                                    {{ review.comment }}
                                </p>
                                <div v-if="review.agency_reply"
                                    class="mt-2 rounded-md border-l-2 border-gray-300 bg-gray-50 p-3">
                                    <p class="text-xs font-semibold text-gray-700">
                                        {{ t('Réponse de') }} {{ agency.commercial_name }}
                                    </p>
                                    <p class="mt-1 whitespace-pre-line text-sm text-gray-700">
                                        {{ review.agency_reply }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </section>

                    <section>
                        <h2 class="text-xl font-semibold text-gray-900">
                            {{ t('Véhicules disponibles') }}
                            <span class="text-base font-normal text-gray-500">({{ vehicles.total }})</span>
                        </h2>

                        <div v-if="!vehicles.data.length"
                            class="mt-4 rounded-xl border border-gray-200 bg-white p-10 text-center">
                            <p class="font-medium text-gray-900">{{ t('Aucun véhicule en ligne') }}</p>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ t("Cette agence n'a pas d'annonce publiée pour le moment.") }}
                            </p>
                        </div>

                        <div v-else class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            <VehicleCard v-for="vehicle in vehicles.data" :key="vehicle.id" :vehicle="vehicle" />
                        </div>

                        <div v-if="vehicles.links.length > 3" class="mt-6 flex flex-wrap justify-center gap-1">
                            <component v-for="link in vehicles.links" :key="link.label"
                                :is="link.url ? Link : 'span'" :href="link.url"
                                class="rounded px-3 py-1.5 text-sm"
                                :class="link.active ? 'bg-gray-900 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-100' : 'text-gray-400'"
                                v-html="link.label" />
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                    <div class="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 class="text-sm font-semibold text-gray-900">{{ t("Contacter l'agence") }}</h2>
                        <div class="mt-3 space-y-2">
                            <a v-if="agency.phone" :href="`tel:${agency.phone}`"
                                class="block rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-900 hover:bg-gray-50">
                                {{ agency.phone }}
                            </a>
                            <a v-if="whatsappLink" :href="whatsappLink" target="_blank" rel="noopener"
                                class="block rounded-md bg-green-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-green-700">
                                WhatsApp
                            </a>
                        </div>
                    </div>

                    <!-- La carte n'apparaît que si l'agence a posé son point :
                         centrer sur le chef-lieu de la wilaya afficherait une
                         adresse qui n'est pas la sienne. -->
                    <div v-if="agency.latitude && agency.longitude"
                        class="overflow-hidden rounded-xl border border-gray-200 bg-white p-2">
                        <LocationMap :latitude="agency.latitude" :longitude="agency.longitude"
                            :label="agency.commercial_name" height="260px" />
                        <p class="px-2 py-2 text-xs text-gray-500">{{ agency.address }}</p>
                    </div>

                    <div v-if="agency.opening_hours.length" class="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 class="text-sm font-semibold text-gray-900">{{ t('Horaires') }}</h2>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li v-for="day in agency.opening_hours" :key="day.label" class="flex justify-between">
                                <span class="text-gray-600">{{ day.label }}</span>
                                <span :class="day.hours === 'Fermé' ? 'text-gray-400' : 'text-gray-900'">
                                    {{ day.hours }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>

        <footer class="border-t border-gray-100 bg-white">
            <div class="mx-auto max-w-6xl px-6 py-8 text-sm text-gray-500">
                <p>Autokrili — location de voitures en Algérie.</p>
            </div>
        </footer>
    </div>
</template>
