<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The plan matrix lives here, never in PHP constants: the specification
 * requires the administrator to edit quotas and prices without a deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->unsignedInteger('price_dzd')->default(0);

            // NULL means unlimited. A sentinel like 9999 would eventually be
            // shown to a user, or compared as a number and quietly cap someone.
            $table->unsignedInteger('max_listings')->nullable();
            $table->unsignedInteger('max_photos')->default(1);
            $table->unsignedInteger('max_users')->default(1);

            $table->boolean('has_commune_priority')->default(false);
            $table->boolean('has_wilaya_priority')->default(false);
            $table->boolean('has_homepage_feature')->default(false);
            $table->boolean('can_reply_reviews')->default(false);

            // basic | advanced | premium — drives which dashboard blocks unlock.
            $table->string('stats_level', 20)->default('basic');

            $table->string('badge_label', 30)->nullable();
            $table->string('badge_color', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
