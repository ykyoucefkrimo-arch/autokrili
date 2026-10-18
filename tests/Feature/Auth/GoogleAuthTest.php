<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

/**
 * Connexion et inscription par compte Google.
 *
 * Posée le 2026-10-18 : sur un marché grand public et mobile, choisir un mot
 * de passe au clavier d'un téléphone est la marche la plus haute du parcours
 * d'inscription.
 *
 * Ce qui se joue ici n'est pas l'appel à Google — Socialite s'en charge —
 * mais les trois décisions de rattachement, et la frontière avec les agences.
 */
function profilGoogle(string $id = '1234567890', ?string $email = 'nadia@gmail.com', ?string $nom = 'Nadia B.'): SocialiteUser
{
    $profil = new SocialiteUser();
    $profil->id = $id;
    $profil->name = $nom;
    $profil->email = $email;

    return $profil;
}

function googleRend(SocialiteUser $profil): void
{
    $pilote = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $pilote->shouldReceive('user')->andReturn($profil);
    Socialite::shouldReceive('driver')->with('google')->andReturn($pilote);
}

beforeEach(function () {
    config([
        'services.google.client_id' => 'identifiant-de-test',
        'services.google.client_secret' => 'secret-de-test',
    ]);
});

it('cree un compte client verifie au premier passage', function () {
    googleRend(profilGoogle());

    get(route('auth.google.retour'))->assertRedirect();

    $utilisateur = User::where('email', 'nadia@gmail.com')->firstOrFail();

    expect($utilisateur->role)->toBe(User::ROLE_CLIENT)
        ->and($utilisateur->google_id)->toBe('1234567890')
        // Google ne rend que des adresses dont il a confirme la possession :
        // reclamer une seconde verification n'aurait aucun sens.
        ->and($utilisateur->email_verified_at)->not->toBeNull()
        // Pas de faux mot de passe aleatoire : ce serait une donnee morte
        // qui ferait croire que le compte en a un.
        ->and($utilisateur->password)->toBeNull();

    assertAuthenticatedAs($utilisateur);
});

it('rattache un compte existant cree au mot de passe', function () {
    $existant = User::factory()->create([
        'email' => 'nadia@gmail.com',
        'role' => User::ROLE_CLIENT,
    ]);

    googleRend(profilGoogle());

    get(route('auth.google.retour'))->assertRedirect();

    // Un seul compte, pas deux : refuser aurait affiche « adresse deja
    // prise » a quelqu'un qui EST cette adresse.
    expect(User::where('email', 'nadia@gmail.com')->count())->toBe(1);
    assertDatabaseHas('users', ['id' => $existant->id, 'google_id' => '1234567890']);
    assertAuthenticatedAs($existant->fresh());
});

it('reconnait par identifiant Google meme si l adresse a change', function () {
    $connu = User::factory()->create([
        'email' => 'ancienne@gmail.com',
        'google_id' => '1234567890',
    ]);

    googleRend(profilGoogle(email: 'nouvelle@gmail.com'));

    get(route('auth.google.retour'))->assertRedirect();

    // L'identifiant fait foi, pas l'adresse : une adresse peut changer de
    // proprietaire, l'identifiant non.
    assertAuthenticatedAs($connu->fresh());
    expect(User::count())->toBe(1);
});

it('refuse un profil Google sans adresse', function () {
    googleRend(profilGoogle(email: null));

    get(route('auth.google.retour'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe(0);
});

it('repond 404 tant que les identifiants ne sont pas renseignes', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    get(route('auth.google'))->assertNotFound();
    get(route('auth.google.retour'))->assertNotFound();
});

it('n expose le bouton que si le service est configure', function () {
    get(route('login'))->assertInertia(fn ($page) => $page->where('googleAuth', true));

    config(['services.google.client_id' => null]);

    get(route('login'))->assertInertia(fn ($page) => $page->where('googleAuth', false));
});

/**
 * La frontiere qui compte : une agence ne se cree pas en un clic.
 *
 * L'inscription d'une agence collecte un registre de commerce et passe par
 * la moderation. Google creant un compte en un clic contournerait la
 * verification que la plateforme promet a ses visiteurs.
 */
it('ne cree jamais qu un client, jamais une agence', function () {
    googleRend(profilGoogle());

    get(route('auth.google.retour'));

    expect(User::where('email', 'nadia@gmail.com')->first()->role)->toBe(User::ROLE_CLIENT);
    expect(App\Models\Agency::count())->toBe(0);
});

/**
 * Le 404 du 18 octobre 2026, en production.
 *
 * Quelqu'un ouvre l'espace agence en visiteur. `auth` retient l'adresse sous
 * `url.intended` et le renvoie se connecter. Il choisit Google, le compte se
 * cree — et `intended()` le repose sur `/agence`, ou la garde repond 404 a
 * qui n'a pas d'agence. Connexion reussie, page blanche.
 *
 * La regle : une intention qui mene a un mur n'est pas honoree.
 */
it('ignore une intention que le role ne peut pas atteindre', function (string $mur) {
    googleRend(profilGoogle());

    session(['url.intended' => url($mur)]);

    get(route('auth.google.retour'))->assertRedirect(route('home'));
})->with([
    'espace agence' => '/agence',
    'administration' => '/admin',
    'une page profonde de l administration' => '/admin/agences/3',
]);

it('honore une intention ordinaire', function () {
    googleRend(profilGoogle());

    session(['url.intended' => url('/vehicules')]);

    // Sans cela, cliquer « Reserver » puis se connecter ferait perdre le
    // vehicule qu'on regardait : le filtre ne doit pas tout jeter.
    get(route('auth.google.retour'))->assertRedirect(url('/vehicules'));
});

it('refuse une intention vers un autre domaine', function () {
    googleRend(profilGoogle());

    session(['url.intended' => 'https://autokrili-pas-vraiment.example/connexion']);

    // Une redirection ouverte sert a faire passer une page d'hameconnage pour
    // la notre : l'utilisateur part de chez nous et atterrit ailleurs sans
    // avoir rien fait d'autre que se connecter.
    get(route('auth.google.retour'))->assertRedirect(route('home'));
});

it('conduit une agence a son tableau de bord', function () {
    // `agencyOn` a besoin des wilayas et des forfaits ; on ne les amorce que
    // pour ce cas, les autres n'ont aucune geographie a connaitre.
    seed(Database\Seeders\WilayaSeeder::class);
    seed(Database\Seeders\PlanSeeder::class);

    $agence = agencyOn(email: 'gerant@gmail.com');

    // Une agence PEUT se connecter par Google ; ce qui est interdit, c'est de
    // CREER une agence ainsi, puisque l'inscription passe par la moderation.
    googleRend(profilGoogle(email: 'gerant@gmail.com'));

    get(route('auth.google.retour'))->assertRedirect(route('agency.dashboard'));

    expect($agence->user->fresh()->google_id)->toBe('1234567890');
});
