import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Traduction côté navigateur (§7.2).
 *
 * La clé est la phrase française elle-même : une page non encore traduite
 * reste lisible au lieu d'afficher `home.hero.title`, et l'arabe peut n'être
 * que partiellement rempli en v1 sans rien casser.
 */
export function useTranslations() {
    const page = usePage();

    const locale = computed(() => page.props.locale ?? 'fr');
    const rtl = computed(() => page.props.rtl === true);

    /**
     * @param {string} key Phrase française.
     * @param {Object} replacements Valeurs à injecter, notées :nom.
     */
    const t = (key, replacements = {}) => {
        const dictionary = page.props.translations ?? {};
        let text = dictionary[key] ?? key;

        Object.entries(replacements).forEach(([name, value]) => {
            text = text.replace(`:${name}`, value);
        });

        return text;
    };

    return { t, locale, rtl };
}
