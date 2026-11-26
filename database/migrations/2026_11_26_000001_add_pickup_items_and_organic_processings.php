<?php

use App\Models\Campus;
use App\Models\Pickup;
use App\Models\PickupItem;
use App\Models\WasteStockSummary;
use App\Models\WasteType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for itemized pickups and organic waste self-processing.
     */
    public function up(): void
    {
        // 1. Rincian jenis sampah per transaksi pengangkutan
        if (!Schema::hasTable('pickup_items')) {
            Schema::create('pickup_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pickup_id')->constrained('pickups')->cascadeOnDelete();
                $table->foreignId('waste_type_id')->constrained('waste_types')->restrictOnDelete();
                $table->decimal('weight_kg', 10, 2);
                $table->timestamps();

                $table->index(['pickup_id', 'waste_type_id']);
            });
        }

        // 2. Pencatatan pengolahan sampah organik mandiri (hasil pupuk & pakan)
        if (!Schema::hasTable('organic_processings')) {
            Schema::create('organic_processings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
                $table->date('process_date');
                $table->decimal('input_weight_kg', 12, 2);
                $table->decimal('compost_output_kg', 12, 2)->default(0.00);
                $table->decimal('feed_output_kg', 12, 2)->default(0.00);
                $table->string('method', 50)->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['campus_id', 'process_date']);
            });
        }

        // 3. Rincian jenis sampah organik yang masuk pengolahan mandiri
        if (!Schema::hasTable('organic_processing_items')) {
            Schema::create('organic_processing_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organic_processing_id')->constrained('organic_processings')->cascadeOnDelete();
                $table->foreignId('waste_type_id')->constrained('waste_types')->restrictOnDelete();
                $table->decimal('weight_kg', 10, 2);
                $table->timestamps();

                $table->index(['organic_processing_id', 'waste_type_id']);
            });
        }

        // 4. Perluas ringkasan stok per jenis sampah
        Schema::table('waste_type_stock_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('waste_type_stock_summaries', 'total_processed_kg')) {
                $table->decimal('total_processed_kg', 14, 2)->default(0.00)->after('total_sold_kg');
            }
            if (!Schema::hasColumn('waste_type_stock_summaries', 'total_picked_up_kg')) {
                $table->decimal('total_picked_up_kg', 14, 2)->default(0.00)->after('total_processed_kg');
            }
        });

        // 5. Perluas ringkasan stok kampus untuk memisahkan stok organik vs residu & hasil olah
        Schema::table('waste_stock_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('waste_stock_summaries', 'total_processed_kg')) {
                $table->decimal('total_processed_kg', 14, 2)->default(0.00)->after('total_sold_kg');
            }
            if (!Schema::hasColumn('waste_stock_summaries', 'organic_stock_kg')) {
                $table->decimal('organic_stock_kg', 14, 2)->default(0.00)->after('sellable_stock_kg');
            }
            if (!Schema::hasColumn('waste_stock_summaries', 'total_compost_kg')) {
                $table->decimal('total_compost_kg', 14, 2)->default(0.00)->after('organic_stock_kg');
            }
            if (!Schema::hasColumn('waste_stock_summaries', 'total_feed_kg')) {
                $table->decimal('total_feed_kg', 14, 2)->default(0.00)->after('total_compost_kg');
            }
        });

        // 6. Perluas log mutasi stok
        Schema::table('waste_stock_mutations', function (Blueprint $table) {
            if (!Schema::hasColumn('waste_stock_mutations', 'total_processed_after')) {
                $table->decimal('total_processed_after', 14, 2)->default(0.00)->after('total_sold_after');
            }
        });

        // 7. Backfill pickup_items untuk data pengangkutan lama yang belum memiliki rincian item
        $defaultResidualType = WasteType::where('name', 'Residu Campur')->first()
            ?? WasteType::where('is_sellable', false)
                ->whereRaw('LOWER(category) != ?', ['organik'])
                ->first();

        if ($defaultResidualType) {
            Pickup::doesntHave('items')->chunkById(100, function ($pickups) use ($defaultResidualType) {
                foreach ($pickups as $pickup) {
                    if ((float) $pickup->volume_kg > 0) {
                        PickupItem::create([
                            'pickup_id' => $pickup->id,
                            'waste_type_id' => $defaultResidualType->id,
                            'weight_kg' => (float) $pickup->volume_kg,
                        ]);
                    }
                }
            });
        }

        // Resinkronisasi ringkasan stok per kampus agar kolom baru konsisten
        if (Campus::exists()) {
            WasteStockSummary::syncAllCampuses();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organic_processing_items');
        Schema::dropIfExists('organic_processings');
        Schema::dropIfExists('pickup_items');

        Schema::table('waste_stock_mutations', function (Blueprint $table) {
            if (Schema::hasColumn('waste_stock_mutations', 'total_processed_after')) {
                $table->dropColumn('total_processed_after');
            }
        });

        Schema::table('waste_stock_summaries', function (Blueprint $table) {
            $cols = [];
            foreach (['total_processed_kg', 'organic_stock_kg', 'total_compost_kg', 'total_feed_kg'] as $col) {
                if (Schema::hasColumn('waste_stock_summaries', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('waste_type_stock_summaries', function (Blueprint $table) {
            $cols = [];
            foreach (['total_processed_kg', 'total_picked_up_kg'] as $col) {
                if (Schema::hasColumn('waste_type_stock_summaries', $col)) {
                    $cols[] = $col;
                }
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
