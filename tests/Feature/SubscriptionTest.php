<?php

use App\Models\Agency;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\PlanChangeHandled;
use App\Notifications\SubscriptionDowngraded;
use App\Notifications\SubscriptionExpiring;
use App\Services\SubscriptionService;
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

    $this->subscriptions = app(SubscriptionService::class);
});

/** Places an agency on a plan with a chosen end date, bypassing the service. */
function subscribeUntil(Agency $agency, string $planSlug, ?string $endsAt): Subscription
{
    $agency->subscriptions()->update(['status' => Subscription::STATUS_CANCELLED]);

    return $agency->subscriptions()->create([
        'plan_id' => Plan::where('slug', $planSlug)->firstOrFail()->id,
        'starts_at' => now()->subMonths(6)->toDateString(),
        'ends_at' => $endsAt,
        'status' => Subscription::STATUS_ACTIVE,
    ]);
}

/* --- Rétrogradation (§4.3) ------------------------------------------------ */

it('rétrograde en Silver une formule expirée', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    subscribeUntil($agency, Plan::GOLD, now()->subDay()->toDateString());

    expect($this->subscriptions->downgradeExpired())->toBe(1);

    // Toujours un abonnement actif : chaque contrôle de quota pose une question
    // et doit obtenir une réponse.
    expect($agency->fresh()->currentPlan()->slug)->toBe(Plan::SILVER);
});

it('laisse tranquille une formule encore valide ou sans échéance', function () {
    $valide = agencyOn(Plan::GOLD, email: 'valide@test.dz');
    subscribeUntil($valide, Plan::GOLD, now()->addMonth()->toDateString());

    $perpetuelle = agencyOn(Plan::GOLD, email: 'perpetuelle@test.dz');
    subscribeUntil($perpetuelle, Plan::GOLD, null);

    expect($this->subscriptions->downgradeExpired())->toBe(0)
        ->and($valide->fresh()->currentPlan()->slug)->toBe(Plan::GOLD)
        ->and($perpetuelle->fresh()->currentPlan()->slug)->toBe(Plan::GOLD);
});

it('archive les annonces excédentaires, les plus anciennes d’abord', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    subscribeUntil($agency, Plan::GOLD, now()->subDay()->toDateString());

    $silverMax = Plan::where('slug', Plan::SILVER)->value('max_listings');

    // Une de plus que ce que Silver autorise, créées dans un ordre connu.
    $vehicles = collect(range(0, $silverMax))->map(function ($i) use ($agency) {
        $vehicle = readyVehicle($agency, Vehicle::STATUS_PUBLISHED);
        $vehicle->forceFill(['created_at' => now()->subDays(100 - $i)])->save();

        return $vehicle;
    });

    $this->subscriptions->downgradeExpired();

    // La plus ancienne part, la plus récente reste : le travail le plus frais
    // de l'agence est celui auquel elle tient.
    expect($vehicles->first()->fresh()->status)->toBe(Vehicle::STATUS_ARCHIVED)
        ->and($vehicles->last()->fresh()->status)->toBe(Vehicle::STATUS_PUBLISHED)
        ->and($agency->vehicles()->where('status', Vehicle::STATUS_ARCHIVED)->count())->toBe(1);
});

it('ne supprime jamais une annonce archivée par une rétrogradation', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    subscribeUntil($agency, Plan::GOLD, now()->subDay()->toDateString());

    $silverMax = Plan::where('slug', Plan::SILVER)->value('max_listings');
    collect(range(0, $silverMax))->each(fn () => readyVehicle($agency, Vehicle::STATUS_PUBLISHED));

    $this->subscriptions->downgradeExpired();

    $archived = $agency->vehicles()->where('status', Vehicle::STATUS_ARCHIVED)->first();

    // Photos et tarifs survivent : l'agence republie en changeant de formule.
    expect($archived->photos()->count())->toBeGreaterThan(0)
        ->and($archived->pricingRules()->count())->toBeGreaterThan(0)
        ->and($archived->deleted_at)->toBeNull();
});

it('nomme à l’agence les annonces qu’elle vient de perdre', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    subscribeUntil($agency, Plan::GOLD, now()->subDay()->toDateString());

    $silverMax = Plan::where('slug', Plan::SILVER)->value('max_listings');
    collect(range(0, $silverMax))->each(fn () => readyVehicle($agency, Vehicle::STATUS_PUBLISHED));

    $this->subscriptions->downgradeExpired();

    Notification::assertSentTo($agency->user, SubscriptionDowngraded::class,
        fn ($notification) => $notification->archived->count() === 1);
});

it('ne prévient personne quand la rétrogradation n’archive rien', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    subscribeUntil($agency, Plan::GOLD, now()->subDay()->toDateString());
    readyVehicle($agency, Vehicle::STATUS_PUBLISHED);

    $this->subscriptions->downgradeExpired();

    // Un email « vos annonces ont été archivées » alors que rien ne l'a été
    // ferait courir l'agence pour rien.
    Notification::assertNotSentTo($agency->user, SubscriptionDowngraded::class);
});

it('n’archive rien pour une formule illimitée', function () {
    Notification::fake();
    $agency = agencyOn(Plan::PLATINIUM);
    collect(range(1, 12))->each(fn () => readyVehicle($agency, Vehicle::STATUS_PUBLISHED));

    expect($this->subscriptions->archiveExcessListings($agency))->toBeEmpty()
        ->and($agency->vehicles()->where('status', Vehicle::STATUS_PUBLISHED)->count())->toBe(12);
});

/* --- Avertissement d'échéance -------------------------------------------- */

it('prévient sept jours avant l’échéance, une seule fois', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    $subscription = subscribeUntil($agency, Plan::GOLD, now()->addDays(5)->toDateString());

    expect($this->subscriptions->warnExpiring())->toBe(1);
    Notification::assertSentTo($agency->user, SubscriptionExpiring::class);

    // Idempotent : la commande tourne tous les jours, l'agence est prévenue une fois.
    expect($this->subscriptions->warnExpiring())->toBe(0)
        ->and($subscription->fresh()->expiry_notified_at)->not->toBeNull();
});

it('ne prévient pas une échéance encore lointaine', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    subscribeUntil($agency, Plan::GOLD, now()->addMonths(2)->toDateString());

    expect($this->subscriptions->warnExpiring())->toBe(0);
    Notification::assertNothingSent();
});

/* --- Matrice modifiable par l'admin (§4.1) ------------------------------- */

it('cache l’écran des formules à qui n’est pas administrateur', function () {
    $agency = agencyOn(Plan::GOLD);

    actingAs($agency->user)->get(route('admin.plans.index'))->assertNotFound();
});

it('laisse l’administrateur modifier la matrice', function () {
    $gold = Plan::where('slug', Plan::GOLD)->firstOrFail();

    actingAs($this->admin)->put(route('admin.plans.update', $gold), [
        'name' => 'Gold', 'price_dzd' => 45000,
        'max_listings' => 25, 'max_photos' => 8, 'max_users' => 4,
        'has_commune_priority' => true, 'has_wilaya_priority' => false,
        'has_homepage_feature' => false, 'can_reply_reviews' => true,
        'stats_level' => 'advanced', 'badge_label' => 'Gold', 'badge_color' => '#d4af37',
        'is_active' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($gold->fresh()->max_listings)->toBe(25)
        ->and($gold->fresh()->max_photos)->toBe(8);
});

it('applique immédiatement une baisse de quota, en archivant ce qui dépasse', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    collect(range(1, 4))->each(fn () => readyVehicle($agency, Vehicle::STATUS_PUBLISHED));

    $gold = Plan::where('slug', Plan::GOLD)->firstOrFail();

    // L'administrateur ramène Gold à deux annonces.
    actingAs($this->admin)->put(route('admin.plans.update', $gold), [
        'name' => 'Gold', 'price_dzd' => 40000,
        'max_listings' => 2, 'max_photos' => 5, 'max_users' => 3,
        'has_commune_priority' => true, 'has_wilaya_priority' => false,
        'has_homepage_feature' => false, 'can_reply_reviews' => true,
        'stats_level' => 'advanced', 'is_active' => true,
    ]);

    expect($agency->vehicles()->where('status', Vehicle::STATUS_ARCHIVED)->count())->toBe(2);
    Notification::assertSentTo($agency->user, SubscriptionDowngraded::class);
});

it('accepte une formule sans limite d’annonces', function () {
    $platinium = Plan::where('slug', Plan::PLATINIUM)->firstOrFail();

    actingAs($this->admin)->put(route('admin.plans.update', $platinium), [
        'name' => 'Platinium', 'price_dzd' => 90000,
        'max_listings' => null, 'max_photos' => 10, 'max_users' => 10,
        'has_commune_priority' => true, 'has_wilaya_priority' => true,
        'has_homepage_feature' => true, 'can_reply_reviews' => true,
        'stats_level' => 'premium', 'is_active' => true,
    ])->assertSessionHasNoErrors();

    // NULL vaut illimité, jamais un grand nombre.
    expect($platinium->fresh()->max_listings)->toBeNull();
});

/* --- Demandes de changement ---------------------------------------------- */

it('laisse une agence demander une formule, une seule à la fois', function () {
    Notification::fake();
    $agency = agencyOn(Plan::SILVER);
    $gold = Plan::where('slug', Plan::GOLD)->firstOrFail();

    actingAs($agency->user)->post(route('agency.subscription.request'), [
        'requested_plan_id' => $gold->id,
        'agency_message' => 'Nous voulons publier plus de véhicules.',
    ])->assertSessionHasNoErrors();

    expect(PlanChangeRequest::count())->toBe(1);

    // En empiler trois ne ferait pas répondre l'administrateur plus vite.
    actingAs($agency->user)->post(route('agency.subscription.request'), [
        'requested_plan_id' => $gold->id,
    ])->assertSessionHasErrors('requested_plan_id');

    expect(PlanChangeRequest::count())->toBe(1);
});

it('attribue la formule demandée et clôt la demande', function () {
    Notification::fake();
    $agency = agencyOn(Plan::SILVER);
    $gold = Plan::where('slug', Plan::GOLD)->firstOrFail();

    actingAs($agency->user)->post(route('agency.subscription.request'), ['requested_plan_id' => $gold->id]);
    $request = PlanChangeRequest::first();

    actingAs($this->admin)->post(route('admin.plans.grant', $agency), [
        'plan_id' => $gold->id,
        'ends_at' => now()->addYear()->toDateString(),
        'note' => 'Virement reçu.',
        'request_id' => $request->id,
    ])->assertSessionHasNoErrors();

    expect($agency->fresh()->currentPlan()->slug)->toBe(Plan::GOLD)
        ->and($request->fresh()->status)->toBe(PlanChangeRequest::STATUS_ACCEPTED);

    Notification::assertSentTo($agency->user, PlanChangeHandled::class);
});

it('attribue une formule sans qu’aucune demande n’ait été faite', function () {
    Notification::fake();
    $agency = agencyOn(Plan::SILVER);
    $platinium = Plan::where('slug', Plan::PLATINIUM)->firstOrFail();

    // L'administrateur n'a pas a attendre qu'une agence demande : c'est lui
    // qui constate l'encaissement, hors plateforme (§4.3).
    expect(PlanChangeRequest::count())->toBe(0);

    actingAs($this->admin)->post(route('admin.plans.grant', $agency), [
        'plan_id' => $platinium->id,
        'ends_at' => now()->addYear()->toDateString(),
        'note' => 'Versement recu au comptoir.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($agency->fresh()->currentPlan()->slug)->toBe(Plan::PLATINIUM)
        ->and($agency->fresh()->activeSubscription->admin_note)->toContain('Versement');
});

it('attribue une formule sans échéance quand le champ est vide', function () {
    Notification::fake();
    $agency = agencyOn(Plan::SILVER);
    $gold = Plan::where('slug', Plan::GOLD)->firstOrFail();

    actingAs($this->admin)->post(route('admin.plans.grant', $agency), [
        'plan_id' => $gold->id,
        'ends_at' => '',
    ])->assertSessionHasNoErrors();

    // Sans echeance, la retrogradation automatique ne s'applique jamais.
    expect($agency->fresh()->activeSubscription->ends_at)->toBeNull();
});

it('propose la matrice sur la fiche agence de l’administrateur', function () {
    $agency = agencyOn(Plan::GOLD);

    actingAs($this->admin)
        ->get(route('admin.agencies.show', $agency))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('plans', 3)
            ->where('agency.plan.slug', Plan::GOLD));
});

it('refuse une échéance déjà passée', function () {
    $agency = agencyOn(Plan::SILVER);
    $gold = Plan::where('slug', Plan::GOLD)->firstOrFail();

    // Elle rétrograderait l'agence dès la nuit suivante.
    actingAs($this->admin)->post(route('admin.plans.grant', $agency), [
        'plan_id' => $gold->id,
        'ends_at' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors('ends_at');
});

it('exige un motif pour refuser une demande', function () {
    Notification::fake();
    $agency = agencyOn(Plan::SILVER);
    $gold = Plan::where('slug', Plan::GOLD)->firstOrFail();

    actingAs($agency->user)->post(route('agency.subscription.request'), ['requested_plan_id' => $gold->id]);
    $request = PlanChangeRequest::first();

    actingAs($this->admin)
        ->post(route('admin.plans.requests.refuse', $request), [])
        ->assertSessionHasErrors('admin_response');

    expect($request->fresh()->status)->toBe(PlanChangeRequest::STATUS_PENDING);
});

it('montre à l’agence sa formule et le comparatif, lu depuis la base', function () {
    $agency = agencyOn(Plan::GOLD);

    actingAs($agency->user)
        ->get(route('agency.subscription'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Agency/Subscription')
            ->where('subscription.plan.slug', Plan::GOLD)
            ->has('plans', 3)
            ->where('usage.max_listings', Plan::where('slug', Plan::GOLD)->value('max_listings')));
});

it('interdit à une agence suspendue de demander un changement', function () {
    $agency = agencyOn(Plan::SILVER);
    $agency->update(['status' => Agency::STATUS_SUSPENDED]);

    actingAs($agency->user)->get(route('agency.subscription'))->assertOk();
    actingAs($agency->user)
        ->post(route('agency.subscription.request'), ['requested_plan_id' => Plan::where('slug', Plan::GOLD)->value('id')])
        ->assertRedirect(route('agency.pending'));
});
