<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            // Human-facing identifier, e.g. DZ-2026-00147.
            $table->string('booking_reference', 20)->unique();

            // A cancelled booking must survive the deletion of its listing:
            // it is an accounting trace, not a view over the catalogue.
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_id')->constrained();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->string('pickup_location', 255)->nullable();
            $table->string('dropoff_location', 255)->nullable();
            $table->unsignedSmallInteger('total_days');

            // Contact details copied at booking time: the client may later edit
            // their profile, the booking must keep what was agreed.
            $table->string('client_name', 150);
            $table->string('client_phone', 20);
            $table->string('client_email', 150)->nullable();
            $table->string('driver_license_number', 50)->nullable();

            $table->boolean('with_driver')->default(false);
            $table->unsignedInteger('vehicle_price_dzd')->default(0);
            $table->unsignedInteger('driver_price_dzd')->default(0);
            $table->unsignedInteger('total_price_dzd')->default(0);
            $table->unsignedInteger('deposit_dzd')->default(0);
            // Frozen breakdown of the applied pricing rules, shown to the client.
            $table->json('price_breakdown')->nullable();

            // pending | confirmed | in_progress | completed | cancelled | expired
            $table->string('status', 20)->default('pending')->index();
            $table->text('cancellation_reason')->nullable();
            $table->string('cancelled_by', 20)->nullable();
            $table->text('client_note')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // A pending request expires 24 h after this deadline passes.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('review_invited_at')->nullable();
            $table->timestamps();

            // The overlap query of the availability engine reads exactly this.
            $table->index(['vehicle_id', 'start_date', 'end_date']);
            $table->index(['agency_id', 'status']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->text('agency_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // One review per booking, so a rating cannot be inflated by repetition.
            $table->unique('booking_id');
            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('bookings');
    }
};
