<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Master Data (SRS M7).
     */
    public function up(): void
    {
        // 1. Master Titik Sumber Sampah per Kampus (Area Taman, Kantin, Asrama, Gedung Rektorat, TPS)
        Schema::create('waste_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['campus_id', 'name']);
        });

        // 2. Master Jenis Sampah (9 kategori granular sesuai SRS v2)
        Schema::create('waste_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('category', 50); // Organik, Anorganik, Residu
            $table->decimal('default_price_per_kg', 12, 2)->default(0.00);
            $table->boolean('is_sellable')->default(false); // true: anorganik terpilah, false: residu/kompos
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Master Vendor Pengangkut Residu (Nama, Kontak, Tarif per kg)
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('contact', 100)->nullable();
            $table->decimal('cost_per_kg', 12, 2)->default(0.00); // e.g. Rp 150/kg
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Master Pembeli / Pengepul Sampah Terpilah
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('contact', 100)->nullable();
            $table->timestamps();
        });

        // 5. Master Kategori Pengeluaran Operasional (Upah, Konsumsi, Karung, Pakan, Obat P3K)
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('buyers');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('waste_types');
        Schema::dropIfExists('waste_sources');
    }
};

