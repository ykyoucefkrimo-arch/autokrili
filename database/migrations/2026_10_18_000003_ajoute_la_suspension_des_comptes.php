<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suspension d'un compte utilisateur (2026-10-18).
 *
 * L'administration pouvait suspendre une AGENCE, pas une personne. Un client
 * qui multiplie les fausses réservations, ou un compte compromis, n'avait
 * aucun frein : il n'existait aucun moyen de lui fermer la porte.
 *
 * **Une date, pas un booléen.** `suspended_at` dit aussi QUAND, ce qu'un
 * `is_suspended` ne dira jamais — et c'est la première question posée quand
 * quelqu'un se plaint de ne plus pouvoir entrer.
 *
 * Le motif est conservé parce qu'il est montré à la personne à sa prochaine
 * tentative de connexion : une porte fermée sans explication se transforme en
 * appel téléphonique.
 *
 * On ne supprime pas de compte : les réservations y font référence, et
 * effacer un client effacerait l'historique de l'agence qui l'a servi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('role');
            $table->string('suspension_reason', 500)->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
