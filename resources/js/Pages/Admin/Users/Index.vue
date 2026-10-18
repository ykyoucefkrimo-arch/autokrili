<script setup>
/**
 * Les comptes, vus de l'administration (2026-10-18).
 *
 * Deux gestes seulement : suspendre et rouvrir. Pas de suppression — les
 * réservations portent `client_id`, et effacer un client effacerait
 * l'historique des agences qui l'ont servi.
 */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    utilisateurs: { type: Object, required: true },
    filtres: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    compteurs: { type: Object, default: () => ({}) },
});

const ROLES = {
    client: { label: 'Client', classes: 'bg-sky-100 text-sky-800' },
    agency: { label: 'Agence', classes: 'bg-marque-100 text-marque-800' },
    admin: { label: 'Administrateur', classes: 'bg-encre-200 text-encre-800' },
};

const recherche = ref(props.filtres.search ?? '');
const role = ref(props.filtres.role ?? '');
const statut = ref(props.filtres.statut ?? '');

let minuteur = null;
watch([recherche, role, statut], () => {
    // La frappe est temporisée : sans cela chaque lettre partirait au serveur.
    clearTimeout(minuteur);
    minuteur = setTimeout(() => {
        router.get(route('admin.users.index'), {
            search: recherche.value || undefined,
            role: role.value || undefined,
            statut: statut.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
});

// Le motif est exigé côté serveur ; la fenêtre l'exige ici aussi, pour ne pas
// faire découvrir la règle par un message d'erreur.
const aSuspendre = ref(null);
const formulaire = useForm({ motif: '' });

const ouvrir = (utilisateur) => {
    formulaire.reset();
    formulaire.clearErrors();
    aSuspendre.value = utilisateur;
};

const confirmer = () => {
    formulaire.post(route('admin.users.suspend', aSuspendre.value.id), {
        preserveScroll: true,
        onSuccess: () => (aSuspendre.value = null),
    });
};

const rouvrir = (utilisateur) => {
    router.post(route('admin.users.reinstate', utilisateur.id), {}, { preserveScroll: true });
};

/*
 * Une agence ne peut pas entrer tant que son adresse n'est pas confirmee, et
 * le lien part par courriel. Tant que la production n'envoie rien, c'est le
 * seul moyen de debloquer un dossier legitime — d'ou la confirmation : ce
 * geste affirme qu'on a joint l'agence autrement.
 */
const verifier = (utilisateur) => {
    if (!confirm(`Marquer ${utilisateur.email} comme vérifiée ? À ne faire que si vous avez joint cette agence par un autre moyen.`)) return;

    router.post(route('admin.users.verify-email', utilisateur.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Utilisateurs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Utilisateurs</h2>
                <div class="flex items-center gap-2 text-sm">
                    <span class="rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700">
                        {{ compteurs.total }} comptes
                    </span>
                    <span v-if="compteurs.suspendus"
                        class="rounded-full bg-red-100 px-3 py-1 font-medium text-red-800">
                        {{ compteurs.suspendus }} suspendu{{ compteurs.suspendus > 1 ? 's' : '' }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">

                <div class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <input v-model="recherche" type="search" placeholder="Nom, adresse e-mail, téléphone…"
                        class="w-72 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <select v-model="role"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tous les rôles</option>
                        <option v-for="r in roles" :key="r" :value="r">{{ ROLES[r]?.label ?? r }}</option>
                    </select>
                    <select v-model="statut"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Actifs et suspendus</option>
                        <option value="actif">Actifs</option>
                        <option value="suspendu">Suspendus</option>
                    </select>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div v-if="!utilisateurs.data.length" class="p-10 text-center">
                        <p class="font-medium text-gray-900">Aucun compte ne correspond</p>
                        <p class="mt-1 text-sm text-gray-600">Modifiez la recherche ou les filtres.</p>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Compte</th>
                                <th class="px-4 py-3">Rôle</th>
                                <th class="px-4 py-3">Agence</th>
                                <th class="px-4 py-3 text-center">Réservations</th>
                                <th class="px-4 py-3">Inscrit le</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="u in utilisateurs.data" :key="u.id"
                                :class="u.suspendu ? 'bg-red-50/60' : 'hover:bg-gray-50'">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ u.name }}</p>
                                    <p class="text-xs text-gray-500">{{ u.email }}</p>
                                    <p v-if="u.phone" class="text-xs text-gray-500">{{ u.phone }}</p>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span v-if="u.par_google"
                                            class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">Google</span>
                                        <button v-if="!u.verifie" @click="verifier(u)"
                                            title="Marquer cette adresse comme vérifiée"
                                            class="rounded bg-amber-100 px-1.5 py-0.5 text-[11px] text-amber-800 hover:bg-amber-200">
                                            Adresse non vérifiée — confirmer
                                        </button>
                                        <!-- Le motif compte autant que l'état : c'est lui
                                             qu'on relit avant de rouvrir. -->
                                        <span v-if="u.suspendu"
                                            class="rounded bg-red-100 px-1.5 py-0.5 text-[11px] text-red-800">
                                            Suspendu le {{ u.suspendu_le }} — {{ u.motif }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="ROLES[u.role]?.classes ?? 'bg-gray-100 text-gray-700'">
                                        {{ ROLES[u.role]?.label ?? u.role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <Link v-if="u.agence" :href="route('admin.agencies.show', u.agence.id)"
                                        class="text-indigo-600 hover:underline">
                                        {{ u.agence.commercial_name }}
                                    </Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-700">{{ u.reservations }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ u.inscrit_le }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button v-if="u.suspendu" @click="rouvrir(u)"
                                        class="rounded bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-700">
                                        Rouvrir
                                    </button>
                                    <!-- Aucun bouton là où le serveur refuserait :
                                         son propre compte, et tout administrateur. -->
                                    <button v-else-if="!u.intouchable" @click="ouvrir(u)"
                                        class="rounded border border-red-300 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50">
                                        Suspendre
                                    </button>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="utilisateurs.links.length > 3" class="flex flex-wrap gap-1">
                    <component v-for="lien in utilisateurs.links" :key="lien.label"
                        :is="lien.url ? Link : 'span'" :href="lien.url"
                        class="rounded px-3 py-1.5 text-sm"
                        :class="lien.active ? 'bg-indigo-600 text-white' : lien.url ? 'bg-white text-gray-700 hover:bg-gray-100' : 'text-gray-400'"
                        v-html="lien.label" />
                </div>
            </div>
        </div>

        <div v-if="aSuspendre" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="aSuspendre = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Suspendre « {{ aSuspendre.name }} »</h3>
                <p class="mt-1 text-sm text-gray-600">
                    La session en cours est fermée immédiatement, et le motif s'affiche à la
                    prochaine tentative de connexion. Rien n'est supprimé : les réservations
                    et l'historique restent.
                </p>
                <textarea v-model="formulaire.motif" rows="4" autofocus
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Ex. : réservations répétées jamais honorées, trois agences l'ont signalé."></textarea>
                <p v-if="formulaire.errors.motif" class="mt-1 text-sm text-red-600">{{ formulaire.errors.motif }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button @click="aSuspendre = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="confirmer" :disabled="formulaire.processing"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Confirmer
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
