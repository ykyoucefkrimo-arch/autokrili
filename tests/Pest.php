<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Les rôles et permissions sont des données de référence que l'application
    // suppose présentes, au même titre que les wilayas : sans elles une simple
    // inscription échouerait, ce qui ne dirait rien du code testé.
    ->beforeEach(fn () => test()->seed(Database\Seeders\RoleSeeder::class))
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
 * Fabriques partagees par les tests d'annonces. Elles vivent ici plutot que
 * dans un fichier de test : deux fichiers s'en servent, et une fonction
 * declaree dans l'un ne serait chargee que si l'autre passe apres.
 */

use App\Models\Agency;
use App\Models\Commune;
use App\Models\Plan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Wilaya;
use Illuminate\Support\Facades\Hash;

/** An approved agency on the given plan, with its owner signed in. */
function agencyOn(string $planSlug = Plan::SILVER, bool $trusted = false, string $email = 'agence@test.dz'): Agency
{
    $wilaya = Wilaya::where('code', '16')->firstOrFail();
    $commune = Commune::where('wilaya_id', $wilaya->id)->firstOrFail();

    $user = User::create([
        'name' => 'Gérant', 'email' => $email,
        'password' => Hash::make('password'), 'role' => User::ROLE_AGENCY,
        'email_verified_at' => now(),
    ]);
    $user->syncRoles([User::ROLE_AGENCY]);

    $agency = Agency::create([
        'user_id' => $user->id,
        'commercial_name' => 'Agence Test '.$user->id,
        'slug' => 'agence-test-'.$user->id,
        'manager_name' => 'Gérant',
        'trade_register_number' => '16/00-999',
        'wilaya_id' => $wilaya->id,
        'commune_id' => $commune->id,
        'address' => '1 rue du Test',
        'phone' => '0555000000',
        'status' => Agency::STATUS_APPROVED,
        'approved_at' => now(),
        'is_trusted' => $trusted,
    ]);

    $agency->subscriptions()->create([
        'plan_id' => Plan::where('slug', $planSlug)->firstOrFail()->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addYear(),
        'status' => 'active',
    ]);

    return $agency;
}

function vehiclePayload(Agency $agency, array $overrides = []): array
{
    return array_merge([
        'brand' => 'Renault',
        'model' => 'Clio 5',
        'year' => 2023,
        'category' => 'citadine',
        'transmission' => 'manuelle',
        'fuel' => 'diesel',
        'seats' => 5,
        'doors' => 5,
        'air_conditioning' => true,
        'mileage_limit_per_day' => 200,
        'description' => 'Véhicule récent, entretien à jour.',
        'pickup_wilaya_id' => $agency->wilaya_id,
        'pickup_commune_id' => $agency->commune_id,
        'with_driver_available' => false,
        'pricing' => ['daily' => 4500, 'weekly' => 27000, 'monthly' => 100000],
    ], $overrides);
}

/** A listing complete enough to be submitted: one photo, one daily rate. */
function readyVehicle(Agency $agency, string $status = Vehicle::STATUS_DRAFT): Vehicle
{
    $vehicle = $agency->vehicles()->create([
        'brand' => 'Peugeot', 'model' => '208', 'year' => 2022,
        'slug' => 'peugeot-208-'.uniqid(),
        'category' => 'citadine', 'transmission' => 'manuelle', 'fuel' => 'essence',
        'seats' => 5, 'doors' => 5,
        'pickup_wilaya_id' => $agency->wilaya_id,
        'pickup_commune_id' => $agency->commune_id,
        'status' => $status,
    ]);

    $vehicle->pricingRules()->create(['duration_type' => 'daily', 'price_dzd' => 4200, 'min_days' => 1]);
    $vehicle->photos()->create(['path' => 'vehicles/x.webp', 'sort_order' => 1, 'is_cover' => true]);

    return $vehicle;
}

