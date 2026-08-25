<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    reviews: { type: Object, required: true },
    summary: { type: Object, required: true },
    canReply: { type: Boolean, default: false },
    planName: { type: String, default: '' },
    canWrite: { type: Boolean, default: true },
});

const STATUSES = {
    pending: { label: 'En modération', classes: 'bg-amber-100 text-amber-800' },
    approved: { label: 'Publié', classes: 'bg-green-100 text-green-800' },
    rejected: { label: 'Écarté', classes: 'bg-gray-200 text-gray-600' },
};

const stars = (n) => '★'.repeat(n) + '☆'.repeat(5 - n);

const maxBar = computed(
    () => Math.max(1, ...Object.values(props.summary.distribution ?? {})),
);

const replying = ref(null);
const replyForm = useForm({ reply: '' });

const openReply = (review) => {
    replyForm.reset();
    replyForm.clearErrors();
    replyForm.reply = review.agency_reply ?? '';
    replying.value = review;
};

const submitReply = () => {
    replyForm.post(route('agency.reviews.reply', replying.value.id), {
        preserveScroll: true,
        onSuccess: () => (replying.value = null),
    });
};
</script>

<template>
    <Head title="Avis clients" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Avis clients</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center gap-8">
                        <div>
                            <p class="text-4xl font-bold text-gray-900">
                                {{ summary.count ? summary.average.toFixed(1) : '—' }}
                                <span class="text-lg font-normal text-gray-500">/ 5</span>
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ summary.count }} avis publié{{ summary.count > 1 ? 's' : '' }}
                            </p>
                        </div>

                        <div class="min-w-[200px] flex-1">
                            <div v-for="n in [5, 4, 3, 2, 1]" :key="n" class="flex items-center gap-2 text-xs">
                                <span class="w-3 text-gray-500">{{ n }}</span>
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-amber-400"
                                        :style="{ width: ((summary.distribution?.[n] ?? 0) / maxBar * 100) + '%' }" />
                                </div>
                                <span class="w-6 text-right text-gray-500">{{ summary.distribution?.[n] ?? 0 }}</span>
                            </div>
                        </div>

                        <div v-if="summary.pending" class="rounded-md bg-amber-50 p-3 text-sm text-amber-900">
                            {{ summary.pending }} avis en cours de modération.
                            <span class="block text-xs">Ils ne comptent pas encore dans votre note.</span>
                        </div>
                    </div>
                </div>

                <!-- La réponse aux avis est une option de formule (§4.1) :
                     l'annoncer ici vaut mieux que griser un bouton sans dire pourquoi. -->
                <div v-if="!canReply" class="rounded-md border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                    Votre formule {{ planName }} ne permet pas de répondre publiquement aux avis.
                    Les formules Gold et Platinium l’autorisent — une réponse posée à un avis sévère
                    en dit souvent plus long que l’avis lui-même.
                    <Link :href="route('agency.subscription')" class="ml-1 font-semibold underline">
                        Voir les formules
                    </Link>
                </div>

                <div v-if="!reviews.data.length" class="rounded-lg bg-white p-10 text-center shadow-sm">
                    <p class="font-medium text-gray-900">Aucun avis pour le moment</p>
                    <p class="mx-auto mt-1 max-w-md text-sm text-gray-600">
                        Vos clients sont invités à donner leur avis 24 heures après avoir rendu le
                        véhicule. Les avis sont relus avant publication.
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="review in reviews.data" :key="review.id"
                        class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-lg text-amber-500">{{ stars(review.rating) }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ review.vehicle }} — {{ review.reference }} — {{ review.created_at }}
                                </p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-xs font-medium"
                                :class="STATUSES[review.status].classes">
                                {{ STATUSES[review.status].label }}
                            </span>
                        </div>

                        <p v-if="review.comment" class="mt-3 whitespace-pre-line text-sm text-gray-700">
                            {{ review.comment }}
                        </p>
                        <p v-else class="mt-3 text-sm italic text-gray-400">Note sans commentaire.</p>

                        <div v-if="review.agency_reply"
                            class="mt-3 rounded-md border-l-2 border-indigo-300 bg-gray-50 p-3">
                            <p class="text-xs font-semibold text-gray-700">Votre réponse — {{ review.replied_at }}</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ review.agency_reply }}</p>
                        </div>

                        <div v-if="canWrite && canReply && review.status === 'approved'" class="mt-3">
                            <button @click="openReply(review)"
                                class="rounded border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                {{ review.agency_reply ? 'Modifier ma réponse' : 'Répondre publiquement' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="reviews.links.length > 3" class="flex flex-wrap gap-1">
                    <component v-for="link in reviews.links" :key="link.label"
                        :is="link.url ? Link : 'span'" :href="link.url"
                        class="rounded px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-100' : 'text-gray-400'"
                        v-html="link.label" />
                </div>
            </div>
        </div>

        <div v-if="replying" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="replying = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Répondre publiquement</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Votre réponse s’affiche sous l’avis, sur votre fiche publique.
                </p>
                <p class="mt-2 rounded bg-gray-50 p-2 text-sm text-gray-600">
                    <span class="text-amber-500">{{ stars(replying.rating) }}</span>
                    {{ replying.comment }}
                </p>

                <textarea v-model="replyForm.reply" rows="4" autofocus
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Merci pour votre retour…"></textarea>
                <p v-if="replyForm.errors.reply" class="mt-1 text-sm text-red-600">{{ replyForm.errors.reply }}</p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="replying = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="submitReply" :disabled="replyForm.processing"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        Publier ma réponse
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
