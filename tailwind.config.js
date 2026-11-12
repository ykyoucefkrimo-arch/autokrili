import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Charte Autokrili — refaite le 2026-11-12, d'après le visuel publicitaire
 * fourni par le propriétaire.
 *
 * Avant : noir et ambre (2026-10-18). Désormais **violet sur nuit** : un
 * violet franc pour la marque, un noir teinté de violet pour l'encre, comme
 * le visuel. Le violet se distingue encore mieux que l'ambre d'un marché de
 * comparateurs bleus.
 *
 * Les noms restent SÉMANTIQUES — `bg-marque`, jamais `bg-violet-500` : c'est
 * ce qui a permis ce changement de teinte sans relire tout le code.
 *
 * Une conséquence à retenir : sur le violet, le texte des boutons est BLANC.
 * Sur l'ambre il était foncé ; laissé foncé sur du violet, il devenait
 * illisible (contraste inférieur à 4,5:1).
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
                    50: '#F7F6FB',
                    100: '#E6E4EF',
                    200: '#CBC8DA',
                    300: '#A29EB8',
                    400: '#757090',
                    500: '#4F4A68',
                    600: '#38344D',
                    700: '#28253A',
                    800: '#1B1929',
                    900: '#12101E',
                    950: '#0A0914',
                    DEFAULT: '#12101E',
                },

                /*
                 * La marque : le violet du visuel.
                 *
                 * 500 est la teinte de référence (fonds de boutons, texte
                 * blanc : 5,6:1). 600 pour le survol — plus sombre, pour que
                 * le texte blanc reste lisible. 400 pour un accent sur fond
                 * nuit, où le 500 manque de lumière.
                 */
                marque: {
                    50: '#F5EFFF',
                    100: '#EBDFFF',
                    200: '#D6BFFF',
                    300: '#BA92FF',
                    400: '#A066FF',
                    500: '#7C3AED',
                    600: '#6A28D9',
                    700: '#561FB3',
                    800: '#451B8E',
                    900: '#391972',
                    DEFAULT: '#7C3AED',
                },

                /*
                 * Le fond de page : un blanc cassé, pas du blanc pur.
                 *
                 * Le blanc pur à côté de l'ambre donne un rendu froid et
                 * clinique. Trois points de chaleur suffisent à les accorder,
                 * et le texte s'y lit mieux sur un écran de téléphone en plein
                 * soleil — ce qui est exactement la situation de nos visiteurs.
                 */
                // Un blanc légèrement lavande : le crème chaud de l'ambre jurait
                // avec le violet.
                creme: {
                    DEFAULT: '#F8F7FC',
                    100: '#F0EEF7',
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
                carte: '0 1px 2px 0 rgb(18 16 30 / 0.04), 0 4px 16px -2px rgb(18 16 30 / 0.08)',
                'carte-survol': '0 2px 4px 0 rgb(18 16 30 / 0.06), 0 12px 32px -4px rgb(18 16 30 / 0.14)',
                marque: '0 4px 14px -2px rgb(124 58 237 / 0.4)',
            },
        },
    },

    plugins: [forms],
};
