<?php

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleApproved;
use App\Notifications\VehicleRejected;
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
});

it('cache la file des annonces à qui n’est pas administrateur', function () {
    $agency = agencyOn();

    // 404 et non 403 : l'existence d'une administration n'a pas à être
    // confirmée à un inconnu.
    actingAs($agency->user)->get(route('admin.vehicles.index'))->assertNotFound();
});

it('publie une annonce approuvée, la date et prévient l’agence', function () {
    Notification::fake();
    $agency = agencyOn();
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PENDING);

    actingAs($this->admin)->post(route('admin.vehicles.approve', $vehicle->id));

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_PUBLISHED)
        ->and($vehicle->fresh()->published_at)->not->toBeNull();

    Notification::assertSentTo($agency->user, VehicleApproved::class);
});

it('garde la date de première publication après une re-approbation', function () {
    Notification::fake();
    $agency = agencyOn();
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PUBLISHED);
    $vehicle->update(['published_at' => now()->subMonth()]);
    $first = $vehicle->fresh()->published_at;

    $vehicle->update(['status' => Vehicle::STATUS_PENDING]);
    actingAs($this->admin)->post(route('admin.vehicles.approve', $vehicle->id));

    expect($vehicle->fresh()->published_at->toDateString())->toBe($first->toDateString());
});

it('exige un motif pour rejeter une annonce', function () {
    Notification::fake();
    $agency = agencyOn();
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PENDING);

    actingAs($this->admin)
        ->post(route('admin.vehicles.reject', $vehicle->id), [])
        ->assertSessionHasErrors('reason_code');

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_PENDING);
    Notification::assertNothingSent();
});

it('refuse un motif libre trop court pour être corrigé', function () {
    $agency = agencyOn();
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PENDING);

    actingAs($this->admin)
        ->post(route('admin.vehicles.reject', $vehicle->id), ['reason_note' => 'non'])
        ->assertSessionHasErrors('reason_note');
});

it('accepte un motif prédéfini seul et le transmet à l’agence', function () {
    Notification::fake();
    $agency = agencyOn();
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PENDING);

    actingAs($this->admin)
        ->post(route('admin.vehicles.reject', $vehicle->id), ['reason_code' => 'photos'])
        ->assertSessionHasNoErrors();

    expect($vehicle->fresh()->status)->toBe(Vehicle::STATUS_REJECTED)
        ->and($vehicle->fresh()->rejection_reason)->toContain('Photos');

    Notification::assertSentTo($agency->user, VehicleRejected::class);
});

it('combine le motif prédéfini et la précision libre', function () {
    Notification::fake();
    $agency = agencyOn();
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PENDING);

    actingAs($this->admin)->post(route('admin.vehicles.reject', $vehicle->id), [
        'reason_code' => 'prix',
        'reason_note' => 'Le tarif mensuel est supérieur au tarif journalier sur 30 jours.',
    ]);

    expect($vehicle->fresh()->rejection_reason)
        ->toContain('Prix incohérent')
        ->toContain('tarif mensuel');
});

it('inscrit chaque décision au journal d’audit', function () {
    Notification::fake();
    $agency = agencyOn();
    $approved = readyVehicle($agency, Vehicle::STATUS_PENDING);
    $rejected = readyVehicle($agency, Vehicle::STATUS_PENDING);

    actingAs($this->admin)->post(route('admin.vehicles.approve', $approved->id));
    actingAs($this->admin)->post(route('admin.vehicles.reject', $rejected->id), ['reason_code' => 'doublon']);

    expect(AuditLog::where('action', 'vehicle.approved')->where('model_id', $approved->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'vehicle.rejected')->where('model_id', $rejected->id)->exists())->toBeTrue();
});

it('approuve un lot en ne touchant qu’aux annonces en attente', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    $pending = readyVehicle($agency, Vehicle::STATUS_PENDING);
    $draft = readyVehicle($agency, Vehicle::STATUS_DRAFT);

    actingAs($this->admin)->post(route('admin.vehicles.bulk'), [
        'ids' => [$pending->id, $draft->id],
        'action' => 'approve',
    ])->assertSessionHasNoErrors();

    // Un brouillon n'a jamais été soumis : l'approuver publierait une annonce
    // que son agence considère encore comme un travail en cours.
    expect($pending->fresh()->status)->toBe(Vehicle::STATUS_PUBLISHED)
        ->and($draft->fresh()->status)->toBe(Vehicle::STATUS_DRAFT);
});

it('rejette un lot avec un motif commun', function () {
    Notification::fake();
    $agency = agencyOn(Plan::GOLD);
    $one = readyVehicle($agency, Vehicle::STATUS_PENDING);
    $two = readyVehicle($agency, Vehicle::STATUS_PENDING);

    actingAs($this->admin)->post(route('admin.vehicles.bulk'), [
        'ids' => [$one->id, $two->id],
        'action' => 'reject',
        'reason_code' => 'informations',
    ]);

    expect($one->fresh()->status)->toBe(Vehicle::STATUS_REJECTED)
        ->and($two->fresh()->status)->toBe(Vehicle::STATUS_REJECTED);

    Notification::assertSentToTimes($agency->user, VehicleRejected::class, 2);
});

it('montre la prévisualisation complète de l’annonce à l’administrateur', function () {
    $agency = agencyOn();
    $vehicle = readyVehicle($agency, Vehicle::STATUS_PENDING);

    actingAs($this->admin)
        ->get(route('admin.vehicles.show', $vehicle->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Vehicles/Show')
            ->where('vehicle.id', $vehicle->id)
            ->has('vehicle.pricing')
            ->has('vehicle.photos'));
});
