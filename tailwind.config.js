import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Charte Autokrili — posée le 2026-10-18.
 *
 * Avant : la configuration de Breeze, sans la moindre couleur. Le site public
 * était en noir et blanc (`bg-gray-900` pour les boutons), les espaces agence
 * et administration en indigo — l'indigo de Breeze, que personne n'avait
 * choisi. Deux styles cohabitaient sans décision.
 *
 * **Noir et ambre**, décidé par le propriétaire. Le noir vient du favicon, il
 * existait déjà. L'ambre apporte l'énergie du départ et du soleil, et il
 * distingue d'un marché où tous les comparateurs de voyage sont bleus.
 *
 * Les noms sont en français et SÉMANTIQUES — `bg-marque`, pas `bg-amber-500`.
 * C'est ce qui permettra de changer la teinte un jour sans relire deux cents
 * fichiers. Les gris de Tailwind restent disponibles pour les bordures et les
 * textes secondaires : les redéfinir n'apporterait rien.
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                /*
                 * L'encre : le noir du favicon, décliné.
                 *
                 * Pas un noir pur (#000) : sur un écran, le noir absolu crée
                 * un contraste dur qui fatigue et fait baver le texte clair.
                 * 900 pour les grandes surfaces, 950 pour le pied de page.
                 */
                encre: {
                    50: '#F6F6F7',
                    100: '#E2E2E5',
                    200: '#C5C5CB',
                    300: '#9D9DA6',
                    400: '#6F6F7A',
                    500: '#4A4A54',
                    600: '#35353D',
                    700: '#26262C',
                    800: '#1A1A1F',
                    900: '#111113',
                    950: '#0A0A0C',
                    DEFAULT: '#111113',
                },

                /*
                 * La marque : l'ambre.
                 *
                 * 500 est la teinte de référence, celle des boutons. 600 pour
                 * le survol, 400 pour un accent sur fond sombre — sur du noir,
                 * le 500 manque de luminosité et le texte dessus devient
                 * illisible.
                 */
                marque: {
                    50: '#FFFBEB',
                    100: '#FEF3C7',
                    200: '#FDE68A',
                    300: '#FCD34D',
                    400: '#FBBF24',
                    500: '#F59E0B',
                    600: '#D97706',
                    700: '#B45309',
                    800: '#92400E',
                    900: '#78350F',
                    DEFAULT: '#F59E0B',
                },

                /*
                 * Le fond de page : un blanc cassé, pas du blanc pur.
                 *
                 * Le blanc pur à côté de l'ambre donne un rendu froid et
                 * clinique. Trois points de chaleur suffisent à les accorder,
                 * et le texte s'y lit mieux sur un écran de téléphone en plein
                 * soleil — ce qui est exactement la situation de nos visiteurs.
                 */
                creme: {
                    DEFAULT: '#FBF9F6',
                    100: '#F5F2ED',
                },
            },

            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },

            /*
             * Un seul rayon par usage, pour que rien ne flotte entre deux
             * valeurs : `carte` pour les blocs, `bouton` pour les actions.
             */
            borderRadius: {
                carte: '1rem',
                bouton: '0.625rem',
            },

            /*
             * Ombres portées douces et colorées à l'encre plutôt qu'au noir
             * pur : une ombre grise sur un fond crème se voit comme une tache.
             */
            boxShadow: {
                carte: '0 1px 2px 0 rgb(17 17 19 / 0.04), 0 4px 16px -2px rgb(17 17 19 / 0.08)',
                'carte-survol': '0 2px 4px 0 rgb(17 17 19 / 0.06), 0 12px 32px -4px rgb(17 17 19 / 0.14)',
                marque: '0 4px 14px -2px rgb(245 158 11 / 0.35)',
            },
        },
    },

    plugins: [forms],
};
