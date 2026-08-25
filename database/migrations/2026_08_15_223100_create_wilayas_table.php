<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayas', function (Blueprint $table) {
            $table->id();
            // Official two-digit code, "01" to "58". Kept as a string so the
            // leading zero survives: it is how Algerians name their wilaya.
            $table->string('code', 2)->unique();
            $table->string('name_fr', 100);
            $table->string('name_ar', 100);
            $table->string('slug', 120)->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wilaya_id')->constrained()->cascadeOnDelete();
            $table->string('name_fr', 120);
            $table->string('name_ar', 120)->nullable();
            $table->string('slug', 140);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            // Two wilayas may hold communes of the same name; the pair is unique.
            $table->unique(['wilaya_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
        Schema::dropIfExists('wilayas');
    }
};
