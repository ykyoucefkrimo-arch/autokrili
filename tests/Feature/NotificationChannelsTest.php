<?php

use App\Contracts\SmsGateway;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingReminder;
use App\Notifications\BookingRequested;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\SubscriptionExpiring;
use App\Services\BookingService;
use App\Services\ReferenceDataService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);
    $this->admin->syncRoles([User::ROLE_ADMIN]);

    $this->agency = agencyOn(Plan::GOLD);
    $this->agency->update(['buffer_hours' => 0]);
    $this->vehicle = readyVehicle($this->agency, Vehicle::STATUS_PUBLISHED);
    $this->vehicle->update(['published_at' => now()]);
});

function makeBooking($vehicle, array $overrides = []): Booking
{
    return app(BookingService::class)->request($vehicle, array_merge([
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'client_name' => 'Yacine Haddad',
        'client_phone' => '0555112233',
        'client_email' => 'yacine@test.dz',
    ], $overrides), null);
}

/* --- Canaux configurables (§10) ------------------------------------------ */

it('lit ses canaux dans la configuration plutôt que dans le code', function () {
    $booking = makeBooking($this->vehicle);

    config(['notifications.channels.BookingRequested' => ['mail']]);
    expect((new BookingRequested($booking))->via($this->agency->user))->toBe(['mail']);

    // Activer le SMS pour les demandes est une ligne de configuration.
    config([
        'notifications.channels.BookingRequested' => ['mail', 'sms'],
        'notifications.sms.enabled' => true,
    ]);
    expect((new BookingRequested($booking))->via($this->agency->user))
        ->toBe(['mail', SmsChannel::class]);
});

it('ignore le canal SMS tant qu’aucune passerelle n’est active', function () {
    $booking = makeBooking($this->vehicle);

    config([
        'notifications.channels.BookingConfirmed' => ['mail', 'sms'],
        'notifications.sms.enabled' => false,
    ]);

    // Un canal actif sans passerelle enverrait dans le vide tout en faisant
    // croire que le client a été prévenu.
    expect((new BookingConfirmed($booking))->via($booking->client))->toBe(['mail']);
});

it('envoie le SMS par la passerelle, au format international', function () {
    $booking = makeBooking($this->vehicle);

    // Une instance conservee : la promotion de constructeur ne garde pas une
    // reference, la capture par &$sent serait silencieusement perdue.
    $gateway = new class implements SmsGateway
    {
        public array $sent = [];

        public function send(string $to, string $message): bool
        {
            $this->sent[] = ['to' => $to, 'message' => $message];

            return true;
        }
    };

    $this->app->instance(SmsGateway::class, $gateway);

    $this->app->make(SmsChannel::class)->send($booking->client, new BookingConfirmed($booking));

    expect($gateway->sent)->toHaveCount(1)
        ->and($gateway->sent[0]['message'])->toContain($booking->booking_reference)
        ->and($gateway->sent[0]['to'])->toBe($booking->client->phone);
});

it('passe son tour pour une notification sans texte SMS', function () {
    $gateway = new class implements SmsGateway
    {
        public array $sent = [];

        public function send(string $to, string $message): bool
        {
            $this->sent[] = $to;

            return true;
        }
    };

    $this->app->instance(SmsGateway::class, $gateway);

    // Activer un canal ne doit jamais casser une notification existante.
    $this->app->make(SmsChannel::class)->send(
        $this->agency->user,
        new App\Notifications\AgencyApproved($this->agency)
    );

    expect($gateway->sent)->toBeEmpty();
});

/* --- Rappel J-1 (§10) ----------------------------------------------------- */

it('rappelle la veille du départ aux deux parties', function () {
    Notification::fake();

    $booking = makeBooking($this->vehicle, [
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);
    app(BookingService::class)->confirm($booking);

    expect(app(BookingService::class)->sendReminders())->toBe(1);

    Notification::assertSentTo($booking->client, BookingReminder::class,
        fn ($n) => $n->forAgency === false);
    Notification::assertSentTo($this->agency->user, BookingReminder::class,
        fn ($n) => $n->forAgency === true);
});

it('ne rappelle qu’une fois, même si la commande tourne toutes les heures', function () {
    Notification::fake();

    $booking = makeBooking($this->vehicle, [
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);
    app(BookingService::class)->confirm($booking);

    app(BookingService::class)->sendReminders();

    expect(app(BookingService::class)->sendReminders())->toBe(0)
        ->and($booking->fresh()->reminded_at)->not->toBeNull();
});

it('ne rappelle pas une réservation que l’agence n’a pas acceptée', function () {
    Notification::fake();

    // Rappeler une location non confirmee promettrait une voiture que personne
    // ne s'est engage a remettre.
    makeBooking($this->vehicle, [
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);

    expect(app(BookingService::class)->sendReminders())->toBe(0);
    Notification::assertNotSentTo($this->agency->user, BookingReminder::class);
});

/* --- Échéance : agence ET admin (§10) ------------------------------------ */

it('prévient l’administrateur en même temps que l’agence', function () {
    Notification::fake();

    $this->agency->subscriptions()->update(['status' => Subscription::STATUS_CANCELLED]);
    $this->agency->subscriptions()->create([
        'plan_id' => Plan::where('slug', Plan::GOLD)->value('id'),
        'starts_at' => now()->subMonths(6)->toDateString(),
        'ends_at' => now()->addDays(4)->toDateString(),
        'status' => Subscription::STATUS_ACTIVE,
    ]);

    app(SubscriptionService::class)->warnExpiring();

    // Sans encaissement en ligne, c'est l'administrateur qui relance.
    Notification::assertSentTo($this->agency->user, SubscriptionExpiring::class,
        fn ($n) => $n->forAdmin === false);
    Notification::assertSentTo($this->admin, SubscriptionExpiring::class,
        fn ($n) => $n->forAdmin === true);
});

/* --- Cache des données de référence (§12) -------------------------------- */

it('met les wilayas et la matrice en cache', function () {
    Cache::flush();
    $reference = app(ReferenceDataService::class);

    expect(Cache::has(ReferenceDataService::KEY_WILAYAS))->toBeFalse();

    $reference->wilayas();
    $reference->activePlans();

    expect(Cache::has(ReferenceDataService::KEY_WILAYAS))->toBeTrue()
        ->and(Cache::has(ReferenceDataService::KEY_PLANS))->toBeTrue();
});

it('vide le cache dès qu’un quota de formule change', function () {
    $reference = app(ReferenceDataService::class);
    $reference->activePlans();
    expect(Cache::has(ReferenceDataService::KEY_PLANS))->toBeTrue();

    $gold = Plan::where('slug', Plan::GOLD)->firstOrFail();

    actingAs($this->admin)->put(route('admin.plans.update', $gold), [
        'name' => 'Gold', 'price_dzd' => 40000,
        'max_listings' => 20, 'max_photos' => 5, 'max_users' => 3,
        'has_commune_priority' => true, 'has_wilaya_priority' => false,
        'has_homepage_feature' => false, 'can_reply_reviews' => true,
        'stats_level' => 'advanced', 'is_active' => true,
    ]);

    // Une modification invisible ferait refaire la manipulation a
    // l'administrateur, croyant qu'elle n'a pas pris.
    expect(Cache::has(ReferenceDataService::KEY_PLANS))->toBeFalse();
});

it('sert les mêmes wilayas depuis le cache que depuis la base', function () {
    $reference = app(ReferenceDataService::class);

    $fresh = App\Models\Wilaya::orderBy('code')->pluck('slug');
    $cached = $reference->wilayas()->pluck('slug');

    expect($cached->all())->toBe($fresh->all());
});
