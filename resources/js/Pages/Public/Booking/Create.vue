<script setup>
import Marque from '@/Components/Marque.vue';
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

const photoCassee = ref(false);

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

/*
 * Le meme format que le serveur exige.
 *
 * Il etait absent, et l'etape ne verifiait que le REMPLISSAGE : « 0555 »
 * passait, et l'erreur n'arrivait qu'a l'envoi final — sur un champ devenu
 * invisible deux etapes plus tot. On refuse donc ici, pendant que le champ
 * est sous les yeux.
 */
const TELEPHONE_DZ = /^(\+213[\s.-]?[5-7]|0[5-7])([\s.-]?\d{2}){4}$/;

const telephoneValide = computed(() => TELEPHONE_DZ.test(form.client_phone.trim()));

const canContinue = computed(() => {
    if (step.value === 0) {
        return form.start_date && form.end_date && !rangeHasTakenDate.value && quote.value?.available;
    }
    if (step.value === 1) {
        return form.client_name.trim() && telephoneValide.value;
    }
    return true;
});

// A quelle etape vit chaque champ : sans cela, une erreur du serveur sur le
// nom ou le telephone s'afficherait sur une etape que l'on ne regarde pas,
// et l'envoi semblerait echouer sans raison.
const ETAPE_DU_CHAMP = {
    start_date: 0, end_date: 0, with_driver: 0, availability: 0,
    client_name: 1, client_phone: 1, client_email: 1, driver_license_number: 1,
};

/*
 * Arrive de la liste avec ses dates : on passe directement aux coordonnees.
 *
 * Depuis le 2026-11-11, la liste de resultats propose « Reserver » quand le
 * sejour est connu. Rouvrir le tunnel sur les dates qu'on vient de saisir
 * demandait un toucher de plus pour ne rien decider. On saute l'etape — une
 * seule fois, et seulement si le devis du serveur confirme la disponibilite :
 * le moindre doute ramene a l'ecran des dates, ou le probleme s'explique.
 * « Precedent » y ramene aussi, et le saut ne se rejoue pas.
 */
let sautFait = false;
watch(quote, (devis) => {
    if (sautFait || step.value !== 0) return;
    if (!props.prefill.start_date || !props.prefill.end_date) return;
    if (devis?.available && !rangeHasTakenDate.value) {
        sautFait = true;
        step.value = 1;
    }
});

const enLettres = (iso) => (iso
    ? new Date(iso).toLocaleDateString('fr-DZ', { day: 'numeric', month: 'short' })
    : '');

const submit = () => form.post(route('bookings.store', props.vehicle.id), {
    onError: (erreurs) => {
        const etapes = Object.keys(erreurs)
            .map((champ) => ETAPE_DU_CHAMP[champ])
            .filter((e) => e !== undefined);

        if (etapes.length) step.value = Math.min(...etapes);
    },
});
</script>

<template>
    <Head :title="`Réserver ${vehicle.title}`" />

    <div class="min-h-screen bg-creme">
        <header class="border-b border-encre-100 bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between gap-4 px-6 py-4">
                <Link href="/" class="shrink-0"><Marque /></Link>
                <Link :href="route('vehicle.show', { vehicle: vehicle.id, slug: vehicle.slug })"
                    class="text-sm text-encre-500 hover:text-encre-900">
                    Retour au véhicule
                </Link>
            </div>
        </header>

        <div class="mx-auto max-w-4xl px-6 py-8">
            <h1 class="text-2xl font-bold text-encre-900">Réserver {{ vehicle.title }}</h1>
            <p class="mt-1 text-sm text-encre-500">
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
                    class="flex-1 rounded-bouton px-2 py-2 text-center text-xs font-semibold sm:px-3 sm:text-sm"
                    :class="step === index ? 'bg-encre-900 text-white'
                        : index < step ? 'bg-marque-100 text-encre-700' : 'bg-white text-encre-300'">
                    {{ index + 1 }}. {{ t(label) }}
                </div>
            </nav>

            <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_300px]">
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Étape 1 -->
                    <div v-show="step === 0" class="space-y-4 rounded-carte border border-encre-100 bg-white p-6">
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
                                class="rounded border-encre-300 text-marque-600 focus:ring-marque" />
                            <span class="text-sm text-encre-700">
                                Avec chauffeur (+ {{ price(vehicle.driver_price_per_day) }} DA / jour)
                            </span>
                        </label>

                        <details v-if="unavailableDates.length" class="text-sm text-encre-500">
                            <summary class="cursor-pointer">Voir les dates déjà prises</summary>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span v-for="d in unavailableDates.slice(0, 60)" :key="d"
                                    class="rounded bg-creme-100 px-1.5 py-0.5 text-xs text-encre-400">{{ d }}</span>
                            </div>
                        </details>
                    </div>

                    <!-- Étape 2 -->
                    <div v-show="step === 1" class="space-y-4 rounded-carte border border-encre-100 bg-white p-6">
                        <div class="flex items-center justify-between gap-3 rounded-bouton bg-marque-50 px-3 py-2 text-sm">
                            <span class="text-encre-700">
                                <span class="font-semibold text-encre-900">{{ enLettres(form.start_date) }} → {{ enLettres(form.end_date) }}</span>
                                <template v-if="quote?.total"> · {{ price(quote.total) }} DA</template>
                            </span>
                            <button type="button" @click="step = 0" class="shrink-0 font-semibold text-marque-700 hover:text-marque-800">
                                {{ t('Modifier') }}
                            </button>
                        </div>
                        <p v-if="!isAuthenticated" class="rounded-md bg-creme p-3 text-sm text-encre-500">
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
                                <!-- Un bouton grise sans explication ressemble a
                                     une panne : on dit ce qui manque. -->
                                <p v-if="form.client_phone && !telephoneValide"
                                    class="mt-1 text-sm text-amber-700">
                                    {{ t('Format attendu : 05 55 12 34 56 ou +213 5 55 12 34 56.') }}
                                </p>
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
                                class="mt-1 block w-full rounded-md border-encre-200 shadow-sm focus:border-marque focus:ring-marque"
                                placeholder="Heure d’arrivée souhaitée, précisions…"></textarea>
                        </div>
                    </div>

                    <!-- Étape 3 -->
                    <div v-show="step === 2" class="space-y-4 rounded-carte border border-encre-100 bg-white p-6">
                        <h2 class="font-semibold text-encre-900">{{ t("Vérifiez avant d'envoyer") }}</h2>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between"><dt class="text-encre-400">Véhicule</dt><dd class="text-encre-900">{{ vehicle.title }}</dd></div>
                            <div class="flex justify-between"><dt class="text-encre-400">{{ t('Du') }}</dt><dd class="text-encre-900">{{ form.start_date }}</dd></div>
                            <div class="flex justify-between"><dt class="text-encre-400">{{ t('Au') }}</dt><dd class="text-encre-900">{{ form.end_date }}</dd></div>
                            <div class="flex justify-between"><dt class="text-encre-400">{{ t('Retrait') }}</dt><dd class="text-encre-900">{{ vehicle.pickup }}</dd></div>
                            <div class="flex justify-between"><dt class="text-encre-400">{{ t('Nom') }}</dt><dd class="text-encre-900">{{ form.client_name }}</dd></div>
                            <div class="flex justify-between"><dt class="text-encre-400">{{ t('Téléphone') }}</dt><dd class="text-encre-900">{{ form.client_phone }}</dd></div>
                        </dl>

                        <div v-if="vehicle.agency.rental_conditions"
                            class="rounded-md bg-creme p-3 text-xs leading-relaxed text-encre-500">
                            <p class="font-semibold text-encre-800">Conditions de l’agence</p>
                            <p class="mt-1 whitespace-pre-line">{{ vehicle.agency.rental_conditions }}</p>
                        </div>

                        <label class="flex items-start gap-2">
                            <input v-model="form.accepts_conditions" type="checkbox"
                                class="mt-0.5 rounded border-encre-300 text-marque-600 focus:ring-marque" />
                            <span class="text-sm text-encre-700">
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

                        <p class="text-xs text-encre-400">
                            Aucun paiement en ligne : le règlement se fait à l’agence, au départ.
                            L’agence a 24 heures pour répondre.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button v-if="step > 0" type="button" @click="step--"
                            class="h-12 rounded-bouton border-2 border-encre-100 px-4 text-sm font-semibold text-encre-700 hover:border-marque hover:bg-marque-50">
                            {{ t('Précédent') }}
                        </button>
                        <button v-if="step < 2" type="button" @click="step++" :disabled="!canContinue"
                            class="ml-auto h-12 rounded-bouton bg-marque px-6 text-base font-bold text-white shadow-marque transition hover:bg-marque-600 disabled:opacity-40 disabled:shadow-none">
                            {{ t('Continuer') }}
                        </button>
                        <button v-else type="submit" :disabled="form.processing"
                            class="ml-auto h-12 rounded-bouton bg-marque px-6 text-base font-bold text-white shadow-marque transition hover:bg-marque-600 disabled:opacity-50">
                            {{ t('Envoyer la demande') }}
                        </button>
                    </div>
                </form>

                <!-- Le détail du calcul reste visible tout au long (§6.4). -->
                <aside class="h-fit rounded-carte border border-encre-100 bg-white p-5 lg:sticky lg:top-6">
                    <!-- `@error` : une photo renseignée mais absente du disque
                         affichait l'icône d'image brisée en tête du récapitulatif
                         (vu à la capture le 2026-11-11). Mieux vaut rien. -->
                    <img v-if="vehicle.cover_url && !photoCassee" :src="vehicle.cover_url" alt=""
                        @error="photoCassee = true"
                        class="mb-4 aspect-[4/3] w-full rounded-carte object-cover" />

                    <p v-if="quoting" class="text-sm text-encre-400">{{ t('Calcul en cours…') }}</p>

                    <template v-else-if="quote && quote.days">
                        <p class="text-xs font-semibold uppercase tracking-wide text-encre-400">
                            {{ quote.days }} jour{{ quote.days > 1 ? 's' : '' }}
                        </p>
                        <ul class="mt-3 space-y-1.5 text-sm">
                            <li v-for="line in quote.lines" :key="line.label" class="flex justify-between">
                                <span class="text-encre-500">
                                    {{ line.label }} × {{ line.quantity }}
                                    <span class="text-encre-300">({{ price(line.unit_price) }} DA)</span>
                                </span>
                                <span class="text-encre-900">{{ price(line.total) }} DA</span>
                            </li>
                        </ul>
                        <div class="mt-3 flex justify-between border-t border-encre-100 pt-3">
                            <span class="font-semibold text-encre-900">{{ t('Total') }}</span>
                            <span class="text-lg font-bold text-encre-900">{{ price(quote.total) }} DA</span>
                        </div>
                        <p v-if="quote.deposit" class="mt-2 text-xs text-encre-400">
                            + caution de {{ price(quote.deposit) }} DA, rendue au retour
                        </p>
                    </template>

                    <p v-else class="text-sm text-encre-400">
                        {{ t('Choisissez vos dates pour voir le prix.') }}
                    </p>

                    <p class="mt-4 border-t border-encre-100 pt-3 text-xs text-encre-400">
                        Agence : {{ vehicle.agency.commercial_name }}<br>
                        Tél. {{ vehicle.agency.phone }}
                    </p>
                </aside>
            </div>
        </div>
    </div>
</template>
