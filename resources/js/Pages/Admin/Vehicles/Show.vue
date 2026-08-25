<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    vehicle: { type: Object, required: true },
    reasons: { type: Object, default: () => ({}) },
});

const LABELS = {
    citadine: 'Citadine', berline: 'Berline', suv: 'SUV', utilitaire: 'Utilitaire',
    '4x4': '4x4', luxe: 'Luxe', minibus: 'Minibus',
    manuelle: 'Manuelle', automatique: 'Automatique',
    essence: 'Essence', diesel: 'Diesel', gpl: 'GPL', hybride: 'Hybride', electrique: 'Électrique',
    daily: 'Par jour', weekly: 'Par semaine', monthly: 'Par mois',
};

const STATUSES = {
    pending: { label: 'En attente de modération', classes: 'bg-amber-100 text-amber-800' },
    published: { label: 'En ligne', classes: 'bg-green-100 text-green-800' },
    rejected: { label: 'Refusée', classes: 'bg-red-100 text-red-800' },
    draft: { label: 'Brouillon', classes: 'bg-gray-200 text-gray-700' },
    archived: { label: 'Archivée', classes: 'bg-gray-100 text-gray-500' },
};

const active = ref(props.vehicle.photos.find((p) => p.is_cover) ?? props.vehicle.photos[0] ?? null);

const rejecting = ref(false);
const rejectForm = useForm({ reason_code: '', reason_note: '' });

const approve = () =>
    router.post(route('admin.vehicles.approve', props.vehicle.id), {}, { preserveScroll: true });

const confirmReject = () =>
    rejectForm.post(route('admin.vehicles.reject', props.vehicle.id), {
        preserveScroll: true,
        onSuccess: () => (rejecting.value = false),
    });
</script>

<template>
    <Head :title="vehicle.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ vehicle.title }}</h2>
                <Link :href="route('admin.vehicles.index')" class="text-sm text-gray-600 hover:underline">
                    Retour à la file
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <div class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="STATUSES[vehicle.status].classes">
                        {{ STATUSES[vehicle.status].label }}
                    </span>
                    <p class="text-sm text-gray-600">
                        <Link :href="route('admin.agencies.show', vehicle.agency.id)" class="font-medium text-gray-900 hover:underline">
                            {{ vehicle.agency.commercial_name }}
                        </Link>
                        — {{ vehicle.agency.wilaya }} · soumise le {{ vehicle.submitted_at }}
                    </p>
                    <span v-if="vehicle.agency.is_trusted"
                        class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                        Agence de confiance
                    </span>

                    <div class="ml-auto flex gap-2">
                        <button v-if="vehicle.status !== 'published'" @click="approve"
                            class="rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">
                            Approuver
                        </button>
                        <button v-if="vehicle.status !== 'rejected'" @click="rejecting = true"
                            class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                            Rejeter
                        </button>
                    </div>
                </div>

                <div v-if="vehicle.rejection_reason"
                    class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <span class="font-semibold">Motif du dernier refus :</span> {{ vehicle.rejection_reason }}
                </div>

                <!-- Prévisualisation : ce que verra le visiteur, pas un tableau
                     de champs. Approuver sur un résumé, c'est approuver sans regarder. -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="space-y-3 lg:col-span-2">
                        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                            <div class="aspect-[16/10] bg-gray-100">
                                <img v-if="active" :src="active.url" alt="" class="h-full w-full object-contain" />
                                <div v-else class="flex h-full items-center justify-center text-sm text-gray-400">
                                    Aucune photo — cette annonce ne devrait pas être publiable
                                </div>
                            </div>
                            <div v-if="vehicle.photos.length > 1" class="flex gap-2 overflow-x-auto p-2">
                                <button v-for="photo in vehicle.photos" :key="photo.id" @click="active = photo"
                                    class="shrink-0 overflow-hidden rounded border-2"
                                    :class="active?.id === photo.id ? 'border-indigo-500' : 'border-transparent'">
                                    <img :src="photo.url" alt="" class="h-14 w-20 object-cover" />
                                </button>
                            </div>
                        </div>

                        <div v-if="vehicle.description" class="rounded-lg bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">Description</h3>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ vehicle.description }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">Caractéristiques</h3>
                            <dl class="mt-2 space-y-1 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">Catégorie</dt><dd class="text-gray-900">{{ LABELS[vehicle.category] }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Boîte</dt><dd class="text-gray-900">{{ LABELS[vehicle.transmission] }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Carburant</dt><dd class="text-gray-900">{{ LABELS[vehicle.fuel] }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Places / portes</dt><dd class="text-gray-900">{{ vehicle.seats }} / {{ vehicle.doors }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Climatisation</dt><dd class="text-gray-900">{{ vehicle.air_conditioning ? 'Oui' : 'Non' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Km / jour</dt><dd class="text-gray-900">{{ vehicle.mileage_limit_per_day ?? 'Illimité' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Retrait</dt><dd class="text-right text-gray-900">{{ vehicle.pickup }}</dd></div>
                            </dl>
                        </div>

                        <div class="rounded-lg bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">Tarifs</h3>
                            <dl class="mt-2 space-y-1 text-sm">
                                <div v-for="rule in vehicle.pricing" :key="rule.duration_type" class="flex justify-between">
                                    <dt class="text-gray-500">{{ LABELS[rule.duration_type] }}</dt>
                                    <dd class="font-medium text-gray-900">{{ rule.price_dzd.toLocaleString('fr-DZ') }} DA</dd>
                                </div>
                                <div v-if="vehicle.with_driver_available" class="flex justify-between border-t border-gray-100 pt-1">
                                    <dt class="text-gray-500">Avec chauffeur</dt>
                                    <dd class="font-medium text-gray-900">
                                        + {{ vehicle.driver_price_per_day.toLocaleString('fr-DZ') }} DA / jour
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="rejecting = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Rejeter « {{ vehicle.title }} »</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Le motif part par email à l'agence, qui pourra corriger et soumettre à nouveau.
                </p>

                <div class="mt-3 space-y-2">
                    <label v-for="(label, code) in reasons" :key="code" class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" :value="code" v-model="rejectForm.reason_code"
                            class="border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        {{ label }}
                    </label>
                </div>
                <p v-if="rejectForm.errors.reason_code" class="mt-1 text-sm text-red-600">{{ rejectForm.errors.reason_code }}</p>

                <textarea v-model="rejectForm.reason_note" rows="3"
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Précision facultative : ce qui doit être corrigé."></textarea>
                <p v-if="rejectForm.errors.reason_note" class="mt-1 text-sm text-red-600">{{ rejectForm.errors.reason_note }}</p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="rejecting = false"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="confirmReject" :disabled="rejectForm.processing"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Confirmer le rejet
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
