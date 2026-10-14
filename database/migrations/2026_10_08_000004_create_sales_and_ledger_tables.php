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
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->date('sale_date');
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['campus_id', 'sale_date']);
        });

        // 2. Rincian Item Penjualan per Jenis Sampah (Sale Items)
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('waste_type_id')->constrained('waste_types')->restrictOnDelete();
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('price_per_kg', 12, 2);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();

            $table->index('waste_type_id');
        });

        // 3. Jurnal Buku Kas Keuangan (Financial Transactions)
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
            $table->date('transaction_date');
            $table->enum('type', ['credit', 'debit']); // credit = Masuk (K), debit = Keluar (D)
            $table->enum('source', ['sales', 'pickup', 'operational', 'initial_balance']);
            $table->unsignedBigInteger('ref_id')->nullable(); // ID dari sales / pickups / expenses
            $table->decimal('amount', 14, 2);
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'transaction_date']);
            $table->index(['source', 'ref_id']);
        });

        // 4. Saldo Harian Buku Besar per Kampus (General Ledgers)
        Schema::create('general_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
            $table->date('ledger_date');
            $table->decimal('opening_balance', 14, 2)->default(0.00);
            $table->decimal('total_credit', 14, 2)->default(0.00);
            $table->decimal('total_debit', 14, 2)->default(0.00);
            $table->decimal('closing_balance', 14, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['campus_id', 'ledger_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_ledgers');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};

