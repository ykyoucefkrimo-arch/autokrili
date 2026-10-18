<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fermer et rouvrir un compte (2026-10-18).
 *
 * L'administration savait suspendre une AGENCE, pas une personne. Un client
 * qui multiplie les fausses réservations, ou un compte visiblement compromis,
 * n'avait aucun frein.
 *
 * **On ne supprime jamais.** Les réservations portent `client_id` : effacer
 * un client effacerait l'historique des agences qui l'ont servi, et les
 * chiffres de la plateforme avec. Une suspension se défait ; une suppression
 * non.
 */
class UserModerationService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function suspend(User $utilisateur, string $motif): User
    {
        $this->refuseSiIntouchable($utilisateur);

        return DB::transaction(function () use ($utilisateur, $motif) {
            $avant = $utilisateur->getAttributes();

            $utilisateur->forceFill([
                'suspended_at' => now(),
                'suspension_reason' => $motif,
            ])->save();

            /*
             * Les sessions ouvertes sont effacées en base.
             *
             * Sans cela, quelqu'un déjà connecté resterait connecté jusqu'à
             * l'expiration de son cookie — la suspension ne prendrait effet
             * que pour quelqu'un qui se déconnecte de lui-même, c'est-à-dire
             * jamais quand c'est utile. L'intergiciel traite les sessions
             * portées par un autre pilote.
             */
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $utilisateur->id)->delete();
            }

            $this->audit->logChange('user.suspended', $utilisateur, $avant);

            return $utilisateur->refresh();
        });
    }

    public function reinstate(User $utilisateur): User
    {
        return DB::transaction(function () use ($utilisateur) {
            $avant = $utilisateur->getAttributes();

            $utilisateur->forceFill([
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->save();

            $this->audit->logChange('user.reinstated', $utilisateur, $avant);

            return $utilisateur->refresh();
        });
    }

    /**
     * Marquer une adresse comme vérifiée, à la main.
     *
     * Posé le 2026-10-18, après qu'une agence approuvée s'est retrouvée
     * enfermée dehors. Une agence doit confirmer son adresse avant d'entrer
     * (`User::hasVerifiedEmail()`), et le courriel qui porte le lien n'est
     * jamais parti : la production tourne avec `MAIL_MAILER=file`. Le compte
     * était approuvé, légitime, et sans aucun moyen d'avancer.
     *
     * **Ce geste n'est pas l'équivalent du clic.** Le clic prouve que
     * l'adresse est joignable ; ceci ne prouve que la décision d'un
     * administrateur qui a l'agence au téléphone. C'est pourquoi il reste un
     * geste explicite, journalisé, et non une conséquence de l'approbation :
     * une coquille dans l'adresse ferait disparaître en silence toutes les
     * notifications de réservation à venir.
     */
    public function verifyEmail(User $utilisateur): User
    {
        return DB::transaction(function () use ($utilisateur) {
            $avant = $utilisateur->getAttributes();

            $utilisateur->forceFill(['email_verified_at' => now()])->save();
            $this->audit->logChange('user.email_verified', $utilisateur, $avant);

            return $utilisateur->refresh();
        });
    }

    /**
     * Deux comptes qu'on ne ferme pas.
     *
     * **Le sien** : se suspendre soi-même met fin à sa propre session dans la
     * seconde, et il faut alors un autre administrateur — ou la base — pour
     * revenir.
     *
     * **Un autre administrateur** : à deux comptes, le premier qui clique
     * gagne. Un changement de rôle reste possible en base, délibérément hors
     * de portée d'un clic.
     */
    private function refuseSiIntouchable(User $utilisateur): void
    {
        if ($utilisateur->id === Auth::id()) {
            throw new RuntimeException('Vous ne pouvez pas suspendre votre propre compte.');
        }

        if ($utilisateur->isAdmin()) {
            throw new RuntimeException('Un compte administrateur ne se suspend pas depuis cet écran.');
        }
    }
}
