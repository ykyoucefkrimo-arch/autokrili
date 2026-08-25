<?php

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\Plan;
use App\Models\User;
use App\Models\Wilaya;
use App\Notifications\AgencyApproved;
use App\Notifications\AgencyRejected;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(Database\Seeders\RoleSeeder::class);
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);
    $this->admin->syncRoles([User::ROLE_ADMIN]);
});

function makeAgency(string $status = Agency::STATUS_PENDING, string $email = 'agence@test.dz'): Agency
{
    $wilaya = Wilaya::where('code', '16')->firstOrFail();
    $commune = Commune::where('wilaya_id', $wilaya->id)->firstOrFail();

    $user = User::create([
        'name' => 'Gérant', 'email' => $email,
        'password' => Hash::make('password'), 'role' => User::ROLE_AGENCY,
        'email_verified_at' => now(),
    ]);
    $user->syncRoles([User::ROLE_AGENCY]);

    return Agency::create([
        'user_id' => $user->id,
        'commercial_name' => 'Agence Test',
        'slug' => 'agence-test-'.$user->id,
        'manager_name' => 'Gérant',
        'trade_register_number' => '16/00-999',
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'address' => '1 rue du Test',
        'phone' => '0555000000',
        'status' => $status,
        'trade_register_file' => 'agencies/trade-registers/test.pdf',
    ]);
}

it('interdit le back office aux clients et aux agences', function () {
    $client = User::create([
        'name' => 'Client', 'email' => 'client@test.dz',
        'password' => Hash::make('password'), 'role' => User::ROLE_CLIENT,
    ]);

    actingAs($client)->get(route('admin.agencies.index'))->assertNotFound();

    $agency = makeAgency(Agency::STATUS_APPROVED);
    actingAs($agency->user)->get(route('admin.agencies.index'))->assertNotFound();
});

it('approuve une agence, lui attribue Silver et la notifie', function () {
    Notification::fake();
    $agency = makeAgency();

    actingAs($this->admin)
        ->post(route('admin.agencies.approve', $agency))
        ->assertRedirect();

    $agency->refresh();

    expect($agency->status)->toBe(Agency::STATUS_APPROVED)
        ->and($agency->approved_at)->not->toBeNull()
        // Toute agence approuvee doit avoir une formule : les controles de
        // quota posent une question et ont besoin d'une reponse.
        ->and($agency->currentPlan()->slug)->toBe(Plan::SILVER);

    Notification::assertSentTo($agency->user, AgencyApproved::class);
});

it('exige un motif pour rejeter', function () {
    $agency = makeAgency();

    actingAs($this->admin)
        ->post(route('admin.agencies.reject', $agency), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($agency->refresh()->status)->toBe(Agency::STATUS_PENDING);
});

it('rejette avec motif et previent l agence', function () {
    Notification::fake();
    $agency = makeAgency();
    $motif = 'Le registre de commerce transmis est illisible.';

    actingAs($this->admin)->post(route('admin.agencies.reject', $agency), ['reason' => $motif]);

    $agency->refresh();

    expect($agency->status)->toBe(Agency::STATUS_REJECTED)
        ->and($agency->rejection_reason)->toBe($motif);

    Notification::assertSentTo($agency->user, AgencyRejected::class);
});

it('inscrit chaque decision au journal d audit', function () {
    Notification::fake();
    $agency = makeAgency();

    actingAs($this->admin)->post(route('admin.agencies.approve', $agency));

    $entry = AuditLog::where('action', 'agency.approved')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->admin_id)->toBe($this->admin->id)
        ->and($entry->model_id)->toBe($agency->id)
        ->and($entry->new_values['status'])->toBe(Agency::STATUS_APPROVED);
});

it('laisse une agence suspendue consulter son tableau de bord en lecture seule', function () {
    Notification::fake();
    $agency = makeAgency(Agency::STATUS_APPROVED);

    actingAs($this->admin)->post(route('admin.agencies.suspend', $agency), [
        'reason' => 'Plaintes répétées de clients sur l’état des véhicules.',
    ]);

    expect($agency->refresh()->status)->toBe(Agency::STATUS_SUSPENDED);

    // La lecture reste ouverte ; c'est l'ecriture qui sera refusee.
    actingAs($agency->user)->get(route('agency.dashboard'))->assertOk();
});

it('reactive une agence suspendue', function () {
    Notification::fake();
    $agency = makeAgency(Agency::STATUS_SUSPENDED);

    actingAs($this->admin)->post(route('admin.agencies.reinstate', $agency));

    expect($agency->refresh()->status)->toBe(Agency::STATUS_APPROVED)
        ->and($agency->rejection_reason)->toBeNull();
});

it('ne sert le registre de commerce qu a l administrateur et au proprietaire', function () {
    Storage::fake('local');
    $agency = makeAgency();
    Storage::disk('local')->put($agency->trade_register_file, 'contenu');

    actingAs($this->admin)
        ->get(route('admin.agencies.trade-register', $agency))
        ->assertOk();

    // Une autre agence ne doit jamais atteindre le document d'un concurrent.
    $autre = makeAgency(Agency::STATUS_APPROVED, 'autre@test.dz');
    actingAs($autre->user)
        ->get(route('admin.agencies.trade-register', $agency))
        ->assertNotFound();
});

it('filtre la liste des agences par statut et par recherche', function () {
    makeAgency(Agency::STATUS_PENDING, 'a@test.dz');
    $approuvee = makeAgency(Agency::STATUS_APPROVED, 'b@test.dz');
    $approuvee->update(['commercial_name' => 'Oran Auto']);

    actingAs($this->admin)
        ->get(route('admin.agencies.index', ['status' => 'approved']))
        ->assertInertia(fn ($page) => $page->has('agencies.data', 1));

    actingAs($this->admin)
        ->get(route('admin.agencies.index', ['search' => 'Oran']))
        ->assertInertia(fn ($page) => $page->has('agencies.data', 1));
});
