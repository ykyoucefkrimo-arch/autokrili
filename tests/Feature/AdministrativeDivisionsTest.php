<?php

use App\Models\Agency;
use App\Models\Commune;
use App\Models\Plan;
use App\Models\Wilaya;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);
});

it('importe les 69 wilayas et les 1541 communes du fichier officiel', function () {
    $this->seed(Database\Seeders\AlgeriaDivisionsSeeder::class);

    expect(Wilaya::count())->toBe(69)
        ->and(Commune::count())->toBe(1541);

    // Les onze wilayas de la reforme portent un nom francais accentue et un
    // nom arabe : le fichier ne fournit ni l'un ni l'autre correctement.
    $aflou = Wilaya::where('code', '59')->firstOrFail();
    expect($aflou->name_fr)->toBe('Aflou')
        ->and($aflou->name_ar)->toBe('أفلو')
        ->and($aflou->slug)->toBe('aflou');
});

it('garde l’orthographe accentuée des wilayas historiques', function () {
    $this->seed(Database\Seeders\AlgeriaDivisionsSeeder::class);

    // Le fichier ecrit « Bejaia » et « Setif ». Un slug public qui perd ses
    // accents est une URL qui change sous les pieds du visiteur.
    expect(Wilaya::where('code', '06')->value('name_fr'))->toBe('Béjaïa')
        ->and(Wilaya::where('code', '19')->value('name_fr'))->toBe('Sétif');
});

it('conserve la commune d’une agence déjà inscrite', function () {
    $agency = agencyOn(Plan::GOLD);
    $communeId = $agency->commune_id;
    $communeName = $agency->commune->name_fr;

    $this->seed(Database\Seeders\AlgeriaDivisionsSeeder::class);

    // Renumeroter les communes deplacerait chaque agence du pays en silence.
    expect($agency->fresh()->commune_id)->toBe($communeId)
        ->and(Commune::find($communeId)?->name_fr)->toBe($communeName);
});

it('réaligne la wilaya d’une agence dont la commune a changé de main', function () {
    $agency = agencyOn(Plan::GOLD);

    // La reforme deplace des communes : celle-ci part dans une autre wilaya.
    $ailleurs = Wilaya::where('code', '31')->firstOrFail();
    $agency->commune->update(['wilaya_id' => $ailleurs->id]);

    $this->seed(Database\Seeders\AlgeriaDivisionsSeeder::class);

    // Le couple wilaya/commune doit rester coherent, sinon le formulaire
    // refuserait a l'agence sa propre adresse.
    $agency->refresh();
    expect($agency->wilaya_id)->toBe($agency->commune->wilaya_id);
});

it('sert les communes d’une wilaya sur demande', function () {
    $this->seed(Database\Seeders\AlgeriaDivisionsSeeder::class);

    $response = getJson(route('communes.index', ['wilaya' => 'alger']))->assertOk();
    $communes = $response->json();

    $alger = Wilaya::where('slug', 'alger')->firstOrFail();

    expect($communes)->toHaveCount($alger->communes()->count())
        ->and(collect($communes)->pluck('name_fr'))->toContain('Bab Ezzouar')
        // Le nom arabe voyage avec : l'interface arabe du 7.2 en depend.
        ->and($communes[0])->toHaveKeys(['id', 'name_fr', 'name_ar', 'slug']);
});

it('n’expédie plus les 1541 communes avec chaque page', function () {
    $this->seed(Database\Seeders\AlgeriaDivisionsSeeder::class);

    // Une liste de 1541 communes ajouterait ~90 Ko au premier affichage, pour
    // une liste dont le visiteur ne lit qu'une wilaya (specification 7.2,
    // « mobile-first strict »).
    get(route('home'))->assertInertia(fn ($page) => $page
        ->has('searchWilayas', 69)
        ->missing('searchWilayas.0.communes'));
});

it('répond 404 pour une wilaya qui n’existe pas', function () {
    getJson(route('communes.index', ['wilaya' => 'wilaya-inventee']))->assertNotFound();
});

it('laisse un couple wilaya/commune cohérent pour toute la base', function () {
    $agency = agencyOn(Plan::GOLD);
    readyVehicle($agency);

    $this->seed(Database\Seeders\AlgeriaDivisionsSeeder::class);

    $incoherentes = Agency::join('communes', 'communes.id', '=', 'agencies.commune_id')
        ->whereColumn('agencies.wilaya_id', '!=', 'communes.wilaya_id')
        ->count();

    expect($incoherentes)->toBe(0);
});
