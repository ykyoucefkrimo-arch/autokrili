import { ref, watch } from 'vue';

/**
 * Les communes de la wilaya choisie, chargées à la demande.
 *
 * Le pays en compte 1541 : les envoyer toutes avec chaque page alourdirait le
 * premier affichage de ~90 Ko pour une liste dont le visiteur ne lit qu'une
 * wilaya. Les réponses sont gardées en mémoire pour la durée de la visite —
 * revenir sur une wilaya déjà consultée ne coûte plus rien.
 */
const cache = new Map();

export function useCommunes(wilayaRef, wilayas, { onChange } = {}) {
    const communes = ref([]);
    const loading = ref(false);

    const slugOf = (value) => {
        if (!value) return null;
        const wilaya = wilayas.find((w) => String(w.id) === String(value) || w.slug === value);
        return wilaya?.slug ?? null;
    };

    const load = async (value) => {
        const slug = slugOf(value);

        if (!slug) {
            communes.value = [];
            return;
        }

        if (cache.has(slug)) {
            communes.value = cache.get(slug);
            return;
        }

        loading.value = true;
        try {
            const response = await fetch(route('communes.index', { wilaya: slug }), {
                headers: { Accept: 'application/json' },
            });
            const data = response.ok ? await response.json() : [];
            cache.set(slug, data);
            communes.value = data;
        } catch {
            // Une liste vide et un champ actif valent mieux qu'un écran bloqué :
            // le visiteur peut toujours chercher sur la wilaya seule.
            communes.value = [];
        } finally {
            loading.value = false;
        }
    };

    watch(wilayaRef, async (value) => {
        await load(value);
        onChange?.(communes.value);
    });

    // Une page rouverte sur une wilaya déjà choisie doit afficher ses communes
    // sans attendre que l'utilisateur en change.
    if (wilayaRef.value) {
        load(wilayaRef.value);
    }

    return { communes, loading, reload: load };
}
