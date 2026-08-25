<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    agency: { type: Object, required: true },
    plans: { type: Array, default: () => [] },
});

const price = (v) => Number(v ?? 0).toLocaleString('fr-DZ');

const STATUSES = {
    pending: { label: 'En attente', classes: 'bg-amber-100 text-amber-800' },
    approved: { label: 'Approuvée', classes: 'bg-green-100 text-green-800' },
    rejected: { label: 'Rejetée', classes: 'bg-red-100 text-red-800' },
    suspended: { label: 'Suspendue', classes: 'bg-gray-200 text-gray-700' },
};

/* Attribution directe : l'administrateur n'a pas à attendre qu'une agence
   fasse la demande. Le règlement se fait hors plateforme (§4.3), c'est lui qui
   constate l'encaissement. */
const granting = ref(false);
const grantForm = useForm({ plan_id: null, ends_at: '', note: '' });

const openGrant = () => {
    grantForm.reset();
    grantForm.clearErrors();
    grantForm.plan_id = props.agency.plan?.id ?? props.plans[0]?.id ?? null;

    // Un an par défaut : c'est la durée que vend la plateforme. Vidé à la main
    // pour une formule sans échéance — Silver, typiquement.
    const inAYear = new Date();
    inAYear.setFullYear(inAYear.getFullYear() + 1);
    grantForm.ends_at = inAYear.toISOString().slice(0, 10);

    granting.value = true;
};

const submitGrant = () => {
    grantForm.post(route('admin.plans.grant', props.agency.id), {
        preserveScroll: true,
        onSuccess: () => (granting.value = false),
    });
};

const moderation = ref(null);
const reasonForm = useForm({ reason: '' });

const openReason = (action) => {
    reasonForm.reset();
    reasonForm.clearErrors();
    moderation.value = action;
};

const submitReason = () => {
    reasonForm.post(route(`admin.agencies.${moderation.value}`, props.agency.id), {
        preserveScroll: true,
        onSuccess: () => (moderation.value = null),
    });
};

const post = (action, data = {}) => {
    router.post(route(`admin.agencies.${action}`, props.agency.id), data, { preserveScroll: true });
};
</script>

<template>
    <Head :title="agency.commercial_name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.agencies.index')" class="text-sm text-gray-500 hover:underline">← Agences</Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ agency.commercial_name }}</h2>
                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="STATUSES[agency.status].classes">
                    {{ STATUSES[agency.status].label }}
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-5xl gap-6 sm:px-6 lg:grid-cols-3 lg:px-8">

                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Informations légales</h3>
                        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-gray-500">Gérant</dt>
                                <dd class="text-gray-900">{{ agency.manager_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Registre de commerce</dt>
                                <dd class="text-gray-900">{{ agency.trade_register_number }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">NIF</dt>
                                <dd class="text-gray-900">{{ agency.nif || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Inscrite le</dt>
                                <dd class="text-gray-900">{{ agency.created_at }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500">Adresse</dt>
                                <dd class="text-gray-900">{{ agency.address }} — {{ agency.commune }}, {{ agency.wilaya }}</dd>
                            </div>
                        </dl>

                        <!-- Le document s'ouvre par une route protégée par policy,
                             jamais par une URL que le serveur web servirait seul. -->
                        <a v-if="agency.has_trade_register" :href="route('admin.agencies.trade-register', agency.id)"
                            target="_blank" rel="noopener"
                            class="mt-5 inline-flex items-center gap-2 rounded-md bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                            Consulter le registre de commerce
                        </a>
                        <p v-else class="mt-5 text-sm text-red-600">Aucun registre de commerce n'a été fourni.</p>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Contact</h3>
                        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-gray-500">Email</dt>
                                <dd class="text-gray-900">
                                    {{ agency.email }}
                                    <span v-if="agency.email_verified" class="ml-1 text-xs text-green-600">vérifié</span>
                                    <span v-else class="ml-1 text-xs text-amber-600">non vérifié</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Téléphone</dt>
                                <dd class="text-gray-900">{{ agency.phone }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">WhatsApp</dt>
                                <dd class="text-gray-900">{{ agency.whatsapp || '—' }}</dd>
                            </div>
                        </dl>
                        <div v-if="agency.description" class="mt-4">
                            <p class="text-sm text-gray-500">Présentation</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ agency.description }}</p>
                        </div>
                    </div>

                    <div v-if="agency.rejection_reason" class="rounded-lg border border-red-200 bg-red-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-red-800">Motif enregistré</p>
                        <p class="mt-1 text-sm text-red-900">{{ agency.rejection_reason }}</p>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <div v-if="agency.logo_url" class="mb-4">
                            <img :src="agency.logo_url" alt="" class="h-20 w-20 rounded object-cover" />
                        </div>
                        <p class="text-xs uppercase tracking-wide text-gray-500">Formule</p>
                        <p class="text-lg font-semibold text-gray-900">{{ agency.plan?.name ?? 'Aucune' }}</p>
                        <p v-if="agency.plan_ends_at" class="text-sm text-gray-600">
                            Jusqu'au {{ agency.plan_ends_at }}
                        </p>
                        <p v-else-if="agency.plan" class="text-sm text-gray-500">Sans échéance</p>
                        <p v-if="agency.plan_note" class="mt-2 rounded bg-gray-50 p-2 text-xs text-gray-600">
                            {{ agency.plan_note }}
                        </p>

                        <button v-if="agency.status === 'approved'" @click="openGrant"
                            class="mt-3 w-full rounded border border-indigo-600 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:bg-indigo-50">
                            {{ agency.plan ? 'Changer la formule' : 'Attribuer une formule' }}
                        </button>
                        <p v-else class="mt-3 text-xs text-gray-500">
                            Une formule s'attribue à une agence approuvée.
                        </p>

                        <p v-if="agency.approved_at" class="mt-3 text-xs text-gray-500">
                            Approuvée le {{ agency.approved_at }}
                        </p>
                    </div>

                    <div class="space-y-2 rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Décision</h3>
                        <button v-if="agency.status === 'pending'" @click="post('approve')"
                            class="w-full rounded bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700">
                            Approuver l'agence
                        </button>
                        <button v-if="agency.status === 'pending'" @click="openReason('reject')"
                            class="w-full rounded bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">
                            Rejeter
                        </button>
                        <button v-if="agency.status === 'approved'" @click="openReason('suspend')"
                            class="w-full rounded bg-gray-600 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">
                            Suspendre
                        </button>
                        <button v-if="['suspended', 'rejected'].includes(agency.status)" @click="post('reinstate')"
                            class="w-full rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Réactiver
                        </button>

                        <label class="mt-4 flex items-start gap-2 border-t border-gray-100 pt-4 text-sm">
                            <input type="checkbox" :checked="agency.is_trusted"
                                @change="post('trust', { is_trusted: $event.target.checked })"
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span>
                                <span class="font-medium text-gray-900">Agence de confiance</span>
                                <span class="block text-xs text-gray-500">
                                    Ses annonces modifiées ne repassent plus en modération.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="moderation" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="moderation = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">
                    {{ moderation === 'reject' ? 'Rejeter' : 'Suspendre' }} « {{ agency.commercial_name }} »
                </h3>
                <p class="mt-1 text-sm text-gray-600">Le motif est envoyé à l'agence par email.</p>
                <textarea v-model="reasonForm.reason" rows="4" autofocus
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <p v-if="reasonForm.errors.reason" class="mt-1 text-sm text-red-600">{{ reasonForm.errors.reason }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button @click="moderation = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Annuler</button>
                    <button @click="submitReason" :disabled="reasonForm.processing"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Confirmer
                    </button>
                </div>
            </div>
        </div>

        <!-- Attribution d'une formule -->
        <div v-if="granting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="granting = false">
            <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">
                    Attribuer une formule à « {{ agency.commercial_name }} »
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    À faire une fois le règlement encaissé : la plateforme n'encaisse rien en ligne.
                </p>

                <div class="mt-4 space-y-3">
                    <div class="space-y-2">
                        <label v-for="plan in plans" :key="plan.id"
                            class="flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm"
                            :class="grantForm.plan_id === plan.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'">
                            <input type="radio" :value="plan.id" v-model="grantForm.plan_id"
                                class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            <span class="flex-1">
                                <span class="font-medium text-gray-900">{{ plan.name }}</span>
                                <span class="text-gray-500">
                                    — {{ plan.price_dzd ? price(plan.price_dzd) + ' DA / an' : 'gratuite' }}
                                </span>
                                <span class="block text-xs text-gray-500">
                                    {{ plan.max_listings ?? 'Illimité' }} annonce(s),
                                    {{ plan.max_photos }} photo(s) par annonce
                                </span>
                            </span>
                        </label>
                    </div>
                    <p v-if="grantForm.errors.plan_id" class="text-sm text-red-600">{{ grantForm.errors.plan_id }}</p>

                    <label class="block text-sm">
                        <span class="text-gray-600">Échéance</span>
                        <input v-model="grantForm.ends_at" type="date"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <span class="mt-0.5 block text-xs text-gray-400">
                            Videz le champ pour une formule sans échéance. Passée la date, l'agence
                            repasse en Silver et les annonces qui dépassent le quota sont archivées.
                        </span>
                    </label>
                    <p v-if="grantForm.errors.ends_at" class="text-sm text-red-600">{{ grantForm.errors.ends_at }}</p>

                    <label class="block text-sm">
                        <span class="text-gray-600">Note (visible par l'agence)</span>
                        <textarea v-model="grantForm.note" rows="2"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Ex. : virement reçu le 12/09, facture n° 2026-041."></textarea>
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button @click="granting = false"
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
    </AuthenticatedLayout>
</template>
