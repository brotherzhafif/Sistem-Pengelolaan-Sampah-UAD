<?php

use App\Models\OrganicOutputCategory;
use App\Models\OrganicProcessing;
use App\Models\WasteStockSummary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to link organic processing outputs to Master Data categories.
     */
    public function up(): void
    {
        if (!Schema::hasTable('organic_output_categories')) {
            Schema::create('organic_output_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Contoh kategori hasil pengolahan organik bawaan agar siap dipilih
        $pupuk = OrganicOutputCategory::firstOrCreate(['name' => 'Pupuk'], ['is_active' => true]);
        $pakan = OrganicOutputCategory::firstOrCreate(['name' => 'Pakan'], ['is_active' => true]);
        OrganicOutputCategory::firstOrCreate(['name' => 'Pupuk Cair'], ['is_active' => true]);

        Schema::table('organic_processings', function (Blueprint $table) {
            if (!Schema::hasColumn('organic_processings', 'organic_output_category_id')) {
                $table->foreignId('organic_output_category_id')
                    ->nullable()
                    ->after('input_weight_kg')
                    ->constrained('organic_output_categories')
                    ->restrictOnDelete();
            }
            if (!Schema::hasColumn('organic_processings', 'output_weight_kg')) {
                $table->decimal('output_weight_kg', 12, 2)->default(0.00)->after('organic_output_category_id');
            }
        });

        Schema::table('waste_stock_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('waste_stock_summaries', 'total_processed_output_kg')) {
                $table->decimal('total_processed_output_kg', 14, 2)->default(0.00)->after('total_processed_kg');
            }
        });

        // Migrasi data lama jika sebelumnya sudah mencatat compost_output_kg / feed_output_kg
        OrganicProcessing::whereNull('organic_output_category_id')->chunkById(100, function ($rows) use ($pupuk, $pakan) {
            foreach ($rows as $row) {
                $compost = (float) ($row->compost_output_kg ?? 0);
                $feed = (float) ($row->feed_output_kg ?? 0);

                if ($compost >= $feed && $compost > 0) {
                    $row->update([
                        'organic_output_category_id' => $pupuk->id,
                        'output_weight_kg' => round($compost + $feed, 2),
                    ]);
                } elseif ($feed > 0) {
                    $row->update([
                        'organic_output_category_id' => $pakan->id,
                        'output_weight_kg' => round($feed, 2),
                    ]);
                } else {
                    $row->update([
                        'organic_output_category_id' => $pupuk->id,
                        'output_weight_kg' => 0.00,
                    ]);
                }
            }
        });

        if (\App\Models\Campus::exists()) {
            WasteStockSummary::syncAllCampuses();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waste_stock_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('waste_stock_summaries', 'total_processed_output_kg')) {
                $table->dropColumn('total_processed_output_kg');
            }
        });

        Schema::table('organic_processings', function (Blueprint $table) {
            if (Schema::hasColumn('organic_processings', 'organic_output_category_id')) {
                $table->dropConstrainedForeignId('organic_output_category_id');
            }
            if (Schema::hasColumn('organic_processings', 'output_weight_kg')) {
                $table->dropColumn('output_weight_kg');
            }
        });

        Schema::dropIfExists('organic_output_categories');
    }
};
