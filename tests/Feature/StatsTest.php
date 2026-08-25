<?php

use App\Models\ListingStat;
use App\Models\Plan;
use App\Models\Vehicle;
use App\Services\StatsService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->agency = agencyOn(Plan::GOLD);
    $this->vehicle = readyVehicle($this->agency, Vehicle::STATUS_PUBLISHED);
    $this->vehicle->update(['published_at' => now()]);

    $this->stats = app(StatsService::class);
});

/* --- Compteurs ------------------------------------------------------------ */

it('compte une vue par jour et par annonce', function () {
    get(route('vehicle.show', ['vehicle' => $this->vehicle->id, 'slug' => $this->vehicle->slug]));
    get(route('vehicle.show', ['vehicle' => $this->vehicle->id, 'slug' => $this->vehicle->slug]));

    $stat = ListingStat::where('vehicle_id', $this->vehicle->id)->first();

    // Une ligne par annonce et par jour : l'agence veut savoir qu'une voiture a
    // ete vue quarante fois mardi, pas qui l'a vue.
    expect(ListingStat::count())->toBe(1)
        ->and($stat->views)->toBe(2)
        ->and($stat->agency_id)->toBe($this->agency->id);
});

it('compte un clic sur le téléphone ou WhatsApp', function () {
    post(route('vehicles.contact', $this->vehicle))->assertNoContent();

    expect(ListingStat::where('vehicle_id', $this->vehicle->id)->value('contact_clicks'))->toBe(1);
});

it('ne compte rien pour une annonce qui n’est pas publiée', function () {
    $this->vehicle->update(['status' => Vehicle::STATUS_ARCHIVED]);

    post(route('vehicles.contact', $this->vehicle))->assertNoContent();

    expect(ListingStat::count())->toBe(0);
});

it('compte une demande de réservation', function () {
    post(route('bookings.store', $this->vehicle), [
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'client_name' => 'Yacine Haddad',
        'client_phone' => '0555112233',
        'client_email' => 'yacine@test.dz',
        'accepts_conditions' => true,
    ]);

    expect(ListingStat::where('vehicle_id', $this->vehicle->id)->value('bookings_count'))->toBe(1);
});

/* --- Agrégats ------------------------------------------------------------- */

it('remplit les jours sans activité plutôt que de les sauter', function () {
    ListingStat::create([
        'vehicle_id' => $this->vehicle->id,
        'agency_id' => $this->agency->id,
        'date' => now()->subDays(3)->toDateString(),
        'views' => 12,
    ]);

    $data = $this->stats->forAgency($this->agency, 30);

    // Une courbe qui saute les jours creux flatterait l'agence en les cachant.
    expect($data['daily'])->toHaveCount(30)
        ->and(collect($data['daily'])->firstWhere('date', now()->subDays(3)->toDateString())['views'])->toBe(12)
        ->and(collect($data['daily'])->firstWhere('date', now()->subDays(2)->toDateString())['views'])->toBe(0);
});

it('calcule le taux de conversion vue vers demande', function () {
    ListingStat::create([
        'vehicle_id' => $this->vehicle->id,
        'agency_id' => $this->agency->id,
        'date' => now()->toDateString(),
        'views' => 200,
    ]);

    post(route('bookings.store', $this->vehicle), [
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'client_name' => 'Yacine', 'client_phone' => '0555112233',
        'client_email' => 'y@test.dz', 'accepts_conditions' => true,
    ]);

    $data = $this->stats->forAgency($this->agency, 30);

    expect($data['conversion']['view_to_booking'])->toBe(0.5);
});

it('classe les véhicules les plus vus', function () {
    $autre = readyVehicle($this->agency, Vehicle::STATUS_PUBLISHED);

    ListingStat::create(['vehicle_id' => $this->vehicle->id, 'agency_id' => $this->agency->id,
        'date' => now()->toDateString(), 'views' => 5]);
    ListingStat::create(['vehicle_id' => $autre->id, 'agency_id' => $this->agency->id,
        'date' => now()->toDateString(), 'views' => 40]);

    $top = $this->stats->forAgency($this->agency, 30)['topVehicles'];

    expect($top->first()['id'])->toBe($autre->id)
        ->and($top->first()['views'])->toBe(40);
});

/* --- Niveau autorisé par la formule (§4.1) -------------------------------- */

it('donne le niveau de statistiques de la formule', function () {
    foreach ([Plan::SILVER => 'basic', Plan::GOLD => 'advanced', Plan::PLATINIUM => 'premium'] as $slug => $level) {
        $agency = agencyOn($slug, email: "stats-{$slug}@test.dz");

        expect($this->stats->forAgency($agency)['level'])->toBe($level);
    }
});

it('ne calcule la comparaison wilaya que pour Platinium', function () {
    $gold = $this->stats->forAgency($this->agency);
    expect($gold['wilayaComparison'])->toBeNull();

    $platinium = agencyOn(Plan::PLATINIUM, email: 'platinium@test.dz');
    expect($this->stats->forAgency($platinium)['wilayaComparison'])->toBeArray();
});

it('calcule quand même les blocs verrouillés, pour ne pas flouter du vide', function () {
    $silver = agencyOn(Plan::SILVER, email: 'silver@test.dz');
    $vehicle = readyVehicle($silver, Vehicle::STATUS_PUBLISHED);
    ListingStat::create(['vehicle_id' => $vehicle->id, 'agency_id' => $silver->id,
        'date' => now()->toDateString(), 'views' => 100, 'contact_clicks' => 10]);

    $data = $this->stats->forAgency($silver);

    // La page floute et propose la montee en gamme : des chiffres vides
    // derriere le flou vendraient la fonction avec un mensonge.
    expect($data['level'])->toBe('basic')
        ->and($data['conversion']['view_to_contact'])->toBe(10.0)
        ->and($data['weekdays'])->toHaveCount(7);
});

it('affiche l’écran des statistiques à l’agence', function () {
    actingAs($this->agency->user)
        ->get(route('agency.stats'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Agency/Stats')
            ->where('plan.stats_level', 'advanced')
            ->has('stats.daily', 30)
            ->has('stats.totals'));
});

it('accepte seulement les périodes prévues', function () {
    // Une periode arbitraire ferait varier les couts de requete sans raison.
    actingAs($this->agency->user)
        ->get(route('agency.stats', ['days' => 9999]))
        ->assertInertia(fn ($page) => $page->where('days', 30));

    actingAs($this->agency->user)
        ->get(route('agency.stats', ['days' => 7]))
        ->assertInertia(fn ($page) => $page->where('days', 7)->has('stats.daily', 7));
});

it('cache les statistiques d’une agence aux autres', function () {
    $autre = agencyOn(Plan::GOLD, email: 'autre@test.dz');
    ListingStat::create(['vehicle_id' => $this->vehicle->id, 'agency_id' => $this->agency->id,
        'date' => now()->toDateString(), 'views' => 500]);

    actingAs($autre->user)
        ->get(route('agency.stats'))
        ->assertInertia(fn ($page) => $page->where('stats.totals.views', 0));
});
