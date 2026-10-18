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
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'address' => '12 rue Didouche Mourad',
        'phone' => '0555123456',
        'email' => 'contact@alger-location.dz',
        'password' => 'motdepasse-solide-1',
        'password_confirmation' => 'motdepasse-solide-1',
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

/**
 * Le registre de commerce n'est plus demande (2026-10-18).
 *
 * Il l'etait : numero, NIF et televersement du document, trois obstacles
 * avant de savoir si la plateforme vaut la peine. Sur un produit dont toute
 * l'acquisition repose sur le recrutement d'agences, c'etait la friction la
 * plus couteuse.
 *
 * Les colonnes restent en base pour les agences deja inscrites, et
 * l'administration les affiche quand elles existent.
 */
it('inscrit une agence sans registre de commerce ni NIF', function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();

    post(route('agency.register'), validAgencyPayload($this->wilaya, $this->commune))
        ->assertRedirect(route('agency.pending'));

    $agency = Agency::firstOrFail();

    expect($agency->trade_register_number)->toBeNull()
        ->and($agency->nif)->toBeNull()
        ->and($agency->trade_register_file)->toBeNull()
        // La moderation reste : c'est elle qui protege les visiteurs, pas le
        // document.
        ->and($agency->status)->toBe(Agency::STATUS_PENDING);
});

it('ignore un registre de commerce envoye malgre tout', function () {
    Storage::fake('local');
    Notification::fake();

    // Un vieux formulaire en cache, ou un robot, peut encore poster le champ.
    // Rien ne doit etre ecrit sur le disque pour autant.
    post(route('agency.register'), validAgencyPayload($this->wilaya, $this->commune) + [
        'trade_register_file' => UploadedFile::fake()->create('registre.pdf', 200, 'application/pdf'),
        'trade_register_number' => '16/00-1234567',
    ])->assertRedirect(route('agency.pending'));

    expect(Agency::firstOrFail()->trade_register_file)->toBeNull();
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
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

/* --- Inscription depuis un compte deja connecte (2026-10-18) -------------- */

/**
 * Le bouton « Inscrire mon agence » paraissait mort.
 *
 * La route etait dans le groupe `guest`, qui reconduit tout utilisateur
 * connecte vers son tableau de bord. Depuis l'arrivee de la connexion Google,
 * beaucoup de visiteurs sont connectes : il fallait se deconnecter pour
 * inscrire son agence, ce que personne ne devine.
 */
it('ouvre le formulaire a un client deja connecte', function () {
    $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

    actingAs($client)->get(route('agency.register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/RegisterAgency')
            ->where('compte.email', $client->email));
});

it('rattache l agence au compte connecte sans en creer un second', function () {
    Notification::fake();

    $client = User::factory()->create([
        'role' => User::ROLE_CLIENT,
        'email_verified_at' => now(),
    ]);
    $avant = User::count();

    $donnees = validAgencyPayload($this->wilaya, $this->commune);
    unset($donnees['email'], $donnees['password'], $donnees['password_confirmation']);

    actingAs($client)->post(route('agency.register'), $donnees)
        ->assertRedirect(route('agency.pending'));

    expect(User::count())->toBe($avant)
        ->and($client->fresh()->role)->toBe(User::ROLE_AGENCY)
        ->and($client->fresh()->activeAgency()->status)->toBe(Agency::STATUS_PENDING);
});

it('refuse qu un compte connecte impose une autre adresse', function () {
    $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

    // Sinon on pourrait inscrire une agence sous l'adresse d'un tiers tout en
    // en gardant le controle.
    actingAs($client)->post(route('agency.register'),
        validAgencyPayload($this->wilaya, $this->commune) + ['email' => 'quelqu-un-dautre@test.dz']
    )->assertSessionHasErrors('email');
});

it('repose chez elle une agence qui en a deja une', function () {
    $agence = agencyOn();

    actingAs($agence->user)->get(route('agency.register'))
        ->assertRedirect(route('agency.dashboard'));
});
