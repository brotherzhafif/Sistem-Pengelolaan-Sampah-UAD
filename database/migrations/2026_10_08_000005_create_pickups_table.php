<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Residual Waste Pickup & Hauling (SRS M4).
     */
    public function up(): void
    {
        Schema::create('pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->date('pickup_date');
            $table->decimal('volume_kg', 10, 2);
            $table->decimal('cost_per_kg', 12, 2);
            $table->decimal('total_cost', 14, 2);
            $table->string('driver_name', 100)->nullable();
            $table->string('vehicle_plate', 30)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['campus_id', 'pickup_date']);
            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pickups');
    }
};
