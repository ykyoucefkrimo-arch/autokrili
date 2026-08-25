<?php

use App\Http\Middleware\SetLocale;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(Database\Seeders\WilayaSeeder::class);
    $this->seed(Database\Seeders\PlanSeeder::class);
});

it('sert le français par défaut', function () {
    get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'fr')
            ->where('rtl', false)
            ->where('translations.Rechercher', 'Rechercher'));
});

it('bascule en arabe et retient le choix', function () {
    post(route('locale.switch'), ['locale' => 'ar'])->assertRedirect();

    get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'ar')
            ->where('rtl', true)
            ->where('translations.Rechercher', 'بحث'));
});

it('écrit le sens de lecture dans le document', function () {
    // Sans dir="rtl", l'arabe s'affiche à l'envers sur toute la page.
    expect(get(route('home'))->getContent())->toContain('dir="ltr"');

    post(route('locale.switch'), ['locale' => 'ar']);

    expect(get(route('home'))->getContent())->toContain('dir="rtl"');
});

it('refuse une langue qui n’est pas prise en charge', function () {
    post(route('locale.switch'), ['locale' => 'de'])->assertSessionHasErrors('locale');

    get(route('home'))->assertInertia(fn ($page) => $page->where('locale', 'fr'));
});

it('suit la langue du navigateur en l’absence de choix', function () {
    get(route('home'), ['Accept-Language' => 'ar,fr;q=0.8'])
        ->assertInertia(fn ($page) => $page->where('locale', 'ar'));
});

it('ne change pas les URLs indexables selon la langue', function () {
    // Une seule adresse par page, quelle que soit la langue de lecture : c'est
    // ce qui évite les doublons que le §7.2 ne veut pas.
    $french = get(route('search'))->assertOk();

    post(route('locale.switch'), ['locale' => 'ar']);

    $arabic = get(route('search'))->assertOk();

    expect($french->baseResponse->getStatusCode())->toBe($arabic->baseResponse->getStatusCode());
});

it('garde les deux dictionnaires alignés', function () {
    $fr = json_decode(file_get_contents(lang_path('fr.json')), true);
    $ar = json_decode(file_get_contents(lang_path('ar.json')), true);

    // Une clé absente de l'arabe retombe sur le français : lisible, mais c'est
    // une traduction oubliée, pas un choix.
    expect(array_diff_key($fr, $ar))->toBeEmpty()
        ->and(array_diff_key($ar, $fr))->toBeEmpty();
});

it('sert un dictionnaire mis à jour sans vidage manuel du cache', function () {
    // Le cache est indexe sur la date du fichier : avec un `rememberForever`
    // sur la seule locale, une traduction ajoutee ne s'affichait jamais tant
    // que personne ne vidait le cache a la main, et rien ne le signalait.
    get(route('home'));

    $path = lang_path('fr.json');
    $original = file_get_contents($path);

    try {
        $dictionary = json_decode($original, true);
        $dictionary['__sonde__'] = 'valeur de controle';
        file_put_contents($path, json_encode($dictionary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        touch($path, time() + 1);

        get(route('home'))->assertInertia(
            fn ($page) => $page->where('translations.__sonde__', 'valeur de controle')
        );
    } finally {
        file_put_contents($path, $original);
    }
});

it('déclare exactement les langues prises en charge', function () {
    expect(SetLocale::SUPPORTED)->toBe(['fr', 'ar']);
});
