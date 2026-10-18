<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The booking tunnel of specification 7.1: dates and options, then details,
 * then a summary the client validates.
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
    ) {
    }

    public function create(Request $request, Vehicle $vehicle): Response
    {
        $this->assertBookable($vehicle);

        $vehicle->load(['agency:id,commercial_name,phone,min_driver_age,default_deposit_dzd,rental_conditions',
            'coverPhoto', 'pricingRules', 'pickupCommune:id,name_fr', 'pickupWilaya:id,name_fr']);

        $user = $request->user();

        return Inertia::render('Public/Booking/Create', [
            'vehicle' => [
                'id' => $vehicle->id,
                'slug' => $vehicle->slug,
                'title' => $vehicle->title(),
                'with_driver_available' => $vehicle->with_driver_available,
                'driver_price_per_day' => $vehicle->driver_price_per_day,
                'pickup' => trim(($vehicle->pickupCommune?->name_fr ?? '').', '.($vehicle->pickupWilaya?->name_fr ?? ''), ', '),
                'cover_url' => $vehicle->coverPhoto
                    ? Storage::disk('public')->url($vehicle->coverPhoto->path_card ?? $vehicle->coverPhoto->path)
                    : null,
                'agency' => [
                    'commercial_name' => $vehicle->agency->commercial_name,
                    'phone' => $vehicle->agency->phone,
                    'min_driver_age' => $vehicle->agency->min_driver_age,
                    'deposit_dzd' => $vehicle->agency->default_deposit_dzd,
                    'rental_conditions' => $vehicle->agency->rental_conditions,
                ],
            ],
            // The calendar greys these out rather than letting the client pick
            // a range the server will refuse two screens later.
            'unavailableDates' => $this->availability->unavailableDates($vehicle),
            'prefill' => [
                'start_date' => $request->query('start'),
                'end_date' => $request->query('end'),
                'client_name' => $user?->name,
                'client_email' => $user?->email,
                'client_phone' => $user?->phone,
            ],
            'isAuthenticated' => (bool) $user,
        ]);
    }

    /**
     * The live quote. The pricing rules are combined by a service the browser
     * has no copy of; recomputing them in JavaScript would give the client a
     * total the server might not agree with.
     */
    public function quote(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'with_driver' => ['boolean'],
        ]);

        $start = Carbon::parse($data['start_date'])->toDateString();
        $end = Carbon::parse($data['end_date'])->toDateString();

        $days = $this->bookings->countDays($start, $end);
        $quote = $this->pricing->quote(
            $vehicle,
            $days,
            (bool) ($data['with_driver'] ?? false) && $vehicle->with_driver_available
        );

        return response()->json([
            'available' => $this->availability->isAvailable($vehicle, $start, $end),
            'reason' => $this->availability->reasonUnavailable($vehicle, $start, $end),
            'deposit' => (int) $vehicle->agency->default_deposit_dzd,
        ] + $quote);
    }

    public const EN_ATTENTE = 'reservation.en_attente';

    public function store(BookingRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->assertBookable($vehicle);

        $donnees = $request->validated();

        /*
         * L'adresse donnee appartient deja a quelqu'un : on s'arrete ici.
         *
         * Faille trouvee le 2026-10-18, et elle etait grave. Le service
         * cherchait le client PAR ADRESSE, et ce controleur connectait le
         * visiteur au compte trouve. Taper l'adresse d'un autre dans le
         * formulaire ouvrait donc sa session — celle de l'administrateur
         * comprise, dont l'adresse se devine.
         *
         * La reservation est mise de cote, pas perdue, et reprise telle quelle
         * apres la connexion. C'est le parcours des grands loueurs : on
         * compose son sejour en visiteur, on s'identifie au moment de
         * confirmer.
         */
        if (! $request->user() && $this->adresseDejaPrise($donnees['client_email'] ?? null)) {
            $request->session()->put(self::EN_ATTENTE, [
                'vehicle_id' => $vehicle->id,
                'donnees' => $donnees,
            ]);
            $request->session()->put('url.intended', route('bookings.resume'));

            return redirect()->route('login')->with(
                'status',
                __('Un compte existe déjà avec cette adresse. Connectez-vous pour confirmer votre réservation.')
            );
        }

        try {
            $booking = $this->bookings->request($vehicle, $donnees, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['availability' => $e->getMessage()]);
        }

        // Le compte vient d'etre cree pour cette adresse-la : le connecter ne
        // donne acces a rien qui existait avant, et lui epargne un mot de
        // passe qu'il n'a pas encore choisi.
        if (! $request->user() && $booking->client) {
            Auth::login($booking->client);
        }

        return redirect()->route('bookings.confirmation', $booking->booking_reference);
    }

    /**
     * Reprend la reservation mise de cote, une fois la personne identifiee.
     *
     * Le controle qui compte est celui de l'adresse : se connecter avec un
     * autre compte ne doit pas permettre de recuperer la reservation d'un
     * tiers. En cas d'ecart on jette la demande plutot que de la rattacher au
     * mauvais dossier.
     */
    public function resume(Request $request): RedirectResponse
    {
        $attente = $request->session()->pull(self::EN_ATTENTE);

        if (! $attente) {
            return redirect()->route('search');
        }

        $vehicle = Vehicle::find($attente['vehicle_id']);
        $donnees = $attente['donnees'];
        $voulue = mb_strtolower((string) ($donnees['client_email'] ?? ''));

        if (! $vehicle || ! hash_equals($voulue, mb_strtolower((string) $request->user()->email))) {
            return redirect()->route('search')
                ->withErrors(['client_email' => __('Cette réservation a été commencée avec une autre adresse.')]);
        }

        $this->assertBookable($vehicle);

        try {
            $booking = $this->bookings->request($vehicle, $donnees, $request->user());
        } catch (RuntimeException $e) {
            // Le temps de se connecter, quelqu'un a pu prendre le creneau.
            return redirect()->route('bookings.create', [
                'vehicle' => $vehicle->id,
                'start' => $donnees['start_date'] ?? null,
                'end' => $donnees['end_date'] ?? null,
            ])->withErrors(['availability' => $e->getMessage()]);
        }

        return redirect()->route('bookings.confirmation', $booking->booking_reference);
    }

    /** Une adresse deja rattachee a un compte, quel que soit son role. */
    private function adresseDejaPrise(?string $courriel): bool
    {
        return filled($courriel) && User::where('email', $courriel)->exists();
    }

    public function confirmation(Request $request, Booking $booking): Response
    {
        $this->authorize('view', $booking);
        $booking->load(['vehicle', 'agency:id,commercial_name,phone,whatsapp']);

        return Inertia::render('Public/Booking/Confirmation', [
            'booking' => [
                'reference' => $booking->booking_reference,
                'status' => $booking->status,
                'vehicle' => $booking->vehicle?->title(),
                'start_date' => $booking->start_date->translatedFormat('d F Y'),
                'end_date' => $booking->end_date->translatedFormat('d F Y'),
                'total_days' => $booking->total_days,
                'total_price_dzd' => $booking->total_price_dzd,
                'deposit_dzd' => $booking->deposit_dzd,
                'pickup_location' => $booking->pickup_location,
                'expires_at' => $booking->expires_at?->translatedFormat('d F Y à H:i'),
                'agency' => $booking->agency->only(['commercial_name', 'phone', 'whatsapp']),
            ],
        ]);
    }

    /**
     * A listing must be publicly visible to be bookable. Without this check a
     * client holding the URL could keep booking a car the agency has pulled.
     */
    private function assertBookable(Vehicle $vehicle): void
    {
        abort_unless(
            $vehicle->isPublished() && $vehicle->agency?->status === Agency::STATUS_APPROVED,
            404
        );
    }
}
