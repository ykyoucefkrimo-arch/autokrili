<script setup>
import { useTranslations } from '@/composables/useTranslations';
import { router } from '@inertiajs/vue3';

const { locale } = useTranslations();

const LOCALES = [
    { code: 'fr', label: 'FR' },
    { code: 'ar', label: 'ع' },
];

const switchTo = (code) => {
    if (code === locale.value) return;

    // Rechargement complet : la direction du document (rtl/ltr) est posée par
    // le gabarit Blade, qu'une navigation Inertia ne réévalue pas.
    router.post(route('locale.switch'), { locale: code }, {
        preserveScroll: true,
        onSuccess: () => window.location.reload(),
    });
};
</script>

<template>
    <div class="flex items-center gap-0.5 rounded-md border border-gray-200 p-0.5">
        <button v-for="item in LOCALES" :key="item.code" type="button" @click="switchTo(item.code)"
            class="rounded px-2 py-0.5 text-xs font-medium transition"
            :class="locale === item.code ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900'"
            :aria-current="locale === item.code ? 'true' : undefined">
            {{ item.label }}
        </button>
    </div>
</template>
