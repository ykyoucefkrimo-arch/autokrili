<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(SetLocale::SUPPORTED)],
        ]);

        $request->session()->put('locale', $data['locale']);

        // Retour a la page d'ou vient le visiteur : changer de langue ne doit
        // pas le renvoyer a l'accueil en lui faisant perdre sa recherche.
        return back();
    }
}
