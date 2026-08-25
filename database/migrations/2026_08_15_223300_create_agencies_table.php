<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('commercial_name', 150);
            $table->string('slug', 170)->unique();
            $table->string('manager_name', 150);
            $table->string('trade_register_number', 50);
            $table->string('nif', 30)->nullable();

            $table->foreignId('wilaya_id')->constrained();
            $table->foreignId('commune_id')->constrained();
            $table->string('address', 255);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone', 20);
            $table->string('whatsapp', 20)->nullable();
            $table->string('logo_path')->nullable();
            // Stored outside the public disk: read only through a policy-guarded route.
            $table->string('trade_register_file')->nullable();
            $table->text('description')->nullable();

            $table->string('status', 20)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Trusted agencies skip re-moderation when they edit a live listing.
            $table->boolean('is_trusted')->default(false);

            // Cleaning and inspection gap between two rentals (specification 6.1).
            $table->unsignedSmallInteger('buffer_hours')->default(4);

            // Rental conditions surfaced on the public page.
            $table->unsignedTinyInteger('min_driver_age')->default(21);
            $table->unsignedInteger('default_deposit_dzd')->default(0);
            $table->text('rental_conditions')->nullable();
            $table->json('opening_hours')->nullable();

            // Denormalised for sorting and listing; recomputed when a review is approved.
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'wilaya_id']);
        });

        // Multi-user accounts for Gold and Platinium. The owner stays on
        // agencies.user_id; this table holds the extra seats.
        Schema::create('agency_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('staff');
            $table->timestamps();

            $table->unique(['agency_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_users');
        Schema::dropIfExists('agencies');
    }
};
