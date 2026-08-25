<?php

use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PricingService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->agency = agencyOn(Plan::GOLD);
    $this->vehicle = readyVehicle($this->agency, Vehicle::STATUS_PUBLISHED);
    $this->vehicle->update(['published_at' => now()]);
    $this->availability = app(AvailabilityService::class);
    $this->bookings = app(BookingService::class);
});

/** A booking held directly in the database, bypassing the service. */
function holdDates(Vehicle $vehicle, string $start, string $end, string $status = Booking::STATUS_PENDING): Booking
{
    return Booking::create([
        'booking_reference' => 'DZ-'.now()->year.'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'vehicle_id' => $vehicle->id,
        'agency_id' => $vehicle->agency_id,
        'start_date' => $start,
        'end_date' => $end,
        'total_days' => 1,
        'client_name' => 'Client Test',
        'client_phone' => '0555112233',
        'status' => $status,
        'expires_at' => now()->addDay(),
    ]);
}

function bookingPayload(array $overrides = []): array
{
    return array_merge([
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(8)->toDateString(),
        'client_name' => 'Yacine Haddad',
        'client_phone' => '0555112233',
        'client_email' => 'yacine@test.dz',
        'driver_license_number' => '16-2019-123456',
        'with_driver' => false,
        'accepts_conditions' => true,
    ], $overrides);
}

/* --- Disponibilité (§6.1) ----------------------------------------------- */

it('refuse une plage qui chevauche une réservation, même d’un seul jour', function () {
    holdDates($this->vehicle, now()->addDays(10)->toDateString(), now()->addDays(15)->toDateString());

    // Le test de chevauchement est en intervalle ferme : une location qui
    // finit le jour ou une autre commence, c'est la meme journee de voiture.
    expect($this->availability->isAvailable($this->vehicle,
        now()->addDays(15)->toDateString(), now()->addDays(20)->toDateString()))->toBeFalse()
        ->and($this->availability->isAvailable($this->vehicle,
            now()->addDays(5)->toDateString(), now()->addDays(10)->toDateString()))->toBeFalse()
        // Englobante
        ->and($this->availability->isAvailable($this->vehicle,
            now()->addDays(8)->toDateString(), now()->addDays(20)->toDateString()))->toBeFalse();
});

it('laisse libre une plage qui ne chevauche rien', function () {
    $this->agency->update(['buffer_hours' => 0]);
    holdDates($this->vehicle, now()->addDays(10)->toDateString(), now()->addDays(15)->toDateString());

    expect($this->availability->isAvailable($this->vehicle,
        now()->addDays(16)->toDateString(), now()->addDays(20)->toDateString()))->toBeTrue();
});

it('ne laisse bloquer les dates que par les réservations vivantes', function () {
    $this->agency->update(['buffer_hours' => 0]);

    foreach ([Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED, Booking::STATUS_COMPLETED] as $status) {
        Booking::query()->delete();
        holdDates($this->vehicle, now()->addDays(10)->toDateString(), now()->addDays(15)->toDateString(), $status);

        // Une reservation annulee, expiree ou terminee rend ses dates : sans
        // cela le calendrier se remplirait definitivement.
        expect($this->availability->isAvailable($this->vehicle,
            now()->addDays(10)->toDateString(), now()->addDays(15)->toDateString()))->toBeTrue();
    }
});

it('respecte le délai tampon entre deux locations', function () {
    // Quatre heures de nettoyage coutent une journee au calendrier : la
    // voiture ne se remet pas deux fois le meme jour.
    $this->agency->update(['buffer_hours' => 4]);
    holdDates($this->vehicle, now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString());

    expect($this->availability->isAvailable($this->vehicle,
        now()->addDays(13)->toDateString(), now()->addDays(15)->toDateString()))->toBeFalse()
        ->and($this->availability->isAvailable($this->vehicle,
            now()->addDays(14)->toDateString(), now()->addDays(15)->toDateString()))->toBeTrue();
});

it('bloque les dates couvertes par une indisponibilité manuelle', function () {
    AvailabilityBlock::create([
        'vehicle_id' => $this->vehicle->id,
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(12)->toDateString(),
        'reason' => AvailabilityBlock::REASON_MAINTENANCE,
    ]);

    expect($this->availability->isAvailable($this->vehicle,
        now()->addDays(11)->toDateString(), now()->addDays(11)->toDateString()))->toBeFalse();
});

it('liste les dates à griser dans le calendrier', function () {
    $this->agency->update(['buffer_hours' => 0]);
    holdDates($this->vehicle, now()->addDays(3)->toDateString(), now()->addDays(5)->toDateString());

    $dates = $this->availability->unavailableDates($this->vehicle);

    expect($dates)->toContain(now()->addDays(3)->toDateString())
        ->toContain(now()->addDays(4)->toDateString())
        ->toContain(now()->addDays(5)->toDateString())
        ->not->toContain(now()->addDays(6)->toDateString());
});

/* --- Prix (§6.4) --------------------------------------------------------- */

it('applique le tarif le plus avantageux plutôt que le prix par jour', function () {
    $this->vehicle->pricingRules()->delete();
    $this->vehicle->pricingRules()->create(['duration_type' => PricingRule::DAILY, 'price_dzd' => 4500, 'min_days' => 1]);
    $this->vehicle->pricingRules()->create(['duration_type' => PricingRule::WEEKLY, 'price_dzd' => 28000, 'min_days' => 7]);

    $quote = app(PricingService::class)->quote($this->vehicle->fresh(), 7);

    // 7 x 4 500 = 31 500 ; la semaine est a 28 000.
    expect($quote['total'])->toBe(28000);
});

it('applique le tarif semaine même sur six jours quand il est moins cher', function () {
    $this->vehicle->pricingRules()->delete();
    $this->vehicle->pricingRules()->create(['duration_type' => PricingRule::DAILY, 'price_dzd' => 5000, 'min_days' => 1]);
    $this->vehicle->pricingRules()->create(['duration_type' => PricingRule::WEEKLY, 'price_dzd' => 28000, 'min_days' => 7]);

    // 6 x 5 000 = 30 000 : la semaine coute moins cher que six jours.
    expect(app(PricingService::class)->quote($this->vehicle->fresh(), 6)['total'])->toBe(28000);
});

it('combine les règles et détaille le calcul au client', function () {
    $this->vehicle->pricingRules()->delete();
    $this->vehicle->pricingRules()->create(['duration_type' => PricingRule::DAILY, 'price_dzd' => 4000, 'min_days' => 1]);
    $this->vehicle->pricingRules()->create(['duration_type' => PricingRule::WEEKLY, 'price_dzd' => 24000, 'min_days' => 7]);

    $quote = app(PricingService::class)->quote($this->vehicle->fresh(), 9);

    // Une semaine (24 000) + deux jours (8 000) = 32 000.
    expect($quote['total'])->toBe(32000)
        ->and($quote['lines'])->toHaveCount(2)
        ->and($quote['lines'][0]['label'])->toBe('Semaine');
});

it('facture le chauffeur par jour, en plus du véhicule', function () {
    $this->vehicle->update(['with_driver_available' => true, 'driver_price_per_day' => 3000]);

    $quote = app(PricingService::class)->quote($this->vehicle->fresh(), 3, withDriver: true);

    expect($quote['driver'])->toBe(9000)
        ->and($quote['total'])->toBe($quote['vehicle'] + 9000);
});

/* --- Cycle de vie (§6.2) ------------------------------------------------- */

it('crée une demande en attente et gèle les dates', function () {
    $booking = $this->bookings->request($this->vehicle, bookingPayload(), null);

    expect($booking->status)->toBe(Booking::STATUS_PENDING)
        ->and($booking->booking_reference)->toStartWith('DZ-'.now()->year.'-')
        ->and($booking->total_days)->toBe(4)
        ->and($booking->expires_at)->not->toBeNull()
        // Une demande en attente tient les dates : deux clients ne peuvent pas
        // faire la queue sur le meme creneau en s'entendant dire oui tous deux.
        ->and($this->availability->isAvailable($this->vehicle,
            $booking->start_date->toDateString(), $booking->end_date->toDateString()))->toBeFalse();
});

it('crée le compte client au passage plutôt que de l’exiger avant', function () {
    $this->bookings->request($this->vehicle, bookingPayload(['client_email' => 'nouveau@test.dz']), null);

    $client = User::where('email', 'nouveau@test.dz')->first();

    expect($client)->not->toBeNull()
        ->and($client->role)->toBe(User::ROLE_CLIENT);
});

it('réutilise le compte d’un client déjà connu', function () {
    $existing = User::create([
        'name' => 'Deja Client', 'email' => 'connu@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_CLIENT,
    ]);

    $booking = $this->bookings->request($this->vehicle, bookingPayload(['client_email' => 'connu@test.dz']), null);

    expect($booking->client_id)->toBe($existing->id)
        ->and(User::where('email', 'connu@test.dz')->count())->toBe(1);
});

it('gèle le prix au moment de la demande', function () {
    $booking = $this->bookings->request($this->vehicle, bookingPayload(), null);
    $price = $booking->total_price_dzd;

    // L'agence augmente ses tarifs le lendemain : la demande deja faite garde
    // le prix sur lequel le client s'est engage.
    $this->vehicle->pricingRules()->update(['price_dzd' => 99000]);

    expect($booking->fresh()->total_price_dzd)->toBe($price)
        ->and($booking->price_breakdown)->not->toBeEmpty();
});

it('refuse une demande sur des dates déjà prises', function () {
    holdDates($this->vehicle, now()->addDays(5)->toDateString(), now()->addDays(8)->toDateString());

    expect(fn () => $this->bookings->request($this->vehicle, bookingPayload(), null))
        ->toThrow(RuntimeException::class);
});

it('suit le cycle demande → confirmée → en cours → terminée', function () {
    $booking = $this->bookings->request($this->vehicle, bookingPayload(), null);

    expect($this->bookings->confirm($booking)->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($booking->fresh()->confirmed_at)->not->toBeNull()
        // Confirmee : le compte a rebours de 24 h n'a plus lieu d'etre.
        ->and($booking->fresh()->expires_at)->toBeNull();

    expect($this->bookings->start($booking->fresh())->status)->toBe(Booking::STATUS_IN_PROGRESS);
    expect($this->bookings->complete($booking->fresh())->status)->toBe(Booking::STATUS_COMPLETED);
});

it('libère les dates quand le véhicule est rendu', function () {
    $this->agency->update(['buffer_hours' => 0]);
    $booking = $this->bookings->request($this->vehicle, bookingPayload(), null);
    $this->bookings->confirm($booking);
    $this->bookings->start($booking->fresh());
    $this->bookings->complete($booking->fresh());

    // C'est ce qui permet de relouer une voiture rapportee en avance.
    expect($this->availability->isAvailable($this->vehicle,
        $booking->start_date->toDateString(), $booking->end_date->toDateString()))->toBeTrue();
});

it('interdit les transitions qui n’ont pas de sens', function () {
    $booking = $this->bookings->request($this->vehicle, bookingPayload(), null);

    // Un depart sans confirmation, c'est une voiture remise a quelqu'un que
    // l'agence n'a jamais accepte.
    expect(fn () => $this->bookings->start($booking))->toThrow(RuntimeException::class);
    expect(fn () => $this->bookings->complete($booking))->toThrow(RuntimeException::class);
});

/* --- Double réservation (§6.3) ------------------------------------------- */

it('empêche deux confirmations concurrentes sur les mêmes dates', function () {
    $this->agency->update(['buffer_hours' => 0]);

    // Deux demandes deposees avant que l'agence ne reponde : le service refuse
    // la seconde a la creation, on les force donc en base pour rejouer le cas
    // ou les deux existent et arrivent ensemble a la confirmation.
    $first = holdDates($this->vehicle, now()->addDays(5)->toDateString(), now()->addDays(8)->toDateString());
    $second = holdDates($this->vehicle, now()->addDays(6)->toDateString(), now()->addDays(9)->toDateString());

    $this->bookings->confirm($first);

    // La revérification sous verrou est ce qui separe une plateforme d'un
    // fichier Excel partage.
    expect(fn () => $this->bookings->confirm($second))->toThrow(RuntimeException::class);

    expect($first->fresh()->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($second->fresh()->status)->toBe(Booking::STATUS_PENDING);
});

/* --- Annulation et expiration -------------------------------------------- */

it('annule des deux côtés avec un motif, et rend les dates', function () {
    $this->agency->update(['buffer_hours' => 0]);
    $booking = $this->bookings->request($this->vehicle, bookingPayload(), null);

    $this->bookings->cancel($booking, 'Changement de programme.', 'client');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($booking->fresh()->cancelled_by)->toBe('client')
        ->and($this->availability->isAvailable($this->vehicle,
            $booking->start_date->toDateString(), $booking->end_date->toDateString()))->toBeTrue();
});

it('refuse d’annuler une location déjà partie', function () {
    $booking = $this->bookings->request($this->vehicle, bookingPayload(), null);
    $this->bookings->confirm($booking);
    $this->bookings->start($booking->fresh());

    expect(fn () => $this->bookings->cancel($booking->fresh(), 'Trop tard.', 'client'))
        ->toThrow(RuntimeException::class);
});

it('expire les demandes restées sans réponse et libère les dates', function () {
    $this->agency->update(['buffer_hours' => 0]);
    $booking = $this->bookings->request($this->vehicle, bookingPayload(), null);

    // 24 h plus tard, l'agence n'a rien fait.
    $booking->update(['expires_at' => now()->subHour()]);

    expect($this->bookings->expirePending())->toBe(1)
        ->and($booking->fresh()->status)->toBe(Booking::STATUS_EXPIRED)
        ->and($this->availability->isAvailable($this->vehicle,
            $booking->start_date->toDateString(), $booking->end_date->toDateString()))->toBeTrue();
});

it('laisse tranquilles les demandes encore dans les temps', function () {
    $this->bookings->request($this->vehicle, bookingPayload(), null);

    expect($this->bookings->expirePending())->toBe(0);
});

it('numérote les réservations par année, sans trou ni doublon', function () {
    $first = $this->bookings->request($this->vehicle, bookingPayload(), null);
    $second = $this->bookings->request($this->vehicle, bookingPayload([
        'start_date' => now()->addDays(30)->toDateString(),
        'end_date' => now()->addDays(32)->toDateString(),
    ]), null);

    expect($first->booking_reference)->toBe('DZ-'.now()->year.'-00001')
        ->and($second->booking_reference)->toBe('DZ-'.now()->year.'-00002');
});
