<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connexion et inscription par compte Google (2026-10-18).
 *
 * Deux changements, et le second est le moins évident.
 *
 * **`google_id`** — l'identifiant stable que Google rend. On ne se repose PAS
 * sur l'adresse e-mail seule pour reconnaître quelqu'un : une adresse peut
 * changer de propriétaire, l'identifiant non. Unique, pour qu'un même compte
 * Google ne puisse pas être rattaché à deux utilisateurs.
 *
 * **`password` devient nullable** — c'est la conséquence qu'on oublie. Un
 * utilisateur arrivé par Google n'a jamais choisi de mot de passe, et il n'y
 * a aucune raison de lui en inventer un : un faux mot de passe aléatoire en
 * base est une donnée morte que personne ne peut utiliser, et qui laisse
 * croire que le compte en a un.
 *
 * Tout ce qui lit `password` doit donc tolérer `null` — la réinitialisation
 * et l'écran de changement de mot de passe en particulier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
            // On ne remet pas `password` en NOT NULL : les comptes créés par
            // Google n'en ont pas, et la contrainte échouerait.
        });
    }
};
