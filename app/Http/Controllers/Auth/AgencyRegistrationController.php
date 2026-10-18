<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAgencyRequest;
use App\Models\User;
use App\Models\Wilaya;
use App\Services\ReferenceDataService;
use App\Notifications\AgencyRegistered;
use App\Services\AgencyRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class AgencyRegistrationController extends Controller
{
    public function __construct(
        private readonly AgencyRegistrationService $registration,
        private readonly ReferenceDataService $reference,
    ) {
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if ($dehors = $this->ecarteCeuxQuiEnOntDeja($request)) {
            return $dehors;
        }

        return Inertia::render('Auth/RegisterAgency', [
            // Communes ship with the wilayas: the form filters them in the
            // browser, which spares a round trip on every wilaya change.
            'wilayas' => $this->reference->wilayas(),
            /*
             * Le compte connecte, s'il y en a un. Le formulaire masque alors
             * les champs d'identifiants : redemander une adresse et un mot de
             * passe a quelqu'un d'identifie creerait un second compte pour la
             * meme personne.
             */
            'compte' => $request->user()?->only(['name', 'email']),
        ]);
    }

    public function store(RegisterAgencyRequest $request): RedirectResponse
    {
        if ($dehors = $this->ecarteCeuxQuiEnOntDeja($request)) {
            return $dehors;
        }

        $proprietaire = $request->user();

        $agency = $this->registration->register(
            $request->validated(),
            $request->file('logo'),
            $request->file('trade_register_file'),
            $proprietaire,
        );

        // Agencies must verify their email; the event triggers Laravel's own
        // verification notification. Un compte deja verifie — celui arrive
        // par Google, par exemple — n'a rien a reverifier.
        if (! $agency->user->hasVerifiedEmail()) {
            event(new Registered($agency->user));
        }

        $agency->user->notify(new AgencyRegistered($agency));
        Notification::send(
            User::where('role', User::ROLE_ADMIN)->get(),
            new AgencyRegistered($agency, forAdmin: true)
        );

        if (! $proprietaire) {
            Auth::login($agency->user);
        }

        return redirect()->route('agency.pending');
    }

    /**
     * Une agence par compte.
     *
     * Celui qui en a deja une n'a rien a faire sur ce formulaire : on le
     * repose chez lui plutot que de le laisser remplir trente champs pour
     * une demande qui serait refusee a l'enregistrement.
     */
    private function ecarteCeuxQuiEnOntDeja(Request $request): ?RedirectResponse
    {
        return $request->user()?->activeAgency()
            ? redirect()->route('agency.dashboard')
            : null;
    }
}
