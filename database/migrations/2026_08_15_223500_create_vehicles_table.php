<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();

            $table->string('brand', 60);
            $table->string('model', 60);
            $table->unsignedSmallInteger('year');
            $table->string('slug', 200);

            // citadine | berline | suv | utilitaire | 4x4 | luxe | minibus
            $table->string('category', 20)->index();
            // manuelle | automatique
            $table->string('transmission', 20);
            // essence | diesel | gpl | hybride | electrique
            $table->string('fuel', 20);

            $table->unsignedTinyInteger('seats')->default(5);
            $table->unsignedTinyInteger('doors')->default(5);
            $table->boolean('air_conditioning')->default(true);
            // NULL means no mileage cap.
            $table->unsignedInteger('mileage_limit_per_day')->nullable();
            $table->text('description')->nullable();

            // Pickup point carried by the listing itself: an agency with several
            // sites simply publishes listings in different communes.
            $table->foreignId('pickup_wilaya_id')->constrained('wilayas');
            $table->foreignId('pickup_commune_id')->constrained('communes');

            // Chauffeur offered as a paid option rather than a separate entity:
            // no second availability engine to reconcile.
            $table->boolean('with_driver_available')->default(false);
            $table->unsignedInteger('driver_price_per_day')->default(0);

            // draft | pending | published | rejected | archived
            $table->string('status', 20)->default('draft')->index();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Serves the public search: published listings of a given wilaya.
            $table->index(['status', 'pickup_wilaya_id']);
            $table->index(['status', 'pickup_commune_id']);
            $table->index(['agency_id', 'status']);
        });

        Schema::create('vehicle_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            // Derivatives kept beside the original: thumbnail, card, full.
            $table->string('path_thumb')->nullable();
            $table->string('path_card')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();

            $table->index(['vehicle_id', 'sort_order']);
        });

        // Degressive pricing: the engine picks the most favourable rule for the
        // requested duration.
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            // daily | weekly | monthly
            $table->string('duration_type', 20);
            $table->unsignedInteger('price_dzd');
            $table->unsignedSmallInteger('min_days')->default(1);
            $table->timestamps();

            $table->unique(['vehicle_id', 'duration_type']);
        });

        // Manual unavailability: maintenance, or a rental booked off-platform.
        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 30)->default('unavailable');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('vehicle_photos');
        Schema::dropIfExists('vehicles');
    }
};
