<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    plans: { type: Array, default: () => [] },
    requests: { type: Array, default: () => [] },
    expiring: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
});

const price = (v) => Number(v ?? 0).toLocaleString('fr-DZ');

const LEVELS = { basic: 'Basiques', advanced: 'Avancées', premium: 'Complètes' };

/* Édition d'une formule. Les valeurs viennent de la base (§4.1) : cet écran est
   ce qui rend vraie la promesse qu'elles n'y sont pas codées en dur. */
const editing = ref(null);
const planForm = useForm({
    name: '', price_dzd: 0, max_listings: null, max_photos: 1, max_users: 1,
    has_commune_priority: false, has_wilaya_priority: false, has_homepage_feature: false,
    can_reply_reviews: false, stats_level: 'basic', badge_label: '', badge_color: '',
    description: '', is_active: true,
});

const openPlan = (plan) => {
    planForm.clearErrors();
    Object.keys(planForm.data()).forEach((key) => {
        planForm[key] = plan[key];
    });
    editing.value = plan;
};

const savePlan = () => {
    planForm.put(route('admin.plans.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
};

/* Attribution manuelle : aucun paiement en ligne en v1 (§4.3). */
const granting = ref(null);
const grantForm = useForm({ plan_id: null, ends_at: '', note: '', request_id: null });

const openGrant = (request) => {
    grantForm.reset();
    grantForm.clearErrors();
    grantForm.plan_id = request.plan_id;
    grantForm.request_id = request.id;
    // Un an par défaut : c'est la durée que vend la plateforme.
    const inAYear = new Date();
    inAYear.setFullYear(inAYear.getFullYear() + 1);
    grantForm.ends_at = inAYear.toISOString().slice(0, 10);
    granting.value = request;
};

const submitGrant = () => {
    grantForm.post(route('admin.plans.grant', granting.value.agency_id), {
        preserveScroll: true,
        onSuccess: () => (granting.value = null),
    });
};

const refusing = ref(null);
const refuseForm = useForm({ admin_response: '' });

const openRefuse = (request) => {
    refuseForm.reset();
    refuseForm.clearErrors();
    refusing.value = request;
};

const submitRefuse = () => {
    refuseForm.post(route('admin.plans.requests.refuse', refusing.value.id), {
        preserveScroll: true,
        onSuccess: () => (refusing.value = null),
    });
};
</script>

<template>
    <Head title="Formules" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Formules</h2>
                <span v-if="counts.pending_requests"
                    class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                    {{ counts.pending_requests }} demande(s) à traiter
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <!-- Matrice -->
                <section>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Matrice des formules
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Ces valeurs pilotent les quotas, le classement des résultats et les badges.
                        Baisser un quota d’annonces archive immédiatement ce qui dépasse chez les
                        agences concernées, qui en sont prévenues.
                    </p>

                    <div class="mt-3 grid gap-4 lg:grid-cols-3">
                        <div v-for="plan in plans" :key="plan.id"
                            class="rounded-lg bg-white p-5 shadow-sm"
                            :class="plan.is_active ? '' : 'opacity-60'">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="flex items-center gap-2 text-lg font-bold text-gray-900">
                                        <span v-if="plan.badge_color" class="inline-block h-3 w-3 rounded-full"
                                            :style="{ backgroundColor: plan.badge_color }" />
                                        {{ plan.name }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ plan.price_dzd ? price(plan.price_dzd) + ' DA / an' : 'Gratuit' }}
                                    </p>
                                </div>
                                <button @click="openPlan(plan)"
                                    class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    Modifier
                                </button>
                            </div>

                            <dl class="mt-4 space-y-1 text-sm">
                                <div class="flex justify-between"><dt class="text-gray-500">Annonces</dt><dd class="text-gray-900">{{ plan.max_listings ?? 'Illimité' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Photos</dt><dd class="text-gray-900">{{ plan.max_photos }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Utilisateurs</dt><dd class="text-gray-900">{{ plan.max_users }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Priorité commune</dt><dd :class="plan.has_commune_priority ? 'text-green-600' : 'text-gray-300'">{{ plan.has_commune_priority ? '✓' : '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Priorité wilaya</dt><dd :class="plan.has_wilaya_priority ? 'text-green-600' : 'text-gray-300'">{{ plan.has_wilaya_priority ? '✓' : '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Accueil</dt><dd :class="plan.has_homepage_feature ? 'text-green-600' : 'text-gray-300'">{{ plan.has_homepage_feature ? '✓' : '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Réponse aux avis</dt><dd :class="plan.can_reply_reviews ? 'text-green-600' : 'text-gray-300'">{{ plan.can_reply_reviews ? '✓' : '—' }}</dd></div>
                                <div class="flex justify-between"><dt class="text-gray-500">Statistiques</dt><dd class="text-gray-900">{{ LEVELS[plan.stats_level] }}</dd></div>
                            </dl>

                            <p class="mt-4 border-t border-gray-100 pt-3 text-sm">
                                <span class="font-semibold text-gray-900">{{ plan.agencies_count }}</span>
                                <span class="text-gray-500"> agence(s) sur cette formule</span>
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Demandes -->
                <section>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Demandes de changement
                    </h3>

                    <div class="mt-3 overflow-hidden rounded-lg bg-white shadow-sm">
                        <div v-if="!requests.length" class="p-8 text-center">
                            <p class="font-medium text-gray-900">Aucune demande</p>
                            <p class="mt-1 text-sm text-gray-600">
                                Les agences demandent une formule depuis leur espace ; vous
                                l’attribuez ici une fois le règlement encaissé.
                            </p>
                        </div>

                        <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Agence</th>
                                    <th class="px-4 py-3">Formule demandée</th>
                                    <th class="px-4 py-3">Message</th>
                                    <th class="px-4 py-3">Statut</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="request in requests" :key="request.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <Link :href="route('admin.agencies.show', request.agency_id)"
                                            class="font-medium text-gray-900 hover:underline">
                                            {{ request.agency }}
                                        </Link>
                                        <p class="text-xs text-gray-500">{{ request.created_at }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ request.plan }}</td>
                                    <td class="max-w-xs px-4 py-3 text-gray-600">
                                        {{ request.agency_message ?? '—' }}
                                        <p v-if="request.admin_response" class="mt-1 text-xs text-gray-400">
                                            Réponse : {{ request.admin_response }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium"
                                            :class="{
                                                'bg-amber-100 text-amber-800': request.status === 'pending',
                                                'bg-green-100 text-green-800': request.status === 'accepted',
                                                'bg-red-100 text-red-800': request.status === 'refused',
                                            }">
                                            {{ { pending: 'En attente', accepted: 'Acceptée', refused: 'Refusée' }[request.status] }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <template v-if="request.status === 'pending'">
                                            <button @click="openGrant(request)"
                                                class="rounded bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700">
                                                Attribuer
                                            </button>
                                            <button @click="openRefuse(request)"
                                                class="ml-1 rounded bg-red-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-red-700">
                                                Refuser
                                            </button>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Échéances : la relance commerciale, faute d'encaissement en ligne. -->
                <section v-if="expiring.length">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Échéances sous 30 jours
                    </h3>
                    <div class="mt-3 overflow-hidden rounded-lg bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="item in expiring" :key="item.agency_id" class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5">
                                        <Link :href="route('admin.agencies.show', item.agency_id)"
                                            class="font-medium text-gray-900 hover:underline">
                                            {{ item.agency }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-700">{{ item.plan }}</td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ item.ends_at }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="text-xs font-medium"
                                            :class="item.days_left <= 7 ? 'text-red-600' : 'text-amber-700'">
                                            {{ item.days_left > 0 ? `dans ${item.days_left} j` : 'expirée' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <!-- Édition d'une formule -->
        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="editing = null">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Modifier la formule {{ editing.name }}</h3>

                <div class="mt-4 space-y-3 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-gray-600">Nom</span>
                            <input v-model="planForm.name" type="text"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                        <label class="block">
                            <span class="text-gray-600">Prix annuel (DA)</span>
                            <input v-model="planForm.price_dzd" type="number"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <label class="block">
                            <span class="text-gray-600">Annonces</span>
                            <input v-model="planForm.max_listings" type="number" placeholder="Illimité"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <span class="mt-0.5 block text-xs text-gray-400">Vide = illimité</span>
                        </label>
                        <label class="block">
                            <span class="text-gray-600">Photos</span>
                            <input v-model="planForm.max_photos" type="number"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                        <label class="block">
                            <span class="text-gray-600">Utilisateurs</span>
                            <input v-model="planForm.max_users" type="number"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                    </div>
                    <p v-if="planForm.errors.max_listings" class="text-red-600">{{ planForm.errors.max_listings }}</p>

                    <div class="space-y-1.5 rounded-md bg-gray-50 p-3">
                        <label class="flex items-center gap-2 text-gray-700">
                            <input v-model="planForm.has_commune_priority" type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            Priorité commune dans les résultats
                        </label>
                        <label class="flex items-center gap-2 text-gray-700">
                            <input v-model="planForm.has_wilaya_priority" type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            Priorité wilaya dans les résultats
                        </label>
                        <label class="flex items-center gap-2 text-gray-700">
                            <input v-model="planForm.has_homepage_feature" type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            Mise en avant sur la page d’accueil
                        </label>
                        <label class="flex items-center gap-2 text-gray-700">
                            <input v-model="planForm.can_reply_reviews" type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            Réponse aux avis clients
                        </label>
                        <label class="flex items-center gap-2 text-gray-700">
                            <input v-model="planForm.is_active" type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            Formule proposée aux agences
                        </label>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <label class="block">
                            <span class="text-gray-600">Statistiques</span>
                            <select v-model="planForm.stats_level"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="basic">Basiques</option>
                                <option value="advanced">Avancées</option>
                                <option value="premium">Complètes</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-gray-600">Badge</span>
                            <input v-model="planForm.badge_label" type="text" placeholder="Aucun"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                        <label class="block">
                            <span class="text-gray-600">Couleur</span>
                            <input v-model="planForm.badge_color" type="text" placeholder="#6b7280"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-gray-600">Description</span>
                        <textarea v-model="planForm.description" rows="2"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button @click="editing = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="savePlan" :disabled="planForm.processing"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>

        <!-- Attribution -->
        <div v-if="granting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="granting = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">
                    Attribuer {{ granting.plan }} à {{ granting.agency }}
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    À faire une fois le règlement encaissé : la plateforme n’encaisse rien en ligne.
                </p>

                <div class="mt-4 space-y-3 text-sm">
                    <label class="block">
                        <span class="text-gray-600">Échéance</span>
                        <input v-model="grantForm.ends_at" type="date"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <span class="mt-0.5 block text-xs text-gray-400">
                            Vide = sans échéance. Passée l’échéance, l’agence repasse en Silver.
                        </span>
                    </label>
                    <p v-if="grantForm.errors.ends_at" class="text-red-600">{{ grantForm.errors.ends_at }}</p>

                    <label class="block">
                        <span class="text-gray-600">Note (visible par l’agence)</span>
                        <textarea v-model="grantForm.note" rows="2"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Ex. : virement reçu le 12/09, facture n° 2026-041."></textarea>
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button @click="granting = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="submitGrant" :disabled="grantForm.processing"
                        class="rounded bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        Attribuer
                    </button>
                </div>
            </div>
        </div>

        <!-- Refus -->
        <div v-if="refusing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="refusing = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Refuser la demande de {{ refusing.agency }}</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Le motif part par email : une agence qui a demandé et reçoit le silence croit la
                    plateforme morte.
                </p>
                <textarea v-model="refuseForm.admin_response" rows="3" autofocus
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Ex. : règlement non reçu à ce jour, la demande sera réexaminée dès réception."></textarea>
                <p v-if="refuseForm.errors.admin_response" class="mt-1 text-sm text-red-600">
                    {{ refuseForm.errors.admin_response }}
                </p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="refusing = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="submitRefuse" :disabled="refuseForm.processing"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Confirmer le refus
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
