<?php

use App\Models\Agency;
use App\Models\Plan;
use App\Models\Vehicle;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->agency = agencyOn(Plan::GOLD);
    $this->vehicle = readyVehicle($this->agency, Vehicle::STATUS_PUBLISHED);
    $this->vehicle->update(['published_at' => now()]);
});

/* --- Fiche agence --------------------------------------------------------- */

it('affiche la fiche publique d’une agence et ses véhicules', function () {
    get(route('agency.public', $this->agency->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Agency')
            ->where('agency.commercial_name', $this->agency->commercial_name)
            ->where('agency.vehicles_count', 1)
            ->has('vehicles.data', 1));
});

it('cache la fiche d’une agence suspendue ou en attente', function () {
    foreach ([Agency::STATUS_SUSPENDED, Agency::STATUS_PENDING, Agency::STATUS_REJECTED] as $status) {
        $this->agency->update(['status' => $status]);

        // Ses annonces sont masquees : une page qui les annonce encore
        // contredirait la suspension.
        get(route('agency.public', $this->agency->slug))->assertNotFound();
    }
});

it('publie les données structurées de l’agence pour les moteurs', function () {
    $this->agency->update(['latitude' => 36.7538, 'longitude' => 3.0588]);

    get(route('agency.public', $this->agency->slug))
        ->assertInertia(fn ($page) => $page
            ->where('structuredData.@type', 'AutoRental')
            ->where('structuredData.address.addressCountry', 'DZ')
            ->where('structuredData.geo.latitude', 36.7538)
            // Une note agregee sans avis serait une etoile inventee.
            ->missing('structuredData.aggregateRating'));
});

it('publie les données structurées du véhicule', function () {
    get(route('vehicle.show', ['vehicle' => $this->vehicle->id, 'slug' => $this->vehicle->slug]))
        ->assertInertia(fn ($page) => $page
            ->where('structuredData.@type', 'Car')
            ->where('structuredData.offers.priceCurrency', 'DZD')
            ->where('structuredData.offers.seller.@type', 'AutoRental'));
});

/* --- Pages statiques et sitemap ------------------------------------------- */

it('sert les cinq pages statiques', function () {
    foreach (['about', 'terms', 'privacy', 'faq', 'contact'] as $page) {
        get(route("pages.{$page}"))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Public/Page')->has('sections'));
    }
});

it('génère un sitemap qui ne liste que des pages qui répondent', function () {
    $response = get(route('sitemap'))->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/xml');

    $xml = $response->getContent();

    expect($xml)->toContain(route('home'))
        ->toContain(route('agency.public', $this->agency->slug))
        ->toContain(route('vehicle.show', ['vehicle' => $this->vehicle->id, 'slug' => $this->vehicle->slug]))
        ->toContain(route('pages.terms'));

    // Un sitemap qui pointe vers des pages vides apprend au robot a se mefier
    // du fichier entier : les wilayas sans annonce sont exclues.
    expect($xml)->not->toContain(route('search.wilaya', 'tindouf'));

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('retire du sitemap une annonce dépubliée', function () {
    $url = route('vehicle.show', ['vehicle' => $this->vehicle->id, 'slug' => $this->vehicle->slug]);

    $this->vehicle->update(['status' => Vehicle::STATUS_ARCHIVED]);
    cache()->forget('sitemap.xml');

    expect(get(route('sitemap'))->getContent())->not->toContain($url);
});

/* --- Paramètres de l'agence ----------------------------------------------- */

function settingsPayload(Agency $agency, array $overrides = []): array
{
    return array_merge([
        'commercial_name' => $agency->commercial_name,
        'manager_name' => $agency->manager_name,
        'wilaya_id' => $agency->wilaya_id,
        'commune_id' => $agency->commune_id,
        'address' => '12 rue Didouche Mourad',
        'phone' => '0555112233',
        'min_driver_age' => 23,
        'default_deposit_dzd' => 50000,
        'buffer_hours' => 6,
    ], $overrides);
}

it('laisse l’agence remplir ses conditions, sa caution et ses horaires', function () {
    actingAs($this->agency->user)
        ->post(route('agency.settings.update'), settingsPayload($this->agency, [
            'rental_conditions' => 'Permis de plus de deux ans exigé.',
            'opening_hours' => [
                'sunday' => ['closed' => false, 'from' => '08:00', 'to' => '17:00'],
                'friday' => ['closed' => true, 'from' => '08:00', 'to' => '17:00'],
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $agency = $this->agency->fresh();

    expect($agency->rental_conditions)->toContain('deux ans')
        ->and($agency->min_driver_age)->toBe(23)
        ->and($agency->default_deposit_dzd)->toBe(50000)
        ->and($agency->buffer_hours)->toBe(6)
        ->and($agency->opening_hours['friday']['closed'])->toBeTrue();
});

it('fait suivre la caution et l’âge minimum jusqu’au tunnel de réservation', function () {
    actingAs($this->agency->user)->post(route('agency.settings.update'),
        settingsPayload($this->agency, ['default_deposit_dzd' => 75000, 'min_driver_age' => 25]));

    // C'est le point de tout cet ecran : ce que l'agence saisit ici est ce que
    // le client lit avant de s'engager.
    get(route('bookings.create', $this->vehicle))
        ->assertInertia(fn ($page) => $page
            ->where('vehicle.agency.deposit_dzd', 75000)
            ->where('vehicle.agency.min_driver_age', 25));
});

it('refuse une position hors d’Algérie', function () {
    actingAs($this->agency->user)
        ->post(route('agency.settings.update'), settingsPayload($this->agency, [
            'latitude' => 48.8566,   // Paris
            'longitude' => 2.3522,
        ]))
        ->assertSessionHasErrors('latitude');
});

it('refuse une commune qui n’appartient pas à la wilaya choisie', function () {
    $ailleurs = App\Models\Commune::where('wilaya_id', '!=', $this->agency->wilaya_id)->firstOrFail();

    actingAs($this->agency->user)
        ->post(route('agency.settings.update'), settingsPayload($this->agency, ['commune_id' => $ailleurs->id]))
        ->assertSessionHasErrors('commune_id');
});

it('n’affiche la carte que si l’agence a posé son point', function () {
    get(route('agency.public', $this->agency->slug))
        ->assertInertia(fn ($page) => $page->where('agency.latitude', null));

    $this->agency->update(['latitude' => 36.7538, 'longitude' => 3.0588]);

    get(route('agency.public', $this->agency->slug))
        ->assertInertia(fn ($page) => $page->where('agency.latitude', 36.7538));
});

it('interdit à une agence suspendue de modifier ses paramètres', function () {
    $this->agency->update(['status' => Agency::STATUS_SUSPENDED]);

    actingAs($this->agency->user)->get(route('agency.settings'))->assertOk();
    actingAs($this->agency->user)
        ->post(route('agency.settings.update'), settingsPayload($this->agency))
        ->assertRedirect(route('agency.pending'));
});
