<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    subscription: { type: Object, default: null },
    plans: { type: Array, default: () => [] },
    usage: { type: Object, required: true },
    pendingRequest: { type: Object, default: null },
    history: { type: Array, default: () => [] },
    canWrite: { type: Boolean, default: true },
});

const price = (v) => Number(v ?? 0).toLocaleString('fr-DZ');

const LEVELS = { basic: 'Basiques', advanced: 'Avancées', premium: 'Complètes' };

// Les lignes du tableau comparatif se lisent dans les formules elles-mêmes :
// aucune valeur n'est écrite ici, sinon elle mentirait dès que l'administrateur
// modifierait la matrice (§4.1).
const ROWS = [
    { label: 'Prix par an', get: (p) => (p.price_dzd ? `${price(p.price_dzd)} DA` : 'Gratuit') },
    { label: 'Annonces actives', get: (p) => p.max_listings ?? 'Illimité' },
    { label: 'Photos par annonce', get: (p) => p.max_photos },
    { label: 'Utilisateurs du compte', get: (p) => p.max_users },
    { label: 'Priorité commune', get: (p) => p.has_commune_priority },
    { label: 'Priorité wilaya', get: (p) => p.has_wilaya_priority },
    { label: 'Mise en avant sur l’accueil', get: (p) => p.has_homepage_feature },
    { label: 'Réponse aux avis', get: (p) => p.can_reply_reviews },
    { label: 'Statistiques', get: (p) => LEVELS[p.stats_level] ?? p.stats_level },
];

const expiringSoon = computed(
    () => props.subscription?.days_left !== null && props.subscription?.days_left <= 15,
);

const asking = ref(null);
const form = useForm({ requested_plan_id: null, agency_message: '' });

const openRequest = (plan) => {
    form.reset();
    form.clearErrors();
    form.requested_plan_id = plan.id;
    asking.value = plan;
};

const submitRequest = () => {
    form.post(route('agency.subscription.request'), {
        preserveScroll: true,
        onSuccess: () => (asking.value = null),
    });
};
</script>

<template>
    <Head title="Mon abonnement" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Mon abonnement</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <!-- Formule en cours -->
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Formule actuelle</p>
                            <p class="mt-1 flex items-center gap-2 text-2xl font-bold text-gray-900">
                                {{ subscription?.plan.name ?? '—' }}
                                <span v-if="subscription?.plan.badge_color"
                                    class="inline-block h-3 w-3 rounded-full"
                                    :style="{ backgroundColor: subscription.plan.badge_color }" />
                            </p>
                            <p v-if="subscription" class="mt-1 text-sm text-gray-600">
                                Depuis le {{ subscription.starts_at }}
                                <template v-if="subscription.ends_at">
                                    — jusqu’au {{ subscription.ends_at }}
                                </template>
                                <template v-else>— sans échéance</template>
                            </p>
                        </div>

                        <div class="text-right">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Annonces actives</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900">
                                {{ usage.used_listings }}
                                <span class="text-base font-normal text-gray-500">
                                    / {{ usage.max_listings ?? '∞' }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-500">{{ usage.max_photos }} photo(s) par annonce</p>
                        </div>
                    </div>

                    <!-- L'alerte d'échéance dit ce qui sera perdu, pas seulement
                         qu'une date approche. -->
                    <div v-if="expiringSoon"
                        class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        <p class="font-semibold">
                            <template v-if="subscription.days_left > 0">
                                Votre formule expire dans {{ subscription.days_left }} jour(s).
                            </template>
                            <template v-else>Votre formule a expiré.</template>
                        </p>
                        <p class="mt-1">
                            Sans renouvellement, votre compte repasse en Silver et les annonces qui
                            dépassent le quota sont archivées — les plus anciennes d’abord. Rien
                            n’est supprimé : photos et tarifs sont conservés.
                        </p>
                    </div>

                    <p v-if="subscription?.admin_note" class="mt-3 rounded bg-gray-50 p-3 text-sm text-gray-600">
                        {{ subscription.admin_note }}
                    </p>
                </div>

                <div v-if="pendingRequest"
                    class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                    <p class="font-semibold">Demande en cours de traitement</p>
                    <p class="mt-1">
                        Un administrateur vous recontactera pour le règlement. Aucun paiement ne se
                        fait en ligne.
                    </p>
                </div>

                <!-- Tableau comparatif -->
                <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Formules</th>
                                <th v-for="plan in plans" :key="plan.id" class="px-4 py-3 text-center">
                                    <span class="block font-semibold text-gray-900">{{ plan.name }}</span>
                                    <span v-if="plan.is_current"
                                        class="mt-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                        En cours
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in ROWS" :key="row.label">
                                <td class="px-4 py-2.5 text-gray-600">{{ row.label }}</td>
                                <td v-for="plan in plans" :key="plan.id" class="px-4 py-2.5 text-center">
                                    <template v-if="typeof row.get(plan) === 'boolean'">
                                        <span :class="row.get(plan) ? 'text-green-600' : 'text-gray-300'">
                                            {{ row.get(plan) ? '✓' : '—' }}
                                        </span>
                                    </template>
                                    <span v-else class="text-gray-900">{{ row.get(plan) }}</span>
                                </td>
                            </tr>
                            <tr v-if="canWrite">
                                <td class="px-4 py-3"></td>
                                <td v-for="plan in plans" :key="plan.id" class="px-4 py-3 text-center">
                                    <button v-if="!plan.is_current" @click="openRequest(plan)"
                                        :disabled="!!pendingRequest"
                                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-40">
                                        Demander
                                    </button>
                                    <span v-else class="text-xs text-gray-400">Formule actuelle</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-500">
                    Le règlement se fait hors plateforme (virement ou versement). Un administrateur
                    active la formule dès réception.
                </p>

                <div v-if="history.length" class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Mes demandes</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="(item, index) in history" :key="index"
                            class="flex flex-wrap items-baseline gap-2 border-b border-gray-50 pb-2 last:border-0">
                            <span class="font-medium text-gray-900">{{ item.plan }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-amber-100 text-amber-800': item.status === 'pending',
                                    'bg-green-100 text-green-800': item.status === 'accepted',
                                    'bg-red-100 text-red-800': item.status === 'refused',
                                }">
                                {{ { pending: 'En attente', accepted: 'Acceptée', refused: 'Refusée' }[item.status] }}
                            </span>
                            <span class="text-gray-400">{{ item.created_at }}</span>
                            <span v-if="item.admin_response" class="w-full text-gray-600">
                                {{ item.admin_response }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div v-if="asking" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="asking = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Demander la formule {{ asking.name }}</h3>
                <p class="mt-1 text-sm text-gray-600">
                    <template v-if="asking.price_dzd">{{ price(asking.price_dzd) }} DA par an. </template>
                    Un administrateur vous recontactera pour le règlement, qui se fait hors
                    plateforme.
                </p>

                <textarea v-model="form.agency_message" rows="3"
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Message facultatif : vos coordonnées de facturation, une question…"></textarea>
                <p v-if="form.errors.requested_plan_id" class="mt-1 text-sm text-red-600">
                    {{ form.errors.requested_plan_id }}
                </p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="asking = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="submitRequest" :disabled="form.processing"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        Envoyer la demande
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
