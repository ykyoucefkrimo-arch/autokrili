<?php

/**
 * Garde-fou sur les traductions.
 *
 * Le §7.2 autorise un arabe partiellement rempli, mais une clé absente est une
 * traduction *oubliée*, pas un choix : elle retombe silencieusement en
 * français au milieu d'une page arabe. Ce test relit les appels `t('…')` du
 * code Vue et refuse de passer si l'un d'eux n'est pas dans les deux fichiers.
 */

/** @return array<int, string> */
function translationKeysUsed(): array
{
    $keys = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js'))
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'vue') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        // t('…') et t("…"), en ignorant les appels dynamiques t(LABELS[…]) :
        // ces derniers passent une valeur, pas un littéral vérifiable ici.
        preg_match_all("/\bt\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*[,)]/", $source, $single);
        preg_match_all('/\bt\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*[,)]/', $source, $double);

        foreach (array_merge($single[1], $double[1]) as $key) {
            $keys[stripslashes($key)] = true;
        }
    }

    return array_keys($keys);
}

it('traduit en arabe toutes les clés que le code utilise', function () {
    $used = translationKeysUsed();
    $arabic = json_decode(file_get_contents(lang_path('ar.json')), true);

    $missing = array_values(array_diff($used, array_keys($arabic)));

    expect($missing)->toBeEmpty(
        'Clés utilisées mais absentes de ar.json : '.implode(', ', $missing)
    );
});

it('déclare aussi ces clés en français', function () {
    $used = translationKeysUsed();
    $french = json_decode(file_get_contents(lang_path('fr.json')), true);

    $missing = array_values(array_diff($used, array_keys($french)));

    expect($missing)->toBeEmpty(
        'Clés utilisées mais absentes de fr.json : '.implode(', ', $missing)
    );
});

it('utilise réellement le dictionnaire', function () {
    // Un test de couverture qui passerait sur zéro clé ne garantirait rien.
    expect(translationKeysUsed())->not->toBeEmpty()
        ->and(count(translationKeysUsed()))->toBeGreaterThan(30);
});

it('n’accumule pas de traductions que plus personne n’utilise', function () {
    $used = translationKeysUsed();
    $french = array_keys(json_decode(file_get_contents(lang_path('fr.json')), true));

    // Les libellés passés dynamiquement — catégories, boîtes, carburants —
    // n'apparaissent pas comme littéraux : ils sont légitimement absents du
    // relevé et ne comptent pas comme orphelins.
    $dynamic = ['Citadine', 'Berline', 'SUV', 'Utilitaire', '4x4', 'Luxe', 'Minibus',
        'Manuelle', 'Automatique', 'Essence', 'Diesel', 'GPL', 'Hybride', 'Électrique',
        'À la journée', 'À la semaine', 'Au mois',
        'À propos', 'Questions fréquentes', 'Conditions générales', 'Confidentialité', 'Contact'];

    $orphans = array_values(array_diff($french, $used, $dynamic));

    expect(count($orphans))->toBeLessThan(30,
        'Traductions probablement orphelines : '.implode(', ', $orphans));
});
