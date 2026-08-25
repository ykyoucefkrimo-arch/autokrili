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

    public function create(): Response
    {
        return Inertia::render('Auth/RegisterAgency', [
            // Communes ship with the wilayas: the form filters them in the
            // browser, which spares a round trip on every wilaya change.
            'wilayas' => $this->reference->wilayas(),
        ]);
    }

    public function store(RegisterAgencyRequest $request): RedirectResponse
    {
        $agency = $this->registration->register(
            $request->validated(),
            $request->file('logo'),
            $request->file('trade_register_file'),
        );

        // Agencies must verify their email; the event triggers Laravel's own
        // verification notification.
        event(new Registered($agency->user));

        $agency->user->notify(new AgencyRegistered($agency));
        Notification::send(
            User::where('role', User::ROLE_ADMIN)->get(),
            new AgencyRegistered($agency, forAdmin: true)
        );

        Auth::login($agency->user);

        return redirect()->route('agency.pending');
    }
}
