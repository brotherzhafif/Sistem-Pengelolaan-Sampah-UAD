<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Waste Sales & Financial Ledger (SRS M3 & M6).
     */
    public function up(): void
    {
        // 1. Transaksi Penjualan Sampah Terpilah (Sales)
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->date('sale_date');
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['campus_id', 'sale_date']);
        });

        // 2. Rincian Item Penjualan per Jenis Sampah (Sale Items)
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('waste_type_id')->constrained('waste_types')->cascadeOnDelete();
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('price_per_kg', 12, 2);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();

            $table->index('waste_type_id');
        });

        // 3. Jurnal Buku Kas Keuangan (Tabel keuangan - SRS v2)
        Schema::create('keuangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('jenis', ['K', 'D']); // K = Kredit (Pemasukan), D = Debet (Pengeluaran)
            $table->enum('sumber', ['penjualan', 'pengangkutan', 'operasional', 'saldo_awal']);
            $table->unsignedBigInteger('ref_id')->nullable(); // ID dari sales / pickups / expenses
            $table->decimal('nominal', 14, 2);
            $table->string('keterangan', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'tanggal']);
            $table->index(['sumber', 'ref_id']);
        });

        // 4. Saldo Harian Buku Besar per Kampus (Tabel buku_besar - SRS v2)
        Schema::create('buku_besar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->date('tanggal');
            $table->decimal('saldo_awal', 14, 2)->default(0.00);
            $table->decimal('total_kredit', 14, 2)->default(0.00);
            $table->decimal('total_debet', 14, 2)->default(0.00);
            $table->decimal('saldo_akhir', 14, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['campus_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buku_besar');
        Schema::dropIfExists('keuangan');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};

