<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useCommunes } from '@/composables/useCommunes';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    vehicle: { type: Object, default: null },
    wilayas: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    quota: { type: Object, required: true },
});

const isEdit = computed(() => props.vehicle !== null);

const LABELS = {
    citadine: 'Citadine', berline: 'Berline', suv: 'SUV', utilitaire: 'Utilitaire',
    '4x4': '4x4', luxe: 'Luxe', minibus: 'Minibus',
    manuelle: 'Manuelle', automatique: 'Automatique',
    essence: 'Essence', diesel: 'Diesel', gpl: 'GPL', hybride: 'Hybride', electrique: 'Électrique',
};

const v = props.vehicle;

const form = useForm({
    brand: v?.brand ?? '',
    model: v?.model ?? '',
    year: v?.year ?? new Date().getFullYear(),
    category: v?.category ?? 'citadine',
    transmission: v?.transmission ?? 'manuelle',
    fuel: v?.fuel ?? 'diesel',
    seats: v?.seats ?? 5,
    doors: v?.doors ?? 5,
    air_conditioning: v?.air_conditioning ?? true,
    mileage_limit_per_day: v?.mileage_limit_per_day ?? null,
    description: v?.description ?? '',
    pickup_wilaya_id: v?.pickup_wilaya_id ?? '',
    pickup_commune_id: v?.pickup_commune_id ?? '',
    with_driver_available: v?.with_driver_available ?? false,
    driver_price_per_day: v?.driver_price_per_day ?? null,
    pricing: {
        daily: v?.pricing?.daily ?? null,
        weekly: v?.pricing?.weekly ?? null,
        monthly: v?.pricing?.monthly ?? null,
    },
});

// Chargées à la demande : 1541 communes livrées avec le formulaire seraient
// payées par l'agence à chaque annonce créée.
const pickupWilaya = computed(() => form.pickup_wilaya_id);
const { communes, loading: loadingCommunes } = useCommunes(pickupWilaya, props.wilayas, {
    // La commune retenue appartiendrait à l'ancienne wilaya : le serveur la
    // refuserait, autant la vider tout de suite.
    onChange: (list) => {
        if (!list.some((c) => c.id === Number(form.pickup_commune_id))) {
            form.pickup_commune_id = '';
        }
    },
});

const STEPS = [
    { key: 'vehicle', label: 'Le véhicule', fields: ['brand', 'model', 'year', 'category', 'transmission', 'fuel', 'seats', 'doors', 'mileage_limit_per_day', 'description'] },
    { key: 'pickup', label: 'Retrait et options', fields: ['pickup_wilaya_id', 'pickup_commune_id', 'driver_price_per_day'] },
    { key: 'pricing', label: 'Tarifs', fields: ['pricing.daily', 'pricing.weekly', 'pricing.monthly'] },
    { key: 'photos', label: 'Photos', fields: [] },
];

const step = ref(0);

// Une étape qui porte une erreur doit se signaler : sinon l'agence cherche
// dans le formulaire ce que le serveur a déjà pointé du doigt.
const stepHasError = (index) => STEPS[index].fields.some((f) => form.errors[f]);

const save = () => {
    if (isEdit.value) {
        form.put(route('agency.vehicles.update', props.vehicle.id), { preserveScroll: true });
    } else {
        form.post(route('agency.vehicles.store'));
    }
};

/* Photos — un formulaire à part : elles s'envoient à la pièce, sans attendre
   que le reste de l'annonce soit valide. */
const photoInput = ref(null);
const photoForm = useForm({ photos: [] });
const remaining = computed(() => props.quota.max_photos - (props.vehicle?.photos?.length ?? 0));

const uploadPhotos = (event) => {
    photoForm.photos = Array.from(event.target.files);
    if (!photoForm.photos.length) return;

    photoForm.post(route('agency.vehicles.photos.store', props.vehicle.id), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            photoForm.reset();
            if (photoInput.value) photoInput.value.value = '';
        },
    });
};

const setCover = (photo) =>
    router.post(route('agency.vehicles.photos.cover', [props.vehicle.id, photo.id]), {}, { preserveScroll: true });

const deletePhoto = (photo) =>
    router.delete(route('agency.vehicles.photos.destroy', [props.vehicle.id, photo.id]), { preserveScroll: true });

const submitForReview = () =>
    router.post(route('agency.vehicles.submit', props.vehicle.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="isEdit ? vehicle.title : 'Nouvelle annonce'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? vehicle.title : 'Nouvelle annonce' }}
                </h2>
                <Link :href="route('agency.vehicles.index')" class="text-sm text-gray-600 hover:underline">
                    Retour à mes véhicules
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="form.errors.quota || $page.props.errors?.submit"
                    class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    {{ form.errors.quota || $page.props.errors.submit }}
                </div>

                <div v-if="isEdit && vehicle.status === 'rejected' && vehicle.rejection_reason"
                    class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Annonce refusée</p>
                    <p class="mt-1">{{ vehicle.rejection_reason }}</p>
                    <p class="mt-1 text-red-700">Corrigez puis soumettez-la de nouveau.</p>
                </div>

                <!-- Étapes : le formulaire est long, le découper évite la page
                     de quarante champs que personne ne remplit jusqu'au bout. -->
                <nav class="flex flex-wrap gap-2 rounded-lg bg-white p-2 shadow-sm">
                    <button v-for="(s, index) in STEPS" :key="s.key" type="button"
                        @click="step = index"
                        :disabled="s.key === 'photos' && !isEdit"
                        class="rounded-md px-3 py-1.5 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-40"
                        :class="step === index ? 'bg-indigo-600 text-white'
                            : stepHasError(index) ? 'bg-red-50 text-red-700' : 'text-gray-600 hover:bg-gray-50'">
                        {{ index + 1 }}. {{ s.label }}
                    </button>
                </nav>

                <form @submit.prevent="save" class="space-y-4">
                    <!-- Étape 1 -->
                    <div v-show="step === 0" class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel for="brand" value="Marque" />
                                <TextInput id="brand" v-model="form.brand" class="mt-1 block w-full" placeholder="Renault" />
                                <InputError :message="form.errors.brand" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="model" value="Modèle" />
                                <TextInput id="model" v-model="form.model" class="mt-1 block w-full" placeholder="Clio 5" />
                                <InputError :message="form.errors.model" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="year" value="Année" />
                                <TextInput id="year" v-model="form.year" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.year" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel for="category" value="Catégorie" />
                                <select id="category" v-model="form.category"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option v-for="c in options.categories" :key="c" :value="c">{{ LABELS[c] }}</option>
                                </select>
                                <InputError :message="form.errors.category" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="transmission" value="Boîte de vitesses" />
                                <select id="transmission" v-model="form.transmission"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option v-for="t in options.transmissions" :key="t" :value="t">{{ LABELS[t] }}</option>
                                </select>
                                <InputError :message="form.errors.transmission" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="fuel" value="Carburant" />
                                <select id="fuel" v-model="form.fuel"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option v-for="f in options.fuels" :key="f" :value="f">{{ LABELS[f] }}</option>
                                </select>
                                <InputError :message="form.errors.fuel" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-4">
                            <div>
                                <InputLabel for="seats" value="Places" />
                                <TextInput id="seats" v-model="form.seats" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.seats" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="doors" value="Portes" />
                                <TextInput id="doors" v-model="form.doors" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.doors" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="mileage" value="Km / jour inclus" />
                                <TextInput id="mileage" v-model="form.mileage_limit_per_day" type="number"
                                    class="mt-1 block w-full" placeholder="Illimité si vide" />
                                <InputError :message="form.errors.mileage_limit_per_day" class="mt-1" />
                            </div>
                            <label class="flex items-end gap-2 pb-2">
                                <input v-model="form.air_conditioning" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Climatisation</span>
                            </label>
                        </div>

                        <div>
                            <InputLabel for="description" value="Description" />
                            <textarea id="description" v-model="form.description" rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="État du véhicule, équipements, conditions particulières…"></textarea>
                            <InputError :message="form.errors.description" class="mt-1" />
                        </div>
                    </div>

                    <!-- Étape 2 -->
                    <div v-show="step === 1" class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-600">
                            Le point de retrait est porté par l'annonce : si vous avez plusieurs
                            agences, publiez chaque véhicule dans sa commune.
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="wilaya" value="Wilaya de retrait" />
                                <select id="wilaya" v-model="form.pickup_wilaya_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Choisir…</option>
                                    <option v-for="w in wilayas" :key="w.id" :value="w.id">{{ w.code }} — {{ w.name_fr }}</option>
                                </select>
                                <InputError :message="form.errors.pickup_wilaya_id" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="commune" value="Commune de retrait" />
                                <select id="commune" v-model="form.pickup_commune_id" :disabled="!communes.length || loadingCommunes"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                                    <option value="">Choisir…</option>
                                    <option v-for="c in communes" :key="c.id" :value="c.id">{{ c.name_fr }}</option>
                                </select>
                                <InputError :message="form.errors.pickup_commune_id" class="mt-1" />
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-4">
                            <label class="flex items-center gap-2">
                                <input v-model="form.with_driver_available" type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm font-medium text-gray-800">Proposer ce véhicule avec chauffeur</span>
                            </label>
                            <div v-if="form.with_driver_available" class="mt-3 max-w-xs">
                                <InputLabel for="driver_price" value="Prix du chauffeur (DA / jour)" />
                                <TextInput id="driver_price" v-model="form.driver_price_per_day" type="number"
                                    class="mt-1 block w-full" />
                                <InputError :message="form.errors.driver_price_per_day" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <!-- Étape 3 -->
                    <div v-show="step === 2" class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                        <p class="text-sm text-gray-600">
                            Le tarif journalier est obligatoire : il sert de base à tous les calculs.
                            Les tarifs semaine et mois sont des remises — laissez-les vides si vous
                            n'en proposez pas.
                        </p>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel for="daily" value="Par jour (DA)" />
                                <TextInput id="daily" v-model="form.pricing.daily" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors['pricing.daily']" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="weekly" value="Par semaine (DA)" />
                                <TextInput id="weekly" v-model="form.pricing.weekly" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors['pricing.weekly']" class="mt-1" />
                                <p v-if="form.pricing.daily" class="mt-1 text-xs text-gray-500">
                                    Sans remise : {{ (form.pricing.daily * 7).toLocaleString('fr-DZ') }} DA
                                </p>
                            </div>
                            <div>
                                <InputLabel for="monthly" value="Par mois (DA)" />
                                <TextInput id="monthly" v-model="form.pricing.monthly" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors['pricing.monthly']" class="mt-1" />
                                <p v-if="form.pricing.daily" class="mt-1 text-xs text-gray-500">
                                    Sans remise : {{ (form.pricing.daily * 30).toLocaleString('fr-DZ') }} DA
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Étape 4 : les photos vivent hors du formulaire principal -->
                    <div v-show="step === 3" class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                        <template v-if="isEdit">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm text-gray-700">
                                    {{ vehicle.photos.length }} / {{ quota.max_photos }} photo(s) —
                                    formule {{ quota.plan_name }}
                                </p>
                                <input ref="photoInput" type="file" multiple accept="image/*"
                                    :disabled="remaining <= 0" @change="uploadPhotos"
                                    class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-indigo-700 disabled:opacity-50" />
                            </div>

                            <p v-if="remaining <= 0" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                                {{ quota.photo_message }}
                            </p>
                            <InputError :message="photoForm.errors.photos" />
                            <p v-if="photoForm.progress" class="text-sm text-gray-500">
                                Envoi… {{ photoForm.progress.percentage }} %
                            </p>

                            <div v-if="vehicle.photos.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div v-for="photo in vehicle.photos" :key="photo.id"
                                    class="overflow-hidden rounded-lg border"
                                    :class="photo.is_cover ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200'">
                                    <img :src="photo.url" alt="" class="aspect-[4/3] w-full object-cover" />
                                    <div class="flex items-center justify-between p-1.5">
                                        <button type="button" @click="setCover(photo)" :disabled="photo.is_cover"
                                            class="text-xs font-medium text-indigo-600 disabled:text-gray-400">
                                            {{ photo.is_cover ? 'Couverture' : 'Mettre en couverture' }}
                                        </button>
                                        <button type="button" @click="deletePhoto(photo)"
                                            class="text-xs text-red-600 hover:underline">Supprimer</button>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-500">
                                Aucune photo. Une annonce sans photo ne peut pas être soumise à la modération.
                            </p>
                        </template>
                        <p v-else class="text-sm text-gray-600">
                            Enregistrez d'abord l'annonce : les photos s'ajoutent ensuite, sur la
                            fiche créée.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" v-if="step > 0" @click="step--"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Précédent
                        </button>
                        <button type="button" v-if="step < STEPS.length - 1" @click="step++"
                            :disabled="STEPS[step + 1].key === 'photos' && !isEdit"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40">
                            Suivant
                        </button>

                        <button type="submit" :disabled="form.processing"
                            class="ml-auto rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            {{ isEdit ? 'Enregistrer' : 'Créer l’annonce' }}
                        </button>
                        <button v-if="isEdit && ['draft', 'rejected', 'archived'].includes(vehicle.status)"
                            type="button" @click="submitForReview"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                            Soumettre à la modération
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
