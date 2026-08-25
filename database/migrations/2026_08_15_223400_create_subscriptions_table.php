<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->date('starts_at');
            // NULL means no expiry — used for the permanent Silver fallback.
            $table->date('ends_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('admin_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expiry_notified_at')->nullable();
            $table->timestamps();

            $table->index(['agency_id', 'status']);
        });

        // An agency asks, the administrator grants: no online payment in v1.
        Schema::create('plan_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_plan_id')->constrained('plans');
            $table->string('status', 20)->default('pending')->index();
            $table->text('agency_message')->nullable();
            $table->text('admin_response')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_change_requests');
        Schema::dropIfExists('subscriptions');
    }
};
