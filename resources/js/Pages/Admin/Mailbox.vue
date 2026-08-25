<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    emails: { type: Array, default: () => [] },
    mailer: { type: String, default: '' },
});

// Le premier email est ouvert d'emblée : arriver sur un volet vide oblige à un
// clic pour rien.
const selected = ref(props.emails[0] ?? null);

const clear = () => {
    if (!window.confirm('Vider la boîte d’envoi ?')) return;

    router.delete(route('admin.mailbox.destroy'), {
        onSuccess: () => (selected.value = null),
    });
};
</script>

<template>
    <Head title="Boîte d’envoi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Boîte d’envoi</h2>
                <button v-if="emails.length" @click="clear"
                    class="rounded border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Vider
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <!-- Dire d'où viennent ces emails évite de croire, en production,
                     que la plateforme n'envoie plus rien. -->
                <div class="rounded-md border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-900">
                    Mailer <span class="font-semibold">{{ mailer }}</span> : les emails sont écrits sur
                    le disque au lieu d’être envoyés. Cet écran montre ce que le destinataire aurait
                    reçu. En production, un SMTP les envoie réellement et cette page disparaît.
                </div>

                <div v-if="!emails.length" class="rounded-lg bg-white p-12 text-center shadow-sm">
                    <p class="font-medium text-gray-900">Aucun email pour le moment</p>
                    <p class="mx-auto mt-2 max-w-md text-sm text-gray-600">
                        Approuvez une agence, acceptez une réservation ou publiez un avis : l’email
                        correspondant apparaîtra ici.
                    </p>
                </div>

                <div v-else class="grid gap-4 lg:grid-cols-[340px_1fr]">
                    <div class="max-h-[70vh] divide-y divide-gray-100 overflow-y-auto rounded-lg bg-white shadow-sm">
                        <button v-for="email in emails" :key="email.file" @click="selected = email"
                            class="block w-full px-4 py-3 text-left hover:bg-gray-50"
                            :class="selected?.file === email.file ? 'bg-indigo-50' : ''">
                            <p class="truncate font-medium text-gray-900">{{ email.subject }}</p>
                            <p class="truncate text-xs text-gray-500">{{ email.to }}</p>
                            <p class="mt-0.5 truncate text-xs text-gray-400">{{ email.excerpt }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ email.date }}</p>
                        </button>
                    </div>

                    <div v-if="selected" class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div class="border-b border-gray-100 p-4">
                            <p class="font-semibold text-gray-900">{{ selected.subject }}</p>
                            <p class="text-sm text-gray-600">À : {{ selected.to }}</p>
                            <p class="text-xs text-gray-400">{{ selected.date }}</p>
                        </div>
                        <!-- iframe : le HTML de l'email ne doit pas hériter des
                             styles du back office, ni les perturber. -->
                        <iframe :src="route('admin.mailbox.show', selected.file)"
                            class="h-[60vh] w-full border-0" sandbox="" title="Aperçu de l’email" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
