<?php

use App\Models\Agency;
use App\Models\Plan;
use App\Models\Vehicle;
use App\Models\Wilaya;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);
});

/** A published listing, visible to the public. */
function publishedVehicle(Agency $agency, array $overrides = []): Vehicle
{
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PUBLISHED);
    $vehicle->update(['published_at' => now()] + $overrides);

    return $vehicle->fresh();
}

it('affiche les annonces publiées sur la page d’accueil', function () {
    $agency = agencyOn(Plan::GOLD);
    $vehicle = publishedVehicle($agency);

    get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('stats.vehicles', 1)
            ->has('latest', 1)
            ->where('latest.0.id', $vehicle->id));
});

it('n’expose ni brouillon, ni annonce en modération, ni annonce refusée', function () {
    $agency = agencyOn(Plan::GOLD);
    readyVehicle($agency, Vehicle::STATUS_DRAFT);
    readyVehicle($agency, Vehicle::STATUS_PENDING);
    readyVehicle($agency, Vehicle::STATUS_REJECTED);
    readyVehicle($agency, Vehicle::STATUS_ARCHIVED);

    get(route('home'))->assertInertia(fn ($page) => $page->where('stats.vehicles', 0)->has('latest', 0));
    get(route('search'))->assertInertia(fn ($page) => $page->where('vehicles.total', 0));
});

it('retire du catalogue les annonces d’une agence suspendue', function () {
    $agency = agencyOn(Plan::GOLD);
    publishedVehicle($agency);

    get(route('search'))->assertInertia(fn ($page) => $page->where('vehicles.total', 1));

    // Suspendre masque les annonces sans y toucher : elles reviennent telles
    // quelles a la reactivation.
    $agency->update(['status' => Agency::STATUS_SUSPENDED]);

    get(route('search'))->assertInertia(fn ($page) => $page->where('vehicles.total', 0));
});

it('répond 404 sur la fiche d’une annonce non publiée', function () {
    $agency = agencyOn(Plan::GOLD);
    $draft = readyVehicle($agency, Vehicle::STATUS_DRAFT);

    // Detenir l'URL ne doit pas suffire : une annonce retiree cesse de repondre.
    get(route('vehicle.show', ['vehicle' => $draft->id, 'slug' => $draft->slug]))->assertNotFound();
});

it('affiche la fiche véhicule avec ses tarifs et son agence', function () {
    $agency = agencyOn(Plan::GOLD);
    $vehicle = publishedVehicle($agency);

    get(route('vehicle.show', ['vehicle' => $vehicle->id, 'slug' => $vehicle->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Vehicle')
            ->where('vehicle.id', $vehicle->id)
            ->has('vehicle.pricing')
            ->where('vehicle.agency.commercial_name', $agency->commercial_name));
});

it('compte une vue à chaque consultation de la fiche', function () {
    $agency = agencyOn(Plan::GOLD);
    $vehicle = publishedVehicle($agency);

    get(route('vehicle.show', ['vehicle' => $vehicle->id, 'slug' => $vehicle->slug]));

    expect($vehicle->fresh()->views_count)->toBe(1);
});

it('filtre la recherche par wilaya dans l’URL', function () {
    $alger = agencyOn(Plan::GOLD, email: 'alger@test.dz');
    publishedVehicle($alger);

    $wilaya = Wilaya::where('code', '16')->firstOrFail();
    $other = Wilaya::where('code', '31')->firstOrFail();

    get(route('search.wilaya', ['wilaya' => $wilaya->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('vehicles.total', 1));

    get(route('search.wilaya', ['wilaya' => $other->slug]))
        ->assertInertia(fn ($page) => $page->where('vehicles.total', 0));
});

it('refuse une commune qui n’appartient pas à la wilaya de l’URL', function () {
    $wilaya = Wilaya::where('code', '16')->firstOrFail();
    $foreign = Wilaya::where('code', '31')->firstOrFail()->communes()->firstOrFail();

    get(route('search.commune', ['wilaya' => $wilaya->slug, 'commune' => $foreign->slug]))
        ->assertNotFound();
});

it('filtre par catégorie et par carburant', function () {
    $agency = agencyOn(Plan::GOLD);
    $citadine = publishedVehicle($agency, ['category' => 'citadine', 'fuel' => 'essence']);
    publishedVehicle($agency, ['category' => 'suv', 'fuel' => 'diesel']);

    get(route('search', ['category' => 'citadine']))
        ->assertInertia(fn ($page) => $page
            ->where('vehicles.total', 1)
            ->where('vehicles.data.0.id', $citadine->id));

    get(route('search', ['fuel' => 'diesel']))
        ->assertInertia(fn ($page) => $page->where('vehicles.total', 1));
});

it('fait remonter le Platinium en premier sur sa wilaya, et l’annonce sur elle-même', function () {
    $silver = agencyOn(Plan::SILVER, email: 'silver@test.dz');
    $platinium = agencyOn(Plan::PLATINIUM, email: 'platinium@test.dz');

    // Le Silver publie en premier : sans les priorites de formule, il serait
    // devant. C'est exactement ce que la formule achete.
    publishedVehicle($silver);
    $featured = publishedVehicle($platinium);

    $wilaya = Wilaya::where('code', '16')->firstOrFail();

    get(route('search.wilaya', ['wilaya' => $wilaya->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('vehicles.data.0.id', $featured->id)
            // Mention obligatoire (§4.2) : une annonce remontee par sa formule
            // doit le dire.
            ->where('vehicles.data.0.sponsored', true)
            ->where('vehicles.data.1.sponsored', false));
});

it('ignore les priorités de formule quand le visiteur trie lui-même', function () {
    $silver = agencyOn(Plan::SILVER, email: 'silver@test.dz');
    $platinium = agencyOn(Plan::PLATINIUM, email: 'platinium@test.dz');

    $cheap = publishedVehicle($silver);
    $cheap->pricingRules()->where('duration_type', 'daily')->update(['price_dzd' => 2000]);

    $expensive = publishedVehicle($platinium);
    $expensive->pricingRules()->where('duration_type', 'daily')->update(['price_dzd' => 20000]);

    $wilaya = Wilaya::where('code', '16')->firstOrFail();

    // Le choix explicite du visiteur passe avant le classement commercial.
    get(route('search.wilaya', ['wilaya' => $wilaya->slug, 'sort' => 'price_asc']))
        ->assertInertia(fn ($page) => $page->where('vehicles.data.0.id', $cheap->id));
});

it('ne met en avant sur l’accueil que les formules qui paient cet emplacement', function () {
    $silver = agencyOn(Plan::SILVER, email: 'silver@test.dz');
    $platinium = agencyOn(Plan::PLATINIUM, email: 'platinium@test.dz');

    publishedVehicle($silver);
    $featured = publishedVehicle($platinium);

    get(route('home'))->assertInertia(fn ($page) => $page
        ->has('featured', 1)
        ->where('featured.0.id', $featured->id));
});
