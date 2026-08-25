<?php

use App\Models\Agency;
use App\Models\Commune;
use App\Models\User;
use App\Models\Wilaya;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(Database\Seeders\RoleSeeder::class);
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->wilaya = Wilaya::where('code', '16')->firstOrFail();
    $this->commune = Commune::where('wilaya_id', $this->wilaya->id)->firstOrFail();
});

function validAgencyPayload(Wilaya $wilaya, Commune $commune): array
{
    return [
        'commercial_name' => 'Alger Location Test',
        'manager_name' => 'Karim Belhadi',
        'trade_register_number' => '16/00-1234567',
        'nif' => '000916001234567',
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'address' => '12 rue Didouche Mourad',
        'phone' => '0555123456',
        'email' => 'contact@alger-location.dz',
        'password' => 'motdepasse-solide-1',
        'password_confirmation' => 'motdepasse-solide-1',
        'trade_register_file' => UploadedFile::fake()->create('registre.pdf', 200, 'application/pdf'),
    ];
}

it('affiche le formulaire avec les wilayas, sans embarquer toutes les communes', function () {
    get(route('agency.register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/RegisterAgency')
            ->has('wilayas', 58)
            // Le slug est la cle de la route qui sert les communes.
            ->has('wilayas.0.slug')
            // Les 1541 communes du pays se chargent a la wilaya choisie : les
            // livrer toutes couterait ~90 Ko a chaque ouverture du formulaire.
            ->missing('wilayas.0.communes'));
});

it('cree un compte agence en attente de moderation', function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();

    post(route('agency.register'), validAgencyPayload($this->wilaya, $this->commune))
        ->assertRedirect(route('agency.pending'));

    $agency = Agency::firstOrFail();

    expect($agency->status)->toBe(Agency::STATUS_PENDING)
        ->and($agency->user->role)->toBe(User::ROLE_AGENCY)
        ->and($agency->user->hasRole(User::ROLE_AGENCY))->toBeTrue()
        // Aucun abonnement tant que la moderation n'a pas tranche.
        ->and($agency->subscriptions()->count())->toBe(0);
});

it('range le registre de commerce hors du disque public', function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();

    post(route('agency.register'), validAgencyPayload($this->wilaya, $this->commune));

    $agency = Agency::firstOrFail();

    // C'est un document d'identite : il ne doit exister que sur le disque prive.
    Storage::disk('local')->assertExists($agency->trade_register_file);
    Storage::disk('public')->assertMissing($agency->trade_register_file);
});

it('refuse une inscription sans registre de commerce', function () {
    $payload = validAgencyPayload($this->wilaya, $this->commune);
    unset($payload['trade_register_file']);

    post(route('agency.register'), $payload)
        ->assertSessionHasErrors('trade_register_file');

    expect(Agency::count())->toBe(0);
});

it('refuse une commune qui n appartient pas a la wilaya choisie', function () {
    $autreWilaya = Wilaya::where('code', '31')->firstOrFail();
    $communeAilleurs = Commune::where('wilaya_id', $autreWilaya->id)->firstOrFail();

    $payload = validAgencyPayload($this->wilaya, $this->commune);
    $payload['commune_id'] = $communeAilleurs->id;

    post(route('agency.register'), $payload)
        ->assertSessionHasErrors('commune_id');
});

it('refuse un telephone qui n est pas au format algerien', function () {
    $payload = validAgencyPayload($this->wilaya, $this->commune);
    $payload['phone'] = '0123456789';

    post(route('agency.register'), $payload)
        ->assertSessionHasErrors('phone');
})->with([
    'numero francais' => '0123456789',
    'trop court' => '05551234',
    'lettres' => '05 55 AB 34 56',
]);

it('ne cree ni utilisateur ni agence si la validation echoue', function () {
    Notification::fake();

    $payload = validAgencyPayload($this->wilaya, $this->commune);
    $payload['email'] = 'pas-un-email';

    post(route('agency.register'), $payload)->assertSessionHasErrors('email');

    // La transaction protege contre le compte orphelin : un utilisateur sans
    // agence resterait bloque sans moyen de terminer son inscription.
    expect(User::where('role', User::ROLE_AGENCY)->count())->toBe(0)
        ->and(Agency::count())->toBe(0);
});

it('envoie l agence en attente vers l ecran dedie, pas vers un dashboard vide', function () {
    Storage::fake('local');
    Notification::fake();

    post(route('agency.register'), validAgencyPayload($this->wilaya, $this->commune));
    $agency = Agency::firstOrFail();

    actingAs($agency->user)
        ->get(route('agency.dashboard'))
        ->assertRedirect(route('agency.pending'));

    actingAs($agency->user)
        ->get(route('agency.pending'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Agency/Pending'));
});

it('inscrit un client avec le role client et sans verification d email', function () {
    post(route('register'), [
        'name' => 'Sofiane Client',
        'email' => 'sofiane@example.dz',
        'phone' => '0661234567',
        'password' => 'motdepasse-solide-1',
        'password_confirmation' => 'motdepasse-solide-1',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'sofiane@example.dz')->firstOrFail();

    expect($user->role)->toBe(User::ROLE_CLIENT)
        ->and($user->hasRole(User::ROLE_CLIENT))->toBeTrue()
        // Un client n'a pas a confirmer son adresse avant sa premiere reservation.
        ->and($user->hasVerifiedEmail())->toBeTrue();
});
