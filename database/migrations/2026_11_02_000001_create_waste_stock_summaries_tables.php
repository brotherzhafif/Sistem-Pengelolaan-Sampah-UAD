<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for real-time waste stock summary & mutation ledger.
     */
    public function up(): void
    {
        // 1. Tabel Utama Rekapitulasi Stok Sampah Real-Time (O(1) read)
        if (!Schema::hasTable('waste_stock_summaries')) {
            Schema::create('waste_stock_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campus_id')->unique()->constrained('campuses')->restrictOnDelete();
                $table->decimal('total_weighed_kg', 14, 2)->default(0.00);    // Akumulasi Masuk (Penimbangan)
                $table->decimal('total_sold_kg', 14, 2)->default(0.00);       // Akumulasi Keluar (Penjualan)
                $table->decimal('total_picked_up_kg', 14, 2)->default(0.00);  // Akumulasi Keluar (Pengangkutan Residu)
                $table->decimal('current_stock_kg', 14, 2)->default(0.00);    // Sisa Stok Terkini (Weighed - Sold - Picked Up)
                $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('campus_id');
            });
        }

        // 2. Tabel Log Mutasi Riwayat Perubahan Rekap Stok (Siapa, Kapan, Berapa kg, Aksi apa)
        if (!Schema::hasTable('waste_stock_mutations')) {
            Schema::create('waste_stock_mutations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
                $table->string('reference_type', 30); // 'weighing', 'sales', 'pickup', 'adjustment'
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('action', 20); // 'create', 'update', 'delete', 'sync'
                $table->decimal('weight_delta_kg', 14, 2)->default(0.00); // Perubahan neto ke stok (+/-)
                $table->decimal('total_weighed_after', 14, 2)->default(0.00);
                $table->decimal('total_sold_after', 14, 2)->default(0.00);
                $table->decimal('total_picked_up_after', 14, 2)->default(0.00);
                $table->decimal('current_stock_after', 14, 2)->default(0.00);
                $table->string('notes', 255)->nullable();
                $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['campus_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_stock_mutations');
        Schema::dropIfExists('waste_stock_summaries');
    }
};

