<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le registre de commerce n'est plus demandé à l'inscription (2026-10-18).
 *
 * Décision du propriétaire. Le formulaire exigeait un numéro de registre, un
 * NIF et le téléversement du document : trois obstacles avant même de savoir
 * si la plateforme vaut la peine. Sur un produit dont toute l'acquisition
 * repose sur le recrutement d'agences, c'est la friction qui coûte le plus.
 *
 * **Les colonnes restent.** Sept agences sont déjà inscrites avec leur
 * registre ; supprimer les colonnes effacerait ces informations sans retour
 * possible, et l'administration continue de les afficher quand elles existent.
 * Seule l'obligation disparaît.
 *
 * `nif` et `trade_register_file` étaient déjà facultatifs : il n'y a donc
 * qu'une colonne à desserrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('trade_register_number', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        // On ne remet pas la contrainte : les agences inscrites entre-temps
        // n'ont pas de numéro, et le retour en arrière échouerait.
    }
};
