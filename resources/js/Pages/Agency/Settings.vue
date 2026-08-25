<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import LocationMap from '@/Components/LocationMap.vue';
import TextInput from '@/Components/TextInput.vue';
import { useCommunes } from '@/composables/useCommunes';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    agency: { type: Object, required: true },
    wilayas: { type: Array, default: () => [] },
    days: { type: Object, default: () => ({}) },
    fallbackPosition: { type: Object, required: true },
});

const a = props.agency;

const form = useForm({
    commercial_name: a.commercial_name,
    manager_name: a.manager_name,
    wilaya_id: a.wilaya_id,
    commune_id: a.commune_id,
    address: a.address,
    phone: a.phone,
    whatsapp: a.whatsapp ?? '',
    description: a.description ?? '',
    logo: null,
    latitude: a.latitude,
    longitude: a.longitude,
    rental_conditions: a.rental_conditions ?? '',
    min_driver_age: a.min_driver_age,
    default_deposit_dzd: a.default_deposit_dzd,
    buffer_hours: a.buffer_hours,
    opening_hours: { ...a.opening_hours },
});

const wilayaId = computed(() => form.wilaya_id);
const { communes, loading: loadingCommunes } = useCommunes(wilayaId, props.wilayas, {
    onChange: (list) => {
        if (!list.some((c) => c.id === Number(form.commune_id))) form.commune_id = '';
    },
});

const positioned = computed(() => form.latitude !== null && form.longitude !== null);

const onMapMove = ({ latitude, longitude }) => {
    form.latitude = latitude;
    form.longitude = longitude;
};

const clearPosition = () => {
    form.latitude = null;
    form.longitude = null;
};

const logoPreview = ref(a.logo_url);

const onLogo = (event) => {
    const file = event.target.files[0];
    form.logo = file ?? null;
    if (file) logoPreview.value = URL.createObjectURL(file);
};

const submit = () => {
    // forceFormData : le logo est un fichier, et Inertia doit passer en
    // multipart même quand aucun fichier n'a été choisi cette fois-ci.
    form.post(route('agency.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (form.logo = null),
    });
};
</script>

<template>
    <Head title="Paramètres de l’agence" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Paramètres de l’agence</h2>
                <a :href="agency.public_url" target="_blank" rel="noopener"
                    class="text-sm text-gray-600 hover:underline">
                    Voir ma fiche publique
                </a>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Identité -->
                    <div class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Identité</h3>

                        <div class="flex flex-wrap items-center gap-4">
                            <img v-if="logoPreview" :src="logoPreview" alt=""
                                class="h-16 w-16 rounded-full object-cover" />
                            <div v-else class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-sm text-gray-500">
                                Logo
                            </div>
                            <input type="file" accept="image/*" @change="onLogo"
                                class="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-indigo-700" />
                            <InputError :message="form.errors.logo" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="commercial_name" value="Nom commercial" />
                                <TextInput id="commercial_name" v-model="form.commercial_name" class="mt-1 block w-full" />
                                <InputError :message="form.errors.commercial_name" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="manager_name" value="Nom du gérant" />
                                <TextInput id="manager_name" v-model="form.manager_name" class="mt-1 block w-full" />
                                <InputError :message="form.errors.manager_name" class="mt-1" />
                            </div>
                        </div>

                        <!-- Registre de commerce et NIF ne sont pas modifiables :
                             ils ont servi à valider l'agence, les changer sans
                             nouvelle vérification viderait la modération de son sens. -->
                        <div class="grid gap-4 rounded-md bg-gray-50 p-3 text-sm sm:grid-cols-2">
                            <p class="text-gray-600">
                                Registre de commerce :
                                <span class="font-medium text-gray-900">{{ agency.trade_register_number }}</span>
                            </p>
                            <p class="text-gray-600">
                                NIF : <span class="font-medium text-gray-900">{{ agency.nif ?? '—' }}</span>
                            </p>
                            <p class="text-xs text-gray-500 sm:col-span-2">
                                Ces informations ont servi à vérifier votre agence. Écrivez-nous pour
                                les corriger.
                            </p>
                        </div>

                        <div>
                            <InputLabel for="description" value="Présentation" />
                            <textarea id="description" v-model="form.description" rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Votre flotte, vos services, ce qui vous distingue…"></textarea>
                            <InputError :message="form.errors.description" class="mt-1" />
                        </div>
                    </div>

                    <!-- Adresse et position -->
                    <div class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Adresse et position</h3>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="wilaya" value="Wilaya" />
                                <select id="wilaya" v-model="form.wilaya_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option v-for="w in wilayas" :key="w.id" :value="w.id">
                                        {{ w.code }} — {{ w.name_fr }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.wilaya_id" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="commune" value="Commune" />
                                <select id="commune" v-model="form.commune_id" :disabled="loadingCommunes"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                                    <option value="">{{ loadingCommunes ? 'Chargement…' : '— Choisir —' }}</option>
                                    <option v-for="c in communes" :key="c.id" :value="c.id">{{ c.name_fr }}</option>
                                </select>
                                <InputError :message="form.errors.commune_id" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="address" value="Adresse" />
                            <TextInput id="address" v-model="form.address" class="mt-1 block w-full" />
                            <InputError :message="form.errors.address" class="mt-1" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="phone" value="Téléphone" />
                                <TextInput id="phone" v-model="form.phone" class="mt-1 block w-full" />
                                <InputError :message="form.errors.phone" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="whatsapp" value="WhatsApp (facultatif)" />
                                <TextInput id="whatsapp" v-model="form.whatsapp" class="mt-1 block w-full" />
                                <InputError :message="form.errors.whatsapp" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel value="Position sur la carte" />
                                <button v-if="positioned" type="button" @click="clearPosition"
                                    class="text-xs text-gray-500 hover:text-gray-900">
                                    Retirer le point
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Cliquez sur la carte pour placer votre agence, ou déplacez le marqueur.
                                Sans point, aucune carte n’apparaît sur votre fiche publique — mieux
                                vaut rien qu’une adresse fausse.
                            </p>
                            <div class="mt-2 overflow-hidden rounded-lg border border-gray-200">
                                <LocationMap editable
                                    :latitude="form.latitude ?? fallbackPosition.latitude"
                                    :longitude="form.longitude ?? fallbackPosition.longitude"
                                    :zoom="positioned ? 15 : 11"
                                    height="300px"
                                    @moved="onMapMove" />
                            </div>
                            <p v-if="positioned" class="mt-1 text-xs text-gray-500">
                                {{ form.latitude }}, {{ form.longitude }}
                            </p>
                            <InputError :message="form.errors.latitude" class="mt-1" />
                            <InputError :message="form.errors.longitude" class="mt-1" />
                        </div>
                    </div>

                    <!-- Conditions -->
                    <div class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Conditions de location</h3>
                        <p class="text-sm text-gray-600">
                            Ce que le client lit avant de valider sa demande, et ce qui figure sur le
                            bon de réservation.
                        </p>

                        <div>
                            <textarea v-model="form.rental_conditions" rows="5"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Pièces à fournir, carburant, franchise, restrictions de circulation…"></textarea>
                            <InputError :message="form.errors.rental_conditions" class="mt-1" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel for="age" value="Âge minimum du conducteur" />
                                <TextInput id="age" v-model="form.min_driver_age" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.min_driver_age" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="deposit" value="Caution (DA)" />
                                <TextInput id="deposit" v-model="form.default_deposit_dzd" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.default_deposit_dzd" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="buffer" value="Délai entre deux locations (h)" />
                                <TextInput id="buffer" v-model="form.buffer_hours" type="number" class="mt-1 block w-full" />
                                <p class="mt-1 text-xs text-gray-500">Nettoyage et contrôle du véhicule.</p>
                                <InputError :message="form.errors.buffer_hours" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <!-- Horaires -->
                    <div class="space-y-3 rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Horaires d’ouverture</h3>

                        <div v-for="(label, key) in days" :key="key"
                            class="flex flex-wrap items-center gap-3 border-b border-gray-50 py-1.5 last:border-0">
                            <span class="w-24 text-sm text-gray-700">{{ label }}</span>

                            <label class="flex items-center gap-1.5 text-sm text-gray-600">
                                <input type="checkbox" v-model="form.opening_hours[key].closed"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                Fermé
                            </label>

                            <template v-if="!form.opening_hours[key].closed">
                                <input type="time" v-model="form.opening_hours[key].from"
                                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <span class="text-gray-400">→</span>
                                <input type="time" v-model="form.opening_hours[key].to"
                                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                            class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            Enregistrer
                        </button>
                        <Link :href="route('agency.dashboard')" class="text-sm text-gray-600 hover:underline">
                            Retour au tableau de bord
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
