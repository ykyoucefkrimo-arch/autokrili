<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const { t } = useTranslations();

const props = defineProps({
    vehicle: { type: Object, required: true },
    unavailableDates: { type: Array, default: () => [] },
    prefill: { type: Object, default: () => ({}) },
    isAuthenticated: { type: Boolean, default: false },
});

const form = useForm({
    start_date: props.prefill.start_date ?? '',
    end_date: props.prefill.end_date ?? '',
    with_driver: false,
    client_name: props.prefill.client_name ?? '',
    client_phone: props.prefill.client_phone ?? '',
    client_email: props.prefill.client_email ?? '',
    driver_license_number: '',
    client_note: '',
    accepts_conditions: false,
});

const STEPS = ['Dates et options', 'Vos coordonnées', 'Récapitulatif'];
const step = ref(0);

const today = new Date().toISOString().slice(0, 10);
const taken = new Set(props.unavailableDates);

const price = (v) => Number(v ?? 0).toLocaleString('fr-DZ');

/* Le devis vient du serveur : les règles tarifaires sont combinées par un
   service dont le navigateur n'a pas de copie, et un total calculé en
   JavaScript risquerait de ne pas être celui qui sera facturé. */
const quote = ref(null);
const quoting = ref(false);

const fetchQuote = async () => {
    if (!form.start_date || !form.end_date) return (quote.value = null);

    quoting.value = true;
    try {
        const url = route('bookings.quote', props.vehicle.id)
            + `?start_date=${form.start_date}&end_date=${form.end_date}&with_driver=${form.with_driver ? 1 : 0}`;
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        quote.value = response.ok ? await response.json() : null;
    } catch {
        quote.value = null;
    } finally {
        quoting.value = false;
    }
};

watch(() => [form.start_date, form.end_date, form.with_driver], fetchQuote, { immediate: true });

// Une date déjà prise saisie à la main doit se voir tout de suite, pas au
// moment où le serveur refuse la demande.
const rangeHasTakenDate = computed(() => {
    if (!form.start_date || !form.end_date) return false;
    const day = new Date(form.start_date);
    const end = new Date(form.end_date);
    while (day <= end) {
        if (taken.has(day.toISOString().slice(0, 10))) return true;
        day.setDate(day.getDate() + 1);
    }
    return false;
});

const canContinue = computed(() => {
    if (step.value === 0) {
        return form.start_date && form.end_date && !rangeHasTakenDate.value && quote.value?.available;
    }
    if (step.value === 1) {
        return form.client_name && form.client_phone;
    }
    return true;
});

const submit = () => form.post(route('bookings.store', props.vehicle.id));
</script>

<template>
    <Head :title="`Réserver ${vehicle.title}`" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-100 bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-xl font-bold tracking-tight text-gray-900">Autokrili</Link>
                <Link :href="route('vehicle.show', { vehicle: vehicle.id, slug: vehicle.slug })"
                    class="text-sm text-gray-600 hover:text-gray-900">
                    Retour au véhicule
                </Link>
            </div>
        </header>

        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-gray-900">Réserver {{ vehicle.title }}</h1>
            <p class="mt-1 text-sm text-gray-600">
                {{ vehicle.agency.commercial_name }} — retrait à {{ vehicle.pickup }}
            </p>

            <div v-if="form.errors.availability"
                class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                {{ form.errors.availability }}
            </div>

            <!-- Trois étapes (§7.1) : demander le permis avant de savoir si la
                 voiture est libre ferait abandonner. -->
            <nav class="mt-6 flex gap-2">
                <div v-for="(label, index) in STEPS" :key="label"
                    class="flex-1 rounded-md px-3 py-2 text-center text-sm font-medium"
                    :class="step === index ? 'bg-gray-900 text-white'
                        : index < step ? 'bg-gray-200 text-gray-700' : 'bg-white text-gray-400'">
                    {{ index + 1 }}. {{ t(label) }}
                </div>
            </nav>

            <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_300px]">
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Étape 1 -->
                    <div v-show="step === 0" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="start" :value="t('Date de départ')" />
                                <TextInput id="start" v-model="form.start_date" type="date" :min="today"
                                    class="mt-1 block w-full" />
                                <InputError :message="form.errors.start_date" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="end" :value="t('Date de retour')" />
                                <TextInput id="end" v-model="form.end_date" type="date"
                                    :min="form.start_date || today" class="mt-1 block w-full" />
                                <InputError :message="form.errors.end_date" class="mt-1" />
                            </div>
                        </div>

                        <p v-if="rangeHasTakenDate"
                            class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                            Une partie de ces dates est déjà prise. Choisissez une autre période.
                        </p>
                        <p v-else-if="quote && !quote.available"
                            class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                            {{ quote.reason }}
                        </p>

                        <label v-if="vehicle.with_driver_available" class="flex items-center gap-2">
                            <input v-model="form.with_driver" type="checkbox"
                                class="rounded border-gray-300 text-gray-900 focus:ring-gray-900" />
                            <span class="text-sm text-gray-700">
                                Avec chauffeur (+ {{ price(vehicle.driver_price_per_day) }} DA / jour)
                            </span>
                        </label>

                        <details v-if="unavailableDates.length" class="text-sm text-gray-600">
                            <summary class="cursor-pointer">Voir les dates déjà prises</summary>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span v-for="d in unavailableDates.slice(0, 60)" :key="d"
                                    class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{{ d }}</span>
                            </div>
                        </details>
                    </div>

                    <!-- Étape 2 -->
                    <div v-show="step === 1" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
                        <p v-if="!isAuthenticated" class="rounded-md bg-gray-50 p-3 text-sm text-gray-600">
                            Pas besoin de créer un compte : il est ouvert automatiquement avec ces
                            informations, et vous y retrouverez vos réservations.
                        </p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="name" :value="t('Nom complet')" />
                                <TextInput id="name" v-model="form.client_name" class="mt-1 block w-full" />
                                <InputError :message="form.errors.client_name" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="phone" :value="t('Téléphone')" />
                                <TextInput id="phone" v-model="form.client_phone" class="mt-1 block w-full"
                                    placeholder="05 55 12 34 56" />
                                <InputError :message="form.errors.client_phone" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="email" :value="t('Email (facultatif)')" />
                                <TextInput id="email" v-model="form.client_email" type="email" class="mt-1 block w-full" />
                                <InputError :message="form.errors.client_email" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="licence" :value="t('Numéro de permis (facultatif)')" />
                                <TextInput id="licence" v-model="form.driver_license_number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.driver_license_number" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="note" :value="t(&quot;Message à l'agence (facultatif)&quot;)" />
                            <textarea id="note" v-model="form.client_note" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                placeholder="Heure d’arrivée souhaitée, précisions…"></textarea>
                        </div>
                    </div>

                    <!-- Étape 3 -->
                    <div v-show="step === 2" class="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
                        <h2 class="font-semibold text-gray-900">{{ t("Vérifiez avant d'envoyer") }}</h2>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Véhicule</dt><dd class="text-gray-900">{{ vehicle.title }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ t('Du') }}</dt><dd class="text-gray-900">{{ form.start_date }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ t('Au') }}</dt><dd class="text-gray-900">{{ form.end_date }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ t('Retrait') }}</dt><dd class="text-gray-900">{{ vehicle.pickup }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ t('Nom') }}</dt><dd class="text-gray-900">{{ form.client_name }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">{{ t('Téléphone') }}</dt><dd class="text-gray-900">{{ form.client_phone }}</dd></div>
                        </dl>

                        <div v-if="vehicle.agency.rental_conditions"
                            class="rounded-md bg-gray-50 p-3 text-xs leading-relaxed text-gray-600">
                            <p class="font-semibold text-gray-800">Conditions de l’agence</p>
                            <p class="mt-1 whitespace-pre-line">{{ vehicle.agency.rental_conditions }}</p>
                        </div>

                        <label class="flex items-start gap-2">
                            <input v-model="form.accepts_conditions" type="checkbox"
                                class="mt-0.5 rounded border-gray-300 text-gray-900 focus:ring-gray-900" />
                            <span class="text-sm text-gray-700">
                                J’accepte les conditions de location de l’agence.
                                <span v-if="vehicle.agency.min_driver_age">
                                    Âge minimum du conducteur : {{ vehicle.agency.min_driver_age }} ans.
                                </span>
                                <span v-if="vehicle.agency.deposit_dzd">
                                    Caution de {{ price(vehicle.agency.deposit_dzd) }} DA au départ.
                                </span>
                            </span>
                        </label>
                        <InputError :message="form.errors.accepts_conditions" />

                        <p class="text-xs text-gray-500">
                            Aucun paiement en ligne : le règlement se fait à l’agence, au départ.
                            L’agence a 24 heures pour répondre.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button v-if="step > 0" type="button" @click="step--"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            {{ t('Précédent') }}
                        </button>
                        <button v-if="step < 2" type="button" @click="step++" :disabled="!canContinue"
                            class="ml-auto rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-40">
                            {{ t('Continuer') }}
                        </button>
                        <button v-else type="submit" :disabled="form.processing"
                            class="ml-auto rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50">
                            {{ t('Envoyer la demande') }}
                        </button>
                    </div>
                </form>

                <!-- Le détail du calcul reste visible tout au long (§6.4). -->
                <aside class="h-fit rounded-xl border border-gray-200 bg-white p-5 lg:sticky lg:top-6">
                    <img v-if="vehicle.cover_url" :src="vehicle.cover_url" alt=""
                        class="mb-4 aspect-[4/3] w-full rounded-lg object-cover" />

                    <p v-if="quoting" class="text-sm text-gray-500">{{ t('Calcul en cours…') }}</p>

                    <template v-else-if="quote && quote.days">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ quote.days }} jour{{ quote.days > 1 ? 's' : '' }}
                        </p>
                        <ul class="mt-3 space-y-1.5 text-sm">
                            <li v-for="line in quote.lines" :key="line.label" class="flex justify-between">
                                <span class="text-gray-600">
                                    {{ line.label }} × {{ line.quantity }}
                                    <span class="text-gray-400">({{ price(line.unit_price) }} DA)</span>
                                </span>
                                <span class="text-gray-900">{{ price(line.total) }} DA</span>
                            </li>
                        </ul>
                        <div class="mt-3 flex justify-between border-t border-gray-200 pt-3">
                            <span class="font-semibold text-gray-900">{{ t('Total') }}</span>
                            <span class="text-lg font-bold text-gray-900">{{ price(quote.total) }} DA</span>
                        </div>
                        <p v-if="quote.deposit" class="mt-2 text-xs text-gray-500">
                            + caution de {{ price(quote.deposit) }} DA, rendue au retour
                        </p>
                    </template>

                    <p v-else class="text-sm text-gray-500">
                        {{ t('Choisissez vos dates pour voir le prix.') }}
                    </p>

                    <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500">
                        Agence : {{ vehicle.agency.commercial_name }}<br>
                        Tél. {{ vehicle.agency.phone }}
                    </p>
                </aside>
            </div>
        </div>
    </div>
</template>
