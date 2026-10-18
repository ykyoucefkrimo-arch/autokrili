<?php

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Modération des comptes (2026-10-18).
 *
 * L'administration savait suspendre une agence, pas une personne. Un client
 * qui multiplie les fausses réservations, ou un compte visiblement compromis,
 * n'avait aucun frein.
 *
 * Ce qui se joue ici n'est pas la liste — c'est ce qu'une suspension ferme
 * vraiment, et les deux comptes auxquels elle ne doit jamais toucher.
 */
beforeEach(function () {
    $this->seed(Database\Seeders\RoleSeeder::class);

    $this->admin = User::create([
        'name' => 'Administrateur', 'email' => 'admin@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);
});

function client(string $email = 'client@test.dz'): User
{
    return User::create([
        'name' => 'Yacine Haddad', 'email' => $email,
        'password' => Hash::make('password'), 'role' => User::ROLE_CLIENT,
        'email_verified_at' => now(),
    ]);
}

/**
 * Suspend, puis redevient visiteur.
 *
 * `actingAs` vaut pour tout le test : sans ce retour a l'etat anonyme, la
 * tentative de connexion qui suit serait faite par l'administrateur, et le
 * groupe `guest` la renverrait sur le tableau de bord sans rien prouver.
 */
function suspendPar(User $admin, User $cible, string $motif): void
{
    actingAs($admin)->post(route('admin.users.suspend', $cible), ['motif' => $motif]);

    auth()->logout();
    session()->flush();
}

it('liste les comptes et les filtre', function () {
    client('un@test.dz');
    client('deux@test.dz');

    actingAs($this->admin)->get(route('admin.users.index', ['role' => User::ROLE_CLIENT]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->has('utilisateurs.data', 2)
            ->where('compteurs.total', 3));

    actingAs($this->admin)->get(route('admin.users.index', ['search' => 'deux@']))
        ->assertInertia(fn ($page) => $page->has('utilisateurs.data', 1));
});

it('cache la liste a un simple client', function () {
    // 404 et non 403 : l'existence du back office ne se confirme pas.
    actingAs(client())->get(route('admin.users.index'))->assertNotFound();
});

it('renvoie un visiteur vers la connexion', function () {
    get(route('admin.users.index'))->assertRedirect(route('login'));
});

it('suspend un compte, ferme sa session et journalise', function () {
    $victime = client();

    actingAs($this->admin)->post(route('admin.users.suspend', $victime), [
        'motif' => 'Trois agences signalent des réservations jamais honorées.',
    ])->assertRedirect();

    $victime->refresh();

    expect($victime->isSuspended())->toBeTrue()
        ->and($victime->suspension_reason)->toContain('jamais honorées')
        ->and(AuditLog::where('action', 'user.suspended')->count())->toBe(1);
});

it('exige un motif qui explique quelque chose', function () {
    $victime = client();

    // « abus » n'explique rien, et c'est ce que la personne lira.
    actingAs($this->admin)->post(route('admin.users.suspend', $victime), ['motif' => 'abus'])
        ->assertSessionHasErrors('motif');

    expect($victime->fresh()->isSuspended())->toBeFalse();
});

it('refuse la connexion a un compte suspendu, en disant pourquoi', function () {
    $victime = client();
    suspendPar($this->admin, $victime, 'Compte utilisé pour des réservations frauduleuses.');

    post(route('login'), ['email' => 'client@test.dz', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    // Le mot de passe était bon : laisser croire à une erreur de saisie
    // enverrait la personne réinitialiser un mot de passe qui fonctionne.
    expect(session('errors')->first('email'))->toContain('suspendu');
    $this->assertGuest();
});

it('ferme la session deja ouverte d un compte suspendu', function () {
    $victime = client();

    // Sans cela la suspension ne prendrait effet que pour quelqu'un qui se
    // déconnecte de lui-même — c'est-à-dire jamais quand elle sert.
    actingAs($victime)->get(route('dashboard'))->assertOk();

    actingAs($this->admin)->post(route('admin.users.suspend', $victime), [
        'motif' => 'Compte compromis, mot de passe à réinitialiser.',
    ]);

    // `fresh()` : `actingAs` pose l'objet tel quel comme utilisateur
    // authentifie, et celui-ci date d'avant la suspension. En production
    // chaque requete le relit en base.
    actingAs($victime->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('rouvre un compte et le laisse revenir', function () {
    $victime = client();
    suspendPar($this->admin, $victime, 'Suspension le temps de vérifier un signalement.');

    actingAs($this->admin)->post(route('admin.users.reinstate', $victime))->assertRedirect();

    auth()->logout();
    session()->flush();

    expect($victime->fresh()->isSuspended())->toBeFalse()
        ->and($victime->fresh()->suspension_reason)->toBeNull()
        ->and(AuditLog::where('action', 'user.reinstated')->count())->toBe(1);

    post(route('login'), ['email' => 'client@test.dz', 'password' => 'password'])
        ->assertRedirect();
    $this->assertAuthenticated();
});

/**
 * Les deux comptes auxquels on ne touche pas.
 *
 * Se suspendre soi-même met fin à sa propre session dans la seconde, et il
 * faut alors un autre administrateur — ou la base — pour revenir. Suspendre
 * un confrère, à deux comptes, donne la main au premier qui clique.
 */
it('refuse de suspendre son propre compte ou un autre administrateur', function (string $cible) {
    $autre = User::create([
        'name' => 'Second administrateur', 'email' => 'admin2@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN,
    ]);

    $victime = $cible === 'soi' ? $this->admin : $autre;

    actingAs($this->admin)->post(route('admin.users.suspend', $victime), [
        'motif' => 'Un motif parfaitement valable et assez long.',
    ])->assertSessionHasErrors('motif');

    expect($victime->fresh()->isSuspended())->toBeFalse();
})->with(['soi', 'un confrere']);

it('ne propose pas le bouton la ou le serveur refuserait', function () {
    client();

    actingAs($this->admin)->get(route('admin.users.index'))
        ->assertInertia(function ($page) {
            $lignes = collect($page->toArray()['props']['utilisateurs']['data']);

            expect($lignes->firstWhere('role', User::ROLE_ADMIN)['intouchable'])->toBeTrue()
                ->and($lignes->firstWhere('role', User::ROLE_CLIENT)['intouchable'])->toBeFalse();
        });
});

it('montre l agence tenue par le compte', function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);
    $agence = agencyOn(Plan::GOLD);

    actingAs($this->admin)->get(route('admin.users.index', ['role' => User::ROLE_AGENCY]))
        ->assertInertia(fn ($page) => $page
            ->where('utilisateurs.data.0.agence.commercial_name', $agence->commercial_name));
});

/**
 * L'agence approuvée qui ne pouvait pas entrer (2026-10-18).
 *
 * Une agence doit confirmer son adresse avant d'accéder à son espace, et le
 * lien part par courriel. En production `MAIL_MAILER=file` : le courriel
 * n'est jamais parti, le compte était approuvé, légitime, et enfermé dehors.
 */
it('permet a l administrateur de confirmer une adresse', function () {
    $agence = client('gerant@test.dz');
    $agence->forceFill(['role' => User::ROLE_AGENCY, 'email_verified_at' => null])->save();

    expect($agence->fresh()->hasVerifiedEmail())->toBeFalse();

    actingAs($this->admin)->post(route('admin.users.verify-email', $agence))->assertRedirect();

    expect($agence->fresh()->hasVerifiedEmail())->toBeTrue()
        // Journalise : ce geste affirme qu'on a joint l'agence autrement, et
        // c'est une affirmation dont on doit pouvoir repondre.
        ->and(AuditLog::where('action', 'user.email_verified')->count())->toBe(1);
});

it('reserve la confirmation d adresse a l administrateur', function () {
    $autre = client('autre@test.dz');
    $autre->forceFill(['email_verified_at' => null])->save();

    actingAs(client())->post(route('admin.users.verify-email', $autre))->assertNotFound();

    // Sinon n'importe quel compte connecte validerait l'adresse d'un autre.
    expect($autre->fresh()->email_verified_at)->toBeNull();
});
