<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Où atterrit-on une fois connecté ?
 *
 * Posé le 2026-10-18, après un 404 en production : quelqu'un avait ouvert
 * l'espace agence en visiteur, s'était connecté avec Google, et s'était
 * retrouvé sur une page blanche « 404 NOT FOUND ».
 *
 * Le mécanisme est celui de Laravel. Quand un visiteur touche une page
 * protégée, `auth` retient l'adresse en session sous `url.intended` et le
 * renvoie se connecter ; `redirect()->intended()` l'y ramène ensuite. Utile —
 * sans cela, cliquer « Réserver » puis se connecter ferait perdre le véhicule
 * qu'on regardait.
 *
 * Mais `intended()` ne vérifie RIEN. Et deux espaces du site répondent 404
 * plutôt que 403, délibérément : `/admin` et `/agence` ne confirment pas leur
 * existence à un inconnu. L'adresse retenue était donc un piège : la
 * connexion réussissait, et la redirection qui suit tombait sur la garde.
 *
 * D'où cette classe. Elle filtre l'intention avant de l'honorer, et c'est le
 * seul endroit où cette décision se prend — les deux chemins de connexion,
 * mot de passe et Google, souffraient du même défaut.
 */
class ApresConnexion
{
    /**
     * Les préfixes qui répondent 404 à qui n'y a pas droit.
     *
     * Les garder ici en dur est assumé : c'est court, et toute autre méthode
     * demanderait de rejouer la pile de intergiciels de la route visée, pour
     * un gain nul.
     */
    private const RESERVES = [
        'admin' => 'isAdmin',
        'agence' => 'activeAgency',
    ];

    public static function versIntentionOu(string $defaut, User $utilisateur): RedirectResponse
    {
        $voulue = session()->pull('url.intended');

        return redirect()->to(
            self::estAtteignable($voulue, $utilisateur) ? $voulue : $defaut
        );
    }

    /**
     * L'adresse retenue mène-t-elle quelque part pour CET utilisateur ?
     *
     * On ne retient que le chemin : une adresse absolue vers un autre domaine
     * n'a rien à faire ici, et `intended()` l'accepterait sans broncher — une
     * redirection ouverte est le genre de détail dont on se sert pour faire
     * passer une page d'hameçonnage pour la nôtre.
     */
    private static function estAtteignable(?string $voulue, User $utilisateur): bool
    {
        if (blank($voulue)) {
            return false;
        }

        $parties = parse_url($voulue);

        if (isset($parties['host']) && $parties['host'] !== request()->getHost()) {
            return false;
        }

        $premier = str($parties['path'] ?? '')->trim('/')->before('/')->toString();

        foreach (self::RESERVES as $prefixe => $droit) {
            // Négation simple, pas `blank()` : `blank(false)` vaut FALSE —
            // Laravel ne considère jamais un booléen comme vide, et le
            // contrôle admin aurait laissé passer tout le monde.
            if ($premier === $prefixe && ! $utilisateur->{$droit}()) {
                return false;
            }
        }

        return true;
    }
}
