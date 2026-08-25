<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    reviews: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    reasons: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
});

const STATUSES = {
    pending: { label: 'À modérer', classes: 'bg-amber-100 text-amber-800' },
    approved: { label: 'Publié', classes: 'bg-green-100 text-green-800' },
    rejected: { label: 'Écarté', classes: 'bg-gray-200 text-gray-600' },
};

const stars = (n) => '★'.repeat(n) + '☆'.repeat(5 - n);

const status = ref(props.filters.status ?? 'pending');

watch(status, (value) => {
    router.get(route('admin.reviews.index'), { status: value }, { preserveState: true, replace: true });
});

const approve = (review) =>
    router.post(route('admin.reviews.approve', review.id), {}, { preserveScroll: true });

const rejecting = ref(null);
const rejectForm = useForm({ reason: '' });

const openReject = (review) => {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejecting.value = review;
};

const pickReason = (label) => {
    rejectForm.reason = label;
};

const submitReject = () => {
    rejectForm.post(route('admin.reviews.reject', rejecting.value.id), {
        preserveScroll: true,
        onSuccess: () => (rejecting.value = null),
    });
};
</script>

<template>
    <Head title="Modération des avis" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Avis</h2>
                <span v-if="counts.pending"
                    class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                    {{ counts.pending }} à modérer
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <select v-model="status"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="(s, key) in STATUSES" :key="key" :value="key">{{ s.label }}</option>
                        <option value="all">Tous</option>
                    </select>
                </div>

                <div v-if="!reviews.data.length" class="rounded-lg bg-white p-10 text-center shadow-sm">
                    <p class="font-medium text-gray-900">Rien à modérer</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Un avis porte un jugement public sur une entreprise : il est lu avant d’être
                        montré, pas après une plainte.
                    </p>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="review in reviews.data" :key="review.id" class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-lg text-amber-500">{{ stars(review.rating) }}</p>
                                <p class="text-sm text-gray-700">
                                    <Link :href="route('admin.agencies.show', review.agency_id)"
                                        class="font-medium hover:underline">
                                        {{ review.agency }}
                                    </Link>
                                    <span class="text-gray-400"> — par {{ review.client }}</span>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ review.reference }} — {{ review.created_at }}
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

                        <p v-if="review.rejection_reason"
                            class="mt-2 rounded border border-gray-200 bg-gray-50 p-2 text-xs text-gray-600">
                            Motif de l’écart : {{ review.rejection_reason }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-3">
                            <button v-if="review.status !== 'approved'" @click="approve(review)"
                                class="rounded bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700">
                                Publier
                            </button>
                            <button v-if="review.status !== 'rejected'" @click="openReject(review)"
                                class="rounded bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">
                                Écarter
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

        <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="rejecting = null">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Écarter cet avis</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Il cesse de compter dans la note de l’agence. Le motif est conservé au journal
                    d’audit.
                </p>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    <button v-for="(label, key) in reasons" :key="key" type="button" @click="pickReason(label)"
                        class="rounded-full border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:bg-gray-50">
                        {{ label }}
                    </button>
                </div>

                <textarea v-model="rejectForm.reason" rows="3"
                    class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Motif…"></textarea>
                <p v-if="rejectForm.errors.reason" class="mt-1 text-sm text-red-600">{{ rejectForm.errors.reason }}</p>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="rejecting = null"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="submitReject" :disabled="rejectForm.processing"
                        class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        Écarter
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
