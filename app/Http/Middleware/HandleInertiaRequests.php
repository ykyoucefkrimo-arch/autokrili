<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Le dictionnaire de la langue courante.
     *
     * La cle de cache porte la date du fichier : un dictionnaire mis a jour
     * s'invalide de lui-meme. Avec un `rememberForever` sur la seule locale,
     * une traduction ajoutee ne s'affichait jamais tant que personne ne vidait
     * le cache a la main — et rien ne le signalait.
     *
     * @return array<string, string>
     */
    private function translations(): array
    {
        $locale = app()->getLocale();
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        return \Illuminate\Support\Facades\Cache::remember(
            "translations.{$locale}.".filemtime($path),
            now()->addDay(),
            fn () => json_decode(file_get_contents($path), true) ?? []
        );
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ] : null,
                // The layout needs to know which dashboard to offer and whether
                // the agency is still waiting on moderation.
                'agency' => $request->user()?->isAgency()
                    ? $request->user()->activeAgency()?->only(['id', 'commercial_name', 'status'])
                    : null,
            ],
            // La langue et son dictionnaire (§7.2). Le fichier est petit et
            // ne change qu'au deploiement : l'envoyer avec la page evite un
            // aller-retour au premier affichage.
            // La boite d'envoi n'a de sens qu'avec le mailer « file ».
            'mailboxEnabled' => config('mail.default') === 'file',
            'locale' => app()->getLocale(),
            'translations' => $this->translations(),
            'rtl' => app()->getLocale() === 'ar',
            // One-off messages after a redirect.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
