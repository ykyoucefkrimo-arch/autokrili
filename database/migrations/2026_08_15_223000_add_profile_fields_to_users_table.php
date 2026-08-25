<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `role` column duplicates what spatie/laravel-permission already stores.
 * It is kept because the specification asks for it and because a single column
 * makes the common "is this a client or an agency?" check cheap in queries and
 * readable in the database. Roles remain the source of truth for authorisation.
 *
 * Accounts are deleted outright rather than soft-deleted: loi 18-07 grants a
 * right to erasure, and a hidden row still holds the personal data it promised
 * to remove. Bookings survive because they carry their own copy of the client's
 * name and phone, and their client_id is nulled on delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('role', 20)->default('client')->after('phone')->index();
            $table->string('locale', 5)->default('fr')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'locale']);
        });
    }
};
