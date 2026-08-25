<script setup>
import { useTranslations } from '@/composables/useTranslations';
import { Head, Link } from '@inertiajs/vue3';

const { t } = useTranslations();

defineProps({
    title: { type: String, required: true },
    sections: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const LINKS = [
    { route: 'pages.about', label: 'À propos' },
    { route: 'pages.faq', label: 'Questions fréquentes' },
    { route: 'pages.terms', label: 'Conditions générales' },
    { route: 'pages.privacy', label: 'Confidentialité' },
    { route: 'pages.contact', label: 'Contact' },
];
</script>

<template>
    <Head>
        <title>{{ title }} — Autokrili</title>
        <meta name="description" :content="sections[0]?.body.slice(0, 155)" />
    </Head>

    <div class="min-h-screen bg-white">
        <header class="border-b border-gray-100">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
                <Link href="/" class="text-xl font-bold tracking-tight text-gray-900">Autokrili</Link>
                <nav class="flex items-center gap-4 text-sm">
                    <Link :href="route('search')" class="text-gray-600 hover:text-gray-900">{{ t('Véhicules') }}</Link>
                    <Link :href="route('agency.register')"
                        class="rounded-md bg-gray-900 px-4 py-2 font-medium text-white hover:bg-gray-800">
                        {{ t('Inscrire mon agence') }}
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-12">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ title }}</h1>

            <div class="mt-8 space-y-8">
                <section v-for="section in sections" :key="section.title">
                    <h2 class="text-lg font-semibold text-gray-900">{{ section.title }}</h2>
                    <p class="mt-2 leading-relaxed text-gray-700">{{ section.body }}</p>
                </section>
            </div>

            <!-- Une page statique sans issue est une page qu'on quitte. -->
            <div class="mt-12 rounded-xl border border-gray-200 bg-gray-50 p-6 text-center">
                <p class="text-sm text-gray-700">
                    {{ stats.vehicles ?? 0 }} véhicules proposés par
                    {{ stats.agencies ?? 0 }} agences vérifiées.
                </p>
                <Link :href="route('search')"
                    class="mt-4 inline-block rounded-md bg-gray-900 px-5 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    {{ t('Chercher un véhicule') }}
                </Link>
            </div>
        </main>

        <footer class="border-t border-gray-100">
            <div class="mx-auto max-w-4xl px-6 py-8">
                <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-600">
                    <Link v-for="link in LINKS" :key="link.route" :href="route(link.route)"
                        class="hover:text-gray-900">
                        {{ t(link.label) }}
                    </Link>
                </nav>
                <p class="mt-4 text-sm text-gray-500">Autokrili — location de voitures en Algérie.</p>
            </div>
        </footer>
    </div>
</template>
