<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApresConnexion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * Connexion et inscription par compte Google.
 *
 * Posé le 2026-10-18. Le marché visé est grand public et majoritairement
 * mobile : taper une adresse et choisir un mot de passe sur un téléphone est
 * la marche la plus haute du parcours d'inscription. Un bouton Google la
 * supprime.
 *
 * **Ce service est gratuit**, quel que soit le nombre d'utilisateurs, tant
 * qu'on se limite aux champs `email` et `profile` — qui ne sont pas des
 * portées sensibles et n'exigent donc aucune validation de Google.
 *
 * **Réservé aux CLIENTS, pas aux agences.** L'inscription d'une agence
 * collecte un registre de commerce et passe par la modération : un compte
 * créé en un clic contournerait cette vérification, qui est précisément ce
 * que la plateforme promet à ses visiteurs. Une agence s'inscrit donc
 * toujours par `/inscription-agence`.
 */
class AuthGoogleController extends Controller
{
    /**
     * Le bouton n'a de sens que si les identifiants existent.
     *
     * Sans cette garde, un déploiement dont le `.env` est incomplet afficherait
     * un bouton qui mène à une page d'erreur de Google — le pire des deux
     * mondes. Partagé à l'interface par `HandleInertiaRequests`.
     */
    public static function estConfigure(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function rediriger(): RedirectResponse
    {
        abort_unless(self::estConfigure(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function retour(): RedirectResponse
    {
        abort_unless(self::estConfigure(), 404);

        try {
            $profil = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            /*
             * L'état ne correspond plus : la session a expiré pendant que
             * l'utilisateur était chez Google, ou il a ouvert le lien dans un
             * autre navigateur. Ce n'est pas une panne, c'est un parcours
             * interrompu — on le renvoie se reconnecter sans l'alarmer.
             */
            return redirect()->route('login')
                ->withErrors(['email' => __('La connexion a expiré. Merci de réessayer.')]);
        } catch (\Throwable $e) {
            Log::warning('Connexion Google impossible : '.$e->getMessage());

            return redirect()->route('login')
                ->withErrors(['email' => __('La connexion avec Google a échoué. Essayez avec votre mot de passe.')]);
        }

        $courriel = $profil->getEmail();

        /*
         * Google peut rendre un profil sans adresse — un compte dont
         * l'utilisateur a refusé le partage. Sans adresse, aucun compte
         * possible : tout le reste de l'application l'utilise comme identifiant.
         */
        if (blank($courriel)) {
            return redirect()->route('login')
                ->withErrors(['email' => __('Votre compte Google ne partage pas d\'adresse e-mail.')]);
        }

        $utilisateur = $this->trouverOuCreer($profil->getId(), $courriel, $profil->getName());

        Auth::login($utilisateur, remember: true);

        /*
         * Une agence peut très bien se connecter par Google — ce qui est
         * interdit, c'est de CRÉER une agence ainsi. On la renvoie donc chez
         * elle, comme le fait la connexion par mot de passe.
         */
        /*
         * `ApresConnexion` plutot que `intended()` nu : l'adresse retenue en
         * session peut viser un espace que ce compte n'a pas le droit de voir,
         * et la garde y repond 404. Voir la classe.
         */
        return ApresConnexion::versIntentionOu(
            $utilisateur->activeAgency() ? route('agency.dashboard') : route('home'),
            $utilisateur
        );
    }

    /**
     * Rattache, ou crée.
     *
     * Trois cas, dans cet ordre :
     *
     * 1. **L'identifiant Google est connu** — c'est lui qui fait foi, pas
     *    l'adresse. Une adresse peut changer de propriétaire, l'identifiant
     *    reste.
     * 2. **L'adresse existe déjà** sur un compte créé au mot de passe. On
     *    RATTACHE au lieu de refuser : Google a vérifié cette adresse, c'est
     *    donc bien la même personne. Refuser l'enverrait sur un écran
     *    « adresse déjà prise » qu'elle ne comprendrait pas, puisqu'elle est
     *    cette adresse.
     * 3. **Personne** — on crée un client, déjà vérifié : Google ne rend que
     *    des adresses dont il a confirmé la possession, lui réclamer une
     *    seconde vérification par courriel n'aurait aucun sens.
     */
    private function trouverOuCreer(string $googleId, string $courriel, ?string $nom): User
    {
        if ($utilisateur = User::where('google_id', $googleId)->first()) {
            return $utilisateur;
        }

        if ($utilisateur = User::where('email', $courriel)->first()) {
            $utilisateur->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $utilisateur->email_verified_at ?? now(),
            ])->save();

            return $utilisateur;
        }

        $utilisateur = User::create([
            'name' => $nom ?: str($courriel)->before('@')->toString(),
            'email' => $courriel,
            'google_id' => $googleId,
            'role' => User::ROLE_CLIENT,
            // Pas de mot de passe : la colonne est nullable depuis la
            // migration du 2026-10-18, et un faux mot de passe aléatoire
            // serait une donnée morte qui ferait croire au contraire.
            'password' => null,
        ]);

        /*
         * `email_verified_at` n'est PAS assignable en masse, et c'est
         * volontaire : aucune donnée d'entrée ne doit pouvoir marquer une
         * adresse comme vérifiée. On passe donc par la méthode prévue.
         *
         * Et on le fait, parce que Google ne rend que des adresses dont il a
         * confirmé la possession : réclamer une seconde vérification par
         * courriel n'aurait aucun sens.
         */
        $utilisateur->markEmailAsVerified();

        return $utilisateur;
    }
}
