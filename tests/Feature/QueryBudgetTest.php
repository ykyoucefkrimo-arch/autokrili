<?php

use App\Models\Plan;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Le §12 exige « aucune requête N+1 ». Le vérifier une fois à la main ne
 * protège de rien : ces tests comptent les requêtes des pages les plus
 * chargées et échouent si un `with()` disparaît un jour.
 *
 * Le budget est volontairement large — il attrape le passage de « quelques
 * requêtes » à « une par ligne », pas les variations de une ou deux.
 */

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);

    $this->agency = agencyOn(Plan::GOLD);

    // Douze annonces : une page de résultats pleine.
    $this->vehicles = collect(range(1, 12))->map(function () {
        $vehicle = readyVehicle($this->agency, Vehicle::STATUS_PUBLISHED);
        $vehicle->update(['published_at' => now()]);

        return $vehicle;
    });
});

/** @return array{count: int, queries: array<int, string>} */
function countQueries(callable $action): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $action();

    DB::flushQueryLog();

    return ['count' => count($queries), 'queries' => $queries];
}

it('sert la page de résultats sans une requête par annonce', function () {
    $result = countQueries(fn () => get(route('search'))->assertOk());

    // Douze annonces : un N+1 sur l'agence, les photos ou les tarifs ferait
    // exploser ce compte bien au-delà.
    expect($result['count'])->toBeLessThan(25, implode("\n", $result['queries']));
});

it('sert la page d’accueil sans une requête par annonce', function () {
    $result = countQueries(fn () => get(route('home'))->assertOk());

    expect($result['count'])->toBeLessThan(25, implode("\n", $result['queries']));
});

it('sert la fiche agence sans une requête par véhicule', function () {
    $result = countQueries(
        fn () => get(route('agency.public', $this->agency->slug))->assertOk()
    );

    expect($result['count'])->toBeLessThan(25, implode("\n", $result['queries']));
});

it('sert la liste des annonces de l’agence sans requête par ligne', function () {
    $result = countQueries(
        fn () => actingAs($this->agency->user)->get(route('agency.vehicles.index'))->assertOk()
    );

    expect($result['count'])->toBeLessThan(30, implode("\n", $result['queries']));
});

it('ne relit pas la liste de référence des wilayas une fois en cache', function () {
    // Premier appel : la liste est lue puis mise en cache (§12).
    get(route('home'));

    $result = countQueries(fn () => get(route('home'))->assertOk());

    // L'accueil interroge `wilayas` pour d'autres besoins — le compteur des
    // stats, les wilayas qui ont des annonces, la wilaya de retrait de chaque
    // carte. Ce qui doit disparaître, c'est la liste complète triée par code,
    // celle qui remplit la barre de recherche.
    $referenceList = array_filter(
        $result['queries'],
        fn (string $sql) => str_contains($sql, 'from "wilayas"')
            && str_contains($sql, 'order by "code"')
    );

    expect($referenceList)->toBeEmpty(implode(PHP_EOL, $referenceList));
});
