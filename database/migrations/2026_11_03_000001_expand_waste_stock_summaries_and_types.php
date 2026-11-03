<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for comprehensive stock summaries and per-type ledger.
     */
    public function up(): void
    {
        // 1. Perluas kolom waste_stock_summaries untuk menampung seluruh kartu metrik
        Schema::table('waste_stock_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('waste_stock_summaries', 'total_sales_revenue')) {
                $table->decimal('total_sales_revenue', 15, 2)->default(0.00)->after('current_stock_kg');
            }
            if (!Schema::hasColumn('waste_stock_summaries', 'total_pickup_cost')) {
                $table->decimal('total_pickup_cost', 15, 2)->default(0.00)->after('total_sales_revenue');
            }
            if (!Schema::hasColumn('waste_stock_summaries', 'sellable_stock_kg')) {
                $table->decimal('sellable_stock_kg', 14, 2)->default(0.00)->after('total_pickup_cost');
            }
            if (!Schema::hasColumn('waste_stock_summaries', 'residual_stock_kg')) {
                $table->decimal('residual_stock_kg', 14, 2)->default(0.00)->after('sellable_stock_kg');
            }
            if (!Schema::hasColumn('waste_stock_summaries', 'potential_revenue')) {
                $table->decimal('potential_revenue', 15, 2)->default(0.00)->after('residual_stock_kg');
            }
        });

        // 2. Tabel Rekap Stok per Jenis Sampah (O(1) cek batas min/maks stok yang bisa dijual/diangkut)
        if (!Schema::hasTable('waste_type_stock_summaries')) {
            Schema::create('waste_type_stock_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
                $table->foreignId('waste_type_id')->constrained('waste_types')->restrictOnDelete();
                $table->decimal('total_weighed_kg', 14, 2)->default(0.00);
                $table->decimal('total_sold_kg', 14, 2)->default(0.00);
                $table->decimal('available_stock_kg', 14, 2)->default(0.00);
                $table->decimal('potential_revenue', 15, 2)->default(0.00);
                $table->timestamps();

                $table->unique(['campus_id', 'waste_type_id']);
                $table->index(['campus_id', 'available_stock_kg']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_type_stock_summaries');

        Schema::table('waste_stock_summaries', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['total_sales_revenue', 'total_pickup_cost', 'sellable_stock_kg', 'residual_stock_kg', 'potential_revenue'] as $col) {
                if (Schema::hasColumn('waste_stock_summaries', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
