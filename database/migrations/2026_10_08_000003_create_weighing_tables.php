<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Daily Waste Weighing (SRS M2).
     */
    public function up(): void
    {
        // 1. Sesi Penimbangan Harian (Weighing Sessions)
        Schema::create('weighing_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('waste_source_id')->nullable()->constrained('waste_sources')->nullOnDelete();
            $table->date('weigh_date');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['campus_id', 'weigh_date']);
        });

        // 2. Rincian Item Penimbangan per Jenis Sampah (Weighing Items)
        Schema::create('weighing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weighing_session_id')->constrained('weighing_sessions')->cascadeOnDelete();
            $table->foreignId('waste_type_id')->constrained('waste_types')->cascadeOnDelete();
            $table->decimal('weight_kg', 10, 2); // Bobot dalam kilogram
            $table->decimal('volume_m3', 8, 3)->nullable(); // Estimasi volume m³ (opsional)
            $table->timestamps();

            $table->index('waste_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weighing_items');
        Schema::dropIfExists('weighing_sessions');
    }
};

