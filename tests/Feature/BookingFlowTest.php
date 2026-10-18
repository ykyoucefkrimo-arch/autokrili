<?php

use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingRequested;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->agency = agencyOn(Plan::GOLD);
    $this->agency->update(['buffer_hours' => 0]);
    $this->vehicle = readyVehicle($this->agency, Vehicle::STATUS_PUBLISHED);
    $this->vehicle->update(['published_at' => now()]);
});

function tunnelPayload(array $overrides = []): array
{
    return array_merge([
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(8)->toDateString(),
        'client_name' => 'Yacine Haddad',
        'client_phone' => '0555112233',
        'client_email' => 'yacine@test.dz',
        'accepts_conditions' => true,
    ], $overrides);
}

/* --- Tunnel public -------------------------------------------------------- */

it('ouvre le tunnel de réservation à un visiteur non connecté', function () {
    get(route('bookings.create', $this->vehicle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Booking/Create')
            ->where('vehicle.id', $this->vehicle->id)
            ->has('unavailableDates'));
});

it('refuse de réserver une annonce qui n’est pas publiée', function () {
    $this->vehicle->update(['status' => Vehicle::STATUS_ARCHIVED]);

    // Detenir l'URL ne doit pas suffire a reserver une voiture retiree.
    get(route('bookings.create', $this->vehicle))->assertNotFound();
    post(route('bookings.store', $this->vehicle), tunnelPayload())->assertNotFound();
});

it('crée la demande, ouvre le compte et connecte le client', function () {
    Notification::fake();

    post(route('bookings.store', $this->vehicle), tunnelPayload())
        ->assertRedirect(route('bookings.confirmation', Booking::first()->booking_reference));

    $booking = Booking::first();

    expect($booking->status)->toBe(Booking::STATUS_PENDING)
        ->and($booking->client_id)->not->toBeNull();

    // Connecte : le client atterrit sur sa reservation sans mot de passe.
    $this->assertAuthenticatedAs($booking->client);
    Notification::assertSentTo($this->agency->user, BookingRequested::class);
});

it('exige l’acceptation des conditions de l’agence', function () {
    post(route('bookings.store', $this->vehicle), tunnelPayload(['accepts_conditions' => false]))
        ->assertSessionHasErrors('accepts_conditions');

    expect(Booking::count())->toBe(0);
});

it('refuse une demande sur des dates déjà bloquées par l’agence', function () {
    AvailabilityBlock::create([
        'vehicle_id' => $this->vehicle->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(9)->toDateString(),
        'reason' => AvailabilityBlock::REASON_MAINTENANCE,
    ]);

    post(route('bookings.store', $this->vehicle), tunnelPayload())
        ->assertSessionHasErrors('availability');

    expect(Booking::count())->toBe(0);
});

it('donne le devis détaillé avant validation', function () {
    $response = get(route('bookings.quote', $this->vehicle).'?'.http_build_query([
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
    ]))->assertOk();

    expect($response->json('days'))->toBe(3)
        ->and($response->json('available'))->toBeTrue()
        ->and($response->json('lines'))->not->toBeEmpty();
});

/* --- Cloisonnement -------------------------------------------------------- */

it('cache une réservation à qui n’y a pas part', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());
    $booking = Booking::first();

    $intrus = User::create([
        'name' => 'Curieux', 'email' => 'curieux@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_CLIENT,
    ]);

    // La reservation porte un telephone, une adresse et un numero de permis.
    actingAs($intrus)->get(route('bookings.confirmation', $booking->booking_reference))->assertForbidden();
    actingAs($intrus)->get(route('bookings.voucher', $booking))->assertForbidden();
});

it('interdit à une agence de toucher aux réservations d’une concurrente', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());
    $booking = Booking::first();

    $autre = agencyOn(Plan::GOLD, email: 'concurrente@test.dz');

    actingAs($autre->user)->get(route('agency.bookings.show', $booking))->assertForbidden();
    actingAs($autre->user)->post(route('agency.bookings.confirm', $booking))->assertForbidden();

    expect($booking->fresh()->status)->toBe(Booking::STATUS_PENDING);
});

it('empêche le client de s’accepter lui-même une réservation', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());
    $booking = Booking::first();

    actingAs($booking->client)->post(route('agency.bookings.confirm', $booking))->assertNotFound();
});

/* --- Côté agence ---------------------------------------------------------- */

it('accepte une demande et prévient le client', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());
    $booking = Booking::first();

    actingAs($this->agency->user)
        ->post(route('agency.bookings.confirm', $booking))
        ->assertRedirect();

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);
    Notification::assertSentTo($booking->client, BookingConfirmed::class);
});

it('exige un motif pour refuser une demande', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());
    $booking = Booking::first();

    actingAs($this->agency->user)
        ->post(route('agency.bookings.refuse', $booking), [])
        ->assertSessionHasErrors('reason');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_PENDING);
});

it('laisse une agence suspendue consulter sans agir', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());
    $booking = Booking::first();

    $this->agency->update(['status' => App\Models\Agency::STATUS_SUSPENDED]);

    actingAs($this->agency->user)->get(route('agency.bookings.index'))->assertOk();
    actingAs($this->agency->user)
        ->post(route('agency.bookings.confirm', $booking))
        ->assertRedirect(route('agency.pending'));

    expect($booking->fresh()->status)->toBe(Booking::STATUS_PENDING);
});

/* --- Calendrier et blocages ---------------------------------------------- */

it('refuse de bloquer des dates déjà réservées', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());

    // Poser un blocage par-dessus cacherait une location deja promise.
    actingAs($this->agency->user)->post(route('agency.blocks.store'), [
        'vehicle_id' => $this->vehicle->id,
        'start_date' => now()->addDays(6)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => AvailabilityBlock::REASON_MAINTENANCE,
    ])->assertSessionHasErrors('block');

    expect(AvailabilityBlock::count())->toBe(0);
});

it('interdit de bloquer le véhicule d’une concurrente', function () {
    $autre = agencyOn(Plan::GOLD, email: 'concurrente@test.dz');

    actingAs($autre->user)->post(route('agency.blocks.store'), [
        'vehicle_id' => $this->vehicle->id,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => AvailabilityBlock::REASON_MAINTENANCE,
    ])->assertSessionHasErrors('vehicle_id');
});

it('affiche le calendrier du mois avec ses occupations', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());

    actingAs($this->agency->user)
        ->get(route('agency.calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Agency/Calendar')
            ->has('vehicles', 1)
            ->has('bookings', 1));
});

/* --- Bon de réservation --------------------------------------------------- */

it('ne délivre le bon qu’une fois la réservation confirmée', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());
    $booking = Booking::first();

    // Une demande en attente n'est pas un contrat : pas de bon a presenter.
    actingAs($booking->client)->get(route('bookings.voucher', $booking))->assertNotFound();

    actingAs($this->agency->user)->post(route('agency.bookings.confirm', $booking));

    $response = actingAs($booking->client->fresh())->get(route('bookings.voucher', $booking))->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('liste au client ses réservations, à venir et passées', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());
    $booking = Booking::first();

    actingAs($booking->client)
        ->get(route('bookings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Client/Bookings')
            ->has('upcoming', 1)
            ->has('past', 0));
});

it('laisse le client annuler avec un motif', function () {
    Notification::fake();
    post(route('bookings.store', $this->vehicle), tunnelPayload());
    $booking = Booking::first();

    actingAs($booking->client)
        ->post(route('bookings.cancel', $booking), ['reason' => 'Voyage reporté.'])
        ->assertRedirect();

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($booking->fresh()->cancelled_by)->toBe('client');
});

/* --- Connexion a la confirmation (2026-10-18) ----------------------------- */

/**
 * La prise de session, telle qu'elle existait en production.
 *
 * Le service cherchait le client par adresse, et le controleur connectait le
 * visiteur au compte trouve. Taper l'adresse d'un autre ouvrait sa session.
 * L'administrateur etait joignable ainsi : son adresse se devine.
 */
it('ne connecte jamais quelqu un a un compte qu il n a pas prouve', function () {
    $victime = User::create([
        'name' => 'Admin', 'email' => 'admin@autokrili.dz',
        'password' => Hash::make('un-vrai-mot-de-passe'),
        'role' => User::ROLE_ADMIN,
    ]);

    post(route('bookings.store', $this->vehicle), tunnelPayload([
        'client_email' => 'admin@autokrili.dz',
    ]))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(Booking::count())->toBe(0)
        ->and($victime->fresh()->bookings()->count())->toBe(0);
});

it('reprend la reservation mise de cote une fois la personne identifiee', function () {
    $client = User::create([
        'name' => 'Yacine Haddad', 'email' => 'yacine@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_CLIENT,
    ]);

    post(route('bookings.store', $this->vehicle), tunnelPayload())
        ->assertRedirect(route('login'));

    // Rien n'est cree avant la preuve, et rien n'est perdu non plus.
    expect(Booking::count())->toBe(0);
    expect(session('url.intended'))->toBe(route('bookings.resume'));

    actingAs($client)->get(route('bookings.resume'))->assertRedirect();

    $reservation = Booking::firstOrFail();
    expect($reservation->client_id)->toBe($client->id)
        ->and(session()->has(App\Http\Controllers\Public\BookingController::EN_ATTENTE))->toBeFalse();
});

it('jette la reservation si on se connecte avec une autre adresse', function () {
    User::create([
        'name' => 'Yacine', 'email' => 'yacine@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_CLIENT,
    ]);
    $autre = User::create([
        'name' => 'Nadia', 'email' => 'nadia@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_CLIENT,
    ]);

    post(route('bookings.store', $this->vehicle), tunnelPayload());

    // Sinon il suffirait de commencer une reservation au nom d'un autre, puis
    // de se connecter chez soi, pour se l'approprier.
    actingAs($autre)->get(route('bookings.resume'))->assertRedirect(route('search'));

    expect(Booking::count())->toBe(0);
});

it('cree et connecte sans detour quand l adresse est inconnue', function () {
    post(route('bookings.store', $this->vehicle), tunnelPayload([
        'client_email' => 'personne-connue@test.dz',
    ]))->assertRedirect();

    // Le compte vient d'etre cree pour cette adresse : le connecter ne donne
    // acces a rien qui existait avant.
    $this->assertAuthenticated();
    expect(Booking::count())->toBe(1);
});
