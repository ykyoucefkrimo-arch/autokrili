<?php

use App\Models\Agency;
use App\Models\Commune;
use App\Models\Plan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Wilaya;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);
});

it('crée une annonce en brouillon avec ses tarifs dégressifs', function () {
    $agency = agencyOn();

    actingAs($agency->user)
        ->post(route('agency.vehicles.store'), vehiclePayload($agency))
        ->assertRedirect();

    $vehicle = Vehicle::first();

    // Toujours brouillon d'abord : une annonce à moitié écrite n'a rien à
    // faire dans la file de modération.
    expect($vehicle->status)->toBe(Vehicle::STATUS_DRAFT)
        ->and($vehicle->pricingRules)->toHaveCount(3)
        ->and($vehicle->pricingRules->firstWhere('duration_type', 'weekly')->price_dzd)->toBe(27000);
});

it('refuse un tarif hebdomadaire qui n’offre aucune remise', function () {
    $agency = agencyOn();

    actingAs($agency->user)
        ->post(route('agency.vehicles.store'), vehiclePayload($agency, [
            'pricing' => ['daily' => 4000, 'weekly' => 40000, 'monthly' => null],
        ]))
        ->assertSessionHasErrors('pricing.weekly');

    expect(Vehicle::count())->toBe(0);
});

it('refuse une commune qui n’appartient pas à la wilaya de retrait', function () {
    $agency = agencyOn();
    $other = Commune::where('wilaya_id', '!=', $agency->wilaya_id)->firstOrFail();

    actingAs($agency->user)
        ->post(route('agency.vehicles.store'), vehiclePayload($agency, ['pickup_commune_id' => $other->id]))
        ->assertSessionHasErrors('pickup_commune_id');
});

it('bloque la création au-delà du quota d’annonces de la formule', function () {
    $agency = agencyOn(Plan::SILVER);
    $max = $agency->currentPlan()->max_listings;

    for ($i = 0; $i < $max; $i++) {
        readyVehicle($agency);
    }

    actingAs($agency->user)
        ->post(route('agency.vehicles.store'), vehiclePayload($agency))
        ->assertSessionHasErrors('quota');

    expect($agency->vehicles()->count())->toBe($max);
});

it('libère une place du quota quand une annonce est archivée', function () {
    $agency = agencyOn(Plan::SILVER);
    $max = $agency->currentPlan()->max_listings;

    $vehicles = collect(range(1, $max))->map(fn () => readyVehicle($agency));

    actingAs($agency->user)
        ->post(route('agency.vehicles.archive', $vehicles->first()->id))
        ->assertRedirect();

    actingAs($agency->user)
        ->post(route('agency.vehicles.store'), vehiclePayload($agency))
        ->assertSessionHasNoErrors();
});

it('laisse une formule illimitee publier sans plafond ni proposition de montee en gamme', function () {
    $agency = agencyOn(Plan::PLATINIUM);

    // Platinium porte max_listings a NULL : la page doit s'afficher, le quota
    // ne doit jamais bloquer, et rien ne doit etre propose au-dessus.
    actingAs($agency->user)->get(route('agency.vehicles.index'))->assertOk();
    actingAs($agency->user)->get(route('agency.vehicles.create'))->assertOk();

    actingAs($agency->user)
        ->post(route('agency.vehicles.store'), vehiclePayload($agency))
        ->assertSessionHasNoErrors();

    expect(app(App\Services\ListingQuotaService::class)->listingLimitMessage($agency))
        ->not->toContain('Passez en');
});

it('refuse de soumettre une annonce sans photo', function () {
    $agency = agencyOn();
    $vehicle = readyVehicle($agency);
    $vehicle->photos()->delete();

    actingAs($agency->user)
        ->post(route('agency.vehicles.submit', $vehicle->id))
        ->assertSessionHasErrors('submit');

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_DRAFT);
});

it('refuse de soumettre une annonce sans tarif journalier', function () {
    $agency = agencyOn();
    $vehicle = readyVehicle($agency);
    $vehicle->pricingRules()->delete();

    actingAs($agency->user)
        ->post(route('agency.vehicles.submit', $vehicle->id))
        ->assertSessionHasErrors('submit');
});

it('envoie l’annonce en modération à la soumission', function () {
    $agency = agencyOn();
    $vehicle = readyVehicle($agency);

    actingAs($agency->user)->post(route('agency.vehicles.submit', $vehicle->id));

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_PENDING);
});

it('publie immédiatement l’annonce d’une agence de confiance', function () {
    $agency = agencyOn(Plan::SILVER, trusted: true);
    $vehicle = readyVehicle($agency);

    actingAs($agency->user)->post(route('agency.vehicles.submit', $vehicle->id));

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_PUBLISHED)
        ->and($vehicle->fresh()->published_at)->not->toBeNull();
});

it('repasse une annonce publiée en modération après modification', function () {
    $agency = agencyOn();
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PUBLISHED);

    actingAs($agency->user)
        ->put(route('agency.vehicles.update', $vehicle->id), vehiclePayload($agency, ['model' => 'Clio 4']));

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_PENDING);
});

it('laisse en ligne l’annonce modifiée d’une agence de confiance', function () {
    $agency = agencyOn(Plan::SILVER, trusted: true);
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PUBLISHED);

    actingAs($agency->user)
        ->put(route('agency.vehicles.update', $vehicle->id), vehiclePayload($agency, ['model' => 'Clio 4']));

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_PUBLISHED);
});

it('interdit à une agence de toucher à l’annonce d’une concurrente', function () {
    $mine = agencyOn(Plan::SILVER, email: 'moi@test.dz');
    $theirs = agencyOn(Plan::SILVER, email: 'eux@test.dz');
    $vehicle = readyVehicle($theirs);

    actingAs($mine->user)->get(route('agency.vehicles.edit', $vehicle->id))->assertForbidden();
    actingAs($mine->user)->post(route('agency.vehicles.submit', $vehicle->id))->assertForbidden();
    actingAs($mine->user)->delete(route('agency.vehicles.destroy', $vehicle->id))->assertForbidden();

    expect($vehicle->fresh())->not->toBeNull();
});

it('laisse une agence suspendue consulter ses annonces sans les modifier', function () {
    $agency = agencyOn();
    $vehicle = readyVehicle($agency);
    $agency->update(['status' => Agency::STATUS_SUSPENDED]);

    actingAs($agency->user)->get(route('agency.vehicles.index'))->assertOk();
    // Le middleware renvoie vers l'écran d'attente : aucune écriture ne passe.
    actingAs($agency->user)
        ->post(route('agency.vehicles.submit', $vehicle->id))
        ->assertRedirect(route('agency.pending'));

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_DRAFT);
});

/* --- Photos ------------------------------------------------------------- */

it('plafonne les photos au quota de la formule', function () {
    Storage::fake('public');
    $agency = agencyOn(Plan::SILVER);  // une seule photo autorisée
    $vehicle = readyVehicle($agency);
    $vehicle->photos()->delete();

    actingAs($agency->user)->post(route('agency.vehicles.photos.store', $vehicle->id), [
        'photos' => [UploadedFile::fake()->image('a.jpg', 1200, 900)],
    ])->assertSessionHasNoErrors();

    actingAs($agency->user)->post(route('agency.vehicles.photos.store', $vehicle->id), [
        'photos' => [UploadedFile::fake()->image('b.jpg', 1200, 900)],
    ])->assertSessionHasErrors('photos');

    expect($vehicle->photos()->count())->toBe(1);
});

it('refuse un lot entier plutôt que d’en garder une partie', function () {
    Storage::fake('public');
    $agency = agencyOn(Plan::GOLD);  // cinq photos autorisées
    $vehicle = readyVehicle($agency);
    $vehicle->photos()->delete();

    actingAs($agency->user)->post(route('agency.vehicles.photos.store', $vehicle->id), [
        'photos' => collect(range(1, 6))
            ->map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg", 800, 600))
            ->all(),
    ])->assertSessionHasErrors('photos');

    expect($vehicle->photos()->count())->toBe(0);
});

it('écrit trois dérivés et désigne la première photo comme couverture', function () {
    Storage::fake('public');
    $agency = agencyOn(Plan::GOLD);
    $vehicle = readyVehicle($agency);
    $vehicle->photos()->delete();

    actingAs($agency->user)->post(route('agency.vehicles.photos.store', $vehicle->id), [
        'photos' => [UploadedFile::fake()->image('a.jpg', 2000, 1500)],
    ]);

    $photo = $vehicle->photos()->first();

    expect($photo->is_cover)->toBeTrue();
    Storage::disk('public')->assertExists($photo->path);
    Storage::disk('public')->assertExists($photo->path_card);
    Storage::disk('public')->assertExists($photo->path_thumb);
});

it('transmet la couverture à la photo suivante quand elle est supprimée', function () {
    Storage::fake('public');
    $agency = agencyOn(Plan::GOLD);
    $vehicle = readyVehicle($agency);
    $vehicle->photos()->delete();

    actingAs($agency->user)->post(route('agency.vehicles.photos.store', $vehicle->id), [
        'photos' => [
            UploadedFile::fake()->image('a.jpg', 800, 600),
            UploadedFile::fake()->image('b.jpg', 800, 600),
        ],
    ]);

    $cover = $vehicle->photos()->where('is_cover', true)->firstOrFail();

    actingAs($agency->user)
        ->delete(route('agency.vehicles.photos.destroy', [$vehicle->id, $cover->id]));

    expect($vehicle->photos()->count())->toBe(1)
        ->and($vehicle->photos()->first()->is_cover)->toBeTrue();
});

it('supprime les fichiers d’une annonce supprimée', function () {
    Storage::fake('public');
    $agency = agencyOn(Plan::GOLD);
    $vehicle = readyVehicle($agency);
    $vehicle->photos()->delete();

    actingAs($agency->user)->post(route('agency.vehicles.photos.store', $vehicle->id), [
        'photos' => [UploadedFile::fake()->image('a.jpg', 800, 600)],
    ]);
    $path = $vehicle->photos()->first()->path;

    actingAs($agency->user)->delete(route('agency.vehicles.destroy', $vehicle->id));

    Storage::disk('public')->assertMissing($path);
    // Suppression douce : la ligne survit pour les reservations qui la citent,
    // mais l'annonce disparait de toutes les requetes ordinaires.
    expect(Vehicle::find($vehicle->id))->toBeNull()
        ->and($vehicle->photos()->count())->toBe(0);
});
