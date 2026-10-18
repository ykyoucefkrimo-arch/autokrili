<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme la session d'un compte suspendu (2026-10-18).
 *
 * Refuser la connexion ne suffit pas : quelqu'un déjà connecté le reste
 * jusqu'à l'expiration de son cookie. La suspension ne prendrait alors effet
 * que pour qui se déconnecte de lui-même — c'est-à-dire jamais au moment où
 * elle sert.
 *
 * Le service efface aussi les sessions en base, ce qui règle le cas courant.
 * Cet intergiciel couvre le reste : une session portée par un autre pilote,
 * un « se souvenir de moi » qui reconnecte, et la fenêtre entre deux
 * requêtes.
 */
class RefuseUnCompteSuspendu
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && $request->user()->isSuspended()) {
            $motif = $request->user()->suspension_reason;

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => $motif
                    ? __('Votre compte a été suspendu : :motif', ['motif' => $motif])
                    : __('Votre compte a été suspendu.'),
            ]);
        }

        return $next($request);
    }
}
