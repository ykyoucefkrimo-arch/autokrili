<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le rappel J-1 du §10 tourne toutes les heures : sans cette colonne, un
 * client recevrait vingt-quatre fois le meme SMS la veille de son depart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('review_invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
