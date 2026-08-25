<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the interface language (specification 7.2).
 *
 * The choice is kept in the session rather than in the URL: a visitor who
 * switches to Arabic keeps every link they already have, and the SEO URLs of
 * §7.2 stay unique — one page, one address, whatever the language it is read
 * in. The day a full Arabic site is wanted, a `/ar` prefix can be added
 * without moving anything else.
 */
class SetLocale
{
    public const SUPPORTED = ['fr', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            // Faute de choix explicite, la langue du navigateur, puis le
            // francais : c'est la langue de l'administration algerienne.
            $locale = $request->getPreferredLanguage(self::SUPPORTED) ?? config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
