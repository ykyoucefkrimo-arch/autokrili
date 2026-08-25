<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ agency: { type: Object, required: true } });
</script>

<template>
    <Head title="Compte en vérification" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">

                <!-- En attente -->
                <div v-if="agency.status === 'pending'" class="rounded-lg bg-white p-8 text-center shadow-sm">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
                        <svg class="h-7 w-7 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                            <circle cx="12" cy="12" r="9" />
                        </svg>
                    </div>
                    <h1 class="mt-5 text-lg font-semibold text-gray-900">
                        Votre compte est en cours de vérification
                    </h1>
                    <p class="mt-3 text-sm leading-relaxed text-gray-600">
                        La demande de <strong>{{ agency.commercial_name }}</strong> nous est parvenue
                        le {{ agency.submitted_at }}. Nous vérifions votre registre de commerce
                        et vous répondons sous 48&nbsp;heures ouvrées.
                    </p>
                    <p class="mt-3 text-sm text-gray-600">
                        Vous recevrez un email dès que votre agence sera approuvée. Vous pourrez
                        alors publier vos véhicules.
                    </p>
                </div>

                <!-- Rejetée : le motif est ce qui permet de corriger -->
                <div v-else-if="agency.status === 'rejected'" class="rounded-lg bg-white p-8 shadow-sm">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-7 w-7 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <h1 class="mt-5 text-center text-lg font-semibold text-gray-900">
                        Votre inscription n'a pas été retenue
                    </h1>
                    <div class="mt-5 rounded-md border border-red-200 bg-red-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-red-800">Motif</p>
                        <p class="mt-1 text-sm text-red-900">{{ agency.rejection_reason }}</p>
                    </div>
                    <p class="mt-4 text-center text-sm text-gray-600">
                        Corrigez les points signalés puis écrivez-nous à
                        <a href="mailto:contact@autokrili.dz" class="underline">contact@autokrili.dz</a>
                        pour un nouvel examen.
                    </p>
                </div>

                <!-- Suspendue : le tableau de bord reste lisible -->
                <div v-else class="rounded-lg bg-white p-8 shadow-sm">
                    <h1 class="text-center text-lg font-semibold text-gray-900">
                        Votre compte est suspendu
                    </h1>
                    <div class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Motif</p>
                        <p class="mt-1 text-sm text-amber-900">{{ agency.rejection_reason }}</p>
                    </div>
                    <p class="mt-4 text-center text-sm text-gray-600">
                        Vos annonces ne sont plus visibles du public. Votre tableau de bord reste
                        consultable en lecture seule.
                    </p>
                </div>

                <p class="mt-6 text-center text-sm">
                    <Link :href="route('profile.edit')" class="text-gray-600 underline hover:text-gray-900">
                        Modifier mes informations
                    </Link>
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
