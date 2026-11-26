<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class WasteStockSummary extends Model
{
    use HasFactory;

    protected $table = 'waste_stock_summaries';

    protected $fillable = [
        'campus_id',
        'total_weighed_kg',
        'total_sold_kg',
        'total_processed_kg',
        'total_processed_output_kg',
        'total_picked_up_kg',
        'current_stock_kg',
        'total_sales_revenue',
        'total_pickup_cost',
        'sellable_stock_kg',
        'organic_stock_kg',
        'total_compost_kg',
        'total_feed_kg',
        'residual_stock_kg',
        'potential_revenue',
        'last_updated_by',
    ];

    protected $casts = [
        'total_weighed_kg' => 'decimal:2',
        'total_sold_kg' => 'decimal:2',
        'total_processed_kg' => 'decimal:2',
        'total_processed_output_kg' => 'decimal:2',
        'total_picked_up_kg' => 'decimal:2',
        'current_stock_kg' => 'decimal:2',
        'total_sales_revenue' => 'decimal:2',
        'total_pickup_cost' => 'decimal:2',
        'sellable_stock_kg' => 'decimal:2',
        'organic_stock_kg' => 'decimal:2',
        'total_compost_kg' => 'decimal:2',
        'total_feed_kg' => 'decimal:2',
        'residual_stock_kg' => 'decimal:2',
        'potential_revenue' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(WasteStockMutation::class, 'campus_id', 'campus_id');
    }

    /**
     * Mengambil baris rekap ringkasan stok untuk unit kampus tertentu (O(1)),
     * atau akumulasi seluruh unit kampus jika $campusId null.
     */
    public static function getMetrics(?int $campusId = null): array
    {
        if ($campusId) {
            $summary = static::firstOrCreate(
                ['campus_id' => $campusId],
                [
                    'total_weighed_kg' => 0.00,
                    'total_sold_kg' => 0.00,
                    'total_processed_kg' => 0.00,
                    'total_processed_output_kg' => 0.00,
                    'total_picked_up_kg' => 0.00,
                    'current_stock_kg' => 0.00,
                    'total_sales_revenue' => 0.00,
                    'total_pickup_cost' => 0.00,
                    'sellable_stock_kg' => 0.00,
                    'organic_stock_kg' => 0.00,
                    'total_compost_kg' => 0.00,
                    'total_feed_kg' => 0.00,
                    'residual_stock_kg' => 0.00,
                    'potential_revenue' => 0.00,
                ]
            );

            return [
                'total_weighed_kg' => (float) $summary->total_weighed_kg,
                'total_sold_kg' => (float) $summary->total_sold_kg,
                'total_processed_kg' => (float) $summary->total_processed_kg,
                'total_processed_output_kg' => (float) $summary->total_processed_output_kg,
                'total_picked_up_kg' => (float) $summary->total_picked_up_kg,
                'current_stock_kg' => (float) $summary->current_stock_kg,
                'total_sales_revenue' => (float) $summary->total_sales_revenue,
                'total_pickup_cost' => (float) $summary->total_pickup_cost,
                'sellable_stock_kg' => (float) $summary->sellable_stock_kg,
                'organic_stock_kg' => (float) $summary->organic_stock_kg,
                'total_compost_kg' => (float) $summary->total_compost_kg,
                'total_feed_kg' => (float) $summary->total_feed_kg,
                'residual_stock_kg' => (float) $summary->residual_stock_kg,
                'potential_revenue' => (float) $summary->potential_revenue,
                'last_updated_at' => $summary->updated_at,
            ];
        }

        // Jika null (Super Admin / Global view), agregasi dari tabel summary kampus
        $totals = static::selectRaw('
            COALESCE(SUM(total_weighed_kg), 0) as total_weighed,
            COALESCE(SUM(total_sold_kg), 0) as total_sold,
            COALESCE(SUM(total_processed_kg), 0) as total_processed,
            COALESCE(SUM(total_processed_output_kg), 0) as total_processed_output,
            COALESCE(SUM(total_picked_up_kg), 0) as total_picked_up,
            COALESCE(SUM(current_stock_kg), 0) as current_stock,
            COALESCE(SUM(total_sales_revenue), 0) as total_revenue,
            COALESCE(SUM(total_pickup_cost), 0) as total_cost,
            COALESCE(SUM(sellable_stock_kg), 0) as sellable_stock,
            COALESCE(SUM(organic_stock_kg), 0) as organic_stock,
            COALESCE(SUM(total_compost_kg), 0) as total_compost,
            COALESCE(SUM(total_feed_kg), 0) as total_feed,
            COALESCE(SUM(residual_stock_kg), 0) as residual_stock,
            COALESCE(SUM(potential_revenue), 0) as pot_revenue
        ')->first();

        return [
            'total_weighed_kg' => (float) ($totals->total_weighed ?? 0.0),
            'total_sold_kg' => (float) ($totals->total_sold ?? 0.0),
            'total_processed_kg' => (float) ($totals->total_processed ?? 0.0),
            'total_processed_output_kg' => (float) ($totals->total_processed_output ?? 0.0),
            'total_picked_up_kg' => (float) ($totals->total_picked_up ?? 0.0),
            'current_stock_kg' => (float) ($totals->current_stock ?? 0.0),
            'total_sales_revenue' => (float) ($totals->total_revenue ?? 0.0),
            'total_pickup_cost' => (float) ($totals->total_cost ?? 0.0),
            'sellable_stock_kg' => (float) ($totals->sellable_stock ?? 0.0),
            'organic_stock_kg' => (float) ($totals->organic_stock ?? 0.0),
            'total_compost_kg' => (float) ($totals->total_compost ?? 0.0),
            'total_feed_kg' => (float) ($totals->total_feed ?? 0.0),
            'residual_stock_kg' => (float) ($totals->residual_stock ?? 0.0),
            'potential_revenue' => (float) ($totals->pot_revenue ?? 0.0),
            'last_updated_at' => now(),
        ];
    }

    /**
     * Memperbarui tabel rekap stok & mencatat mutasi secara atomic (DB::transaction).
     */
    public static function applyMutation(
        int $campusId,
        string $referenceType,
        ?int $referenceId,
        string $action,
        float $weighedDelta = 0.0,
        float $soldDelta = 0.0,
        float $pickedUpDelta = 0.0,
        float $salesRevenueDelta = 0.0,
        float $pickupCostDelta = 0.0,
        array $items = [],
        ?string $notes = null,
        ?int $userId = null,
        float $processedDelta = 0.0,
        float $compostDelta = 0.0,
        float $feedDelta = 0.0,
        float $processedOutputDelta = 0.0
    ): self {
        return DB::transaction(function () use (
            $campusId,
            $referenceType,
            $referenceId,
            $action,
            $weighedDelta,
            $soldDelta,
            $pickedUpDelta,
            $salesRevenueDelta,
            $pickupCostDelta,
            $items,
            $notes,
            $userId,
            $processedDelta,
            $compostDelta,
            $feedDelta,
            $processedOutputDelta
        ) {
            $summary = static::where('campus_id', $campusId)->lockForUpdate()->first();

            if (!$summary) {
                $summary = static::create([
                    'campus_id' => $campusId,
                    'total_weighed_kg' => 0.00,
                    'total_sold_kg' => 0.00,
                    'total_processed_kg' => 0.00,
                    'total_processed_output_kg' => 0.00,
                    'total_picked_up_kg' => 0.00,
                    'current_stock_kg' => 0.00,
                    'total_sales_revenue' => 0.00,
                    'total_pickup_cost' => 0.00,
                    'sellable_stock_kg' => 0.00,
                    'organic_stock_kg' => 0.00,
                    'total_compost_kg' => 0.00,
                    'total_feed_kg' => 0.00,
                    'residual_stock_kg' => 0.00,
                    'potential_revenue' => 0.00,
                    'last_updated_by' => $userId ?? auth()->id(),
                ]);
            }

            // 1. Update stok per waste_type jika items disediakan
            foreach ($items as $item) {
                $wTypeId = (int) ($item['waste_type_id'] ?? 0);
                if ($wTypeId > 0) {
                    $wDelta = (float) ($item['weighed_delta'] ?? 0.0);
                    $sDelta = (float) ($item['sold_delta'] ?? 0.0);
                    $prDelta = (float) ($item['processed_delta'] ?? 0.0);
                    $puDelta = (float) ($item['picked_up_delta'] ?? 0.0);
                    WasteTypeStockSummary::updateStock($campusId, $wTypeId, $wDelta, $sDelta, $prDelta, $puDelta);
                }
            }

            // 2. Hitung delta kampus level
            $newWeighed = max(0.0, (float) $summary->total_weighed_kg + $weighedDelta);
            $newSold = max(0.0, (float) $summary->total_sold_kg + $soldDelta);
            $newProcessed = max(0.0, (float) $summary->total_processed_kg + $processedDelta);
            $newProcessedOutput = max(0.0, (float) $summary->total_processed_output_kg + $processedOutputDelta);
            $newPickedUp = max(0.0, (float) $summary->total_picked_up_kg + $pickedUpDelta);
            $newCompost = max(0.0, (float) $summary->total_compost_kg + $compostDelta);
            $newFeed = max(0.0, (float) $summary->total_feed_kg + $feedDelta);
            $newCurrentStock = max(0.0, $newWeighed - $newSold - $newProcessed - $newPickedUp);
            $newSalesRevenue = max(0.0, (float) $summary->total_sales_revenue + $salesRevenueDelta);
            $newPickupCost = max(0.0, (float) $summary->total_pickup_cost + $pickupCostDelta);

            // 3. Hitung ulang sellable_stock, organic_stock, residual_stock, dan potential_revenue dari waste_type_stock_summaries
            $typeRecords = WasteTypeStockSummary::join('waste_types', 'waste_type_stock_summaries.waste_type_id', '=', 'waste_types.id')
                ->where('waste_type_stock_summaries.campus_id', $campusId)
                ->select(
                    'waste_types.category',
                    'waste_types.is_sellable',
                    'waste_type_stock_summaries.total_picked_up_kg',
                    'waste_type_stock_summaries.available_stock_kg',
                    'waste_type_stock_summaries.potential_revenue'
                )
                ->get();

            $sellableStock = 0.0;
            $organicStock = 0.0;
            $residualStock = 0.0;
            $potentialRevenue = 0.0;
            $sumPickedUpInTypes = 0.0;

            foreach ($typeRecords as $rec) {
                $sumPickedUpInTypes += (float) $rec->total_picked_up_kg;
                if ($rec->is_sellable) {
                    $sellableStock += (float) $rec->available_stock_kg;
                    $potentialRevenue += (float) $rec->potential_revenue;
                } elseif (strtolower(trim((string) $rec->category)) === 'organik') {
                    $organicStock += (float) $rec->available_stock_kg;
                } else {
                    $residualStock += (float) $rec->available_stock_kg;
                }
            }

            $unallocatedPickedUp = max(0.0, $newPickedUp - $sumPickedUpInTypes);
            $residualStock = max(0.0, $residualStock - $unallocatedPickedUp);

            $summary->update([
                'total_weighed_kg' => $newWeighed,
                'total_sold_kg' => $newSold,
                'total_processed_kg' => $newProcessed,
                'total_processed_output_kg' => $newProcessedOutput,
                'total_picked_up_kg' => $newPickedUp,
                'current_stock_kg' => $newCurrentStock,
                'total_sales_revenue' => $newSalesRevenue,
                'total_pickup_cost' => $newPickupCost,
                'sellable_stock_kg' => $sellableStock,
                'organic_stock_kg' => $organicStock,
                'total_compost_kg' => $newCompost,
                'total_feed_kg' => $newFeed,
                'residual_stock_kg' => $residualStock,
                'potential_revenue' => $potentialRevenue,
                'last_updated_by' => $userId ?? auth()->id(),
            ]);

            // Catat log mutasi riwayat perubahan stok
            $netStockDelta = $weighedDelta - $soldDelta - $processedDelta - $pickedUpDelta;

            WasteStockMutation::create([
                'campus_id' => $campusId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'action' => $action,
                'weight_delta_kg' => $netStockDelta,
                'total_weighed_after' => $newWeighed,
                'total_sold_after' => $newSold,
                'total_processed_after' => $newProcessed,
                'total_picked_up_after' => $newPickedUp,
                'current_stock_after' => $newCurrentStock,
                'notes' => $notes,
                'performed_by' => $userId ?? auth()->id(),
            ]);

            return $summary;
        });
    }

    /**
     * Menghitung ulang (resinkronisasi) data rekapitulasi stok dari transaksi historis unit kampus.
     */
    public static function recalculateForCampus(int $campusId, ?int $userId = null): self
    {
        return DB::transaction(function () use ($campusId, $userId) {
            $activeWasteTypes = WasteType::where('is_active', true)->get();

            // 1. Total timbangan masuk per waste_type
            $weighedByType = WeighingItem::query()
                ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
                ->where('weighing_sessions.campus_id', $campusId)
                ->select('weighing_items.waste_type_id', DB::raw('SUM(weighing_items.weight_kg) as total_kg'))
                ->groupBy('weighing_items.waste_type_id')
                ->pluck('total_kg', 'waste_type_id')
                ->toArray();

            // 2. Total penjualan keluar per waste_type
            $soldByType = SaleItem::query()
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->where('sales.campus_id', $campusId)
                ->select('sale_items.waste_type_id', DB::raw('SUM(sale_items.weight_kg) as total_kg'))
                ->groupBy('sale_items.waste_type_id')
                ->pluck('total_kg', 'waste_type_id')
                ->toArray();

            // 3. Total pengolahan organik mandiri per waste_type & akumulasi hasil olah (pupuk + pakan)
            $processedByType = OrganicProcessingItem::query()
                ->join('organic_processings', 'organic_processing_items.organic_processing_id', '=', 'organic_processings.id')
                ->where('organic_processings.campus_id', $campusId)
                ->select('organic_processing_items.waste_type_id', DB::raw('SUM(organic_processing_items.weight_kg) as total_kg'))
                ->groupBy('organic_processing_items.waste_type_id')
                ->pluck('total_kg', 'waste_type_id')
                ->toArray();

            $processingTotals = OrganicProcessing::where('campus_id', $campusId)
                ->selectRaw('COALESCE(SUM(input_weight_kg), 0) as total_input, COALESCE(SUM(output_weight_kg), 0) as total_output, COALESCE(SUM(compost_output_kg), 0) as total_compost, COALESCE(SUM(feed_output_kg), 0) as total_feed')
                ->first();
            $totalProcessed = (float) ($processingTotals->total_input ?? 0.0);
            $totalProcessedOutput = (float) ($processingTotals->total_output ?? 0.0);
            $totalCompost = (float) ($processingTotals->total_compost ?? 0.0);
            $totalFeed = (float) ($processingTotals->total_feed ?? 0.0);

            // 4. Total pengangkutan residu keluar per waste_type & biaya
            $pickedUpByType = PickupItem::query()
                ->join('pickups', 'pickup_items.pickup_id', '=', 'pickups.id')
                ->where('pickups.campus_id', $campusId)
                ->select('pickup_items.waste_type_id', DB::raw('SUM(pickup_items.weight_kg) as total_kg'))
                ->groupBy('pickup_items.waste_type_id')
                ->pluck('total_kg', 'waste_type_id')
                ->toArray();

            $totalPickedUp = (float) Pickup::where('campus_id', $campusId)->sum('volume_kg');
            $totalPickupCost = (float) Pickup::where('campus_id', $campusId)->sum('total_cost');

            // 5. Total pendapatan penjualan
            $totalSalesRevenue = (float) Sale::where('campus_id', $campusId)->sum('total_amount');

            // 6. Agregasi per jenis sampah & simpan ke waste_type_stock_summaries
            $totalWeighed = 0.0;
            $totalSold = 0.0;
            $sellableStock = 0.0;
            $organicStock = 0.0;
            $residualStock = 0.0;
            $potentialRevenue = 0.0;
            $sumPickedUpInTypes = 0.0;

            foreach ($activeWasteTypes as $type) {
                $weighed = (float) ($weighedByType[$type->id] ?? 0.0);
                $sold = (float) ($soldByType[$type->id] ?? 0.0);
                $processed = (float) ($processedByType[$type->id] ?? 0.0);
                $pickedUp = (float) ($pickedUpByType[$type->id] ?? 0.0);
                $available = max(0.0, $weighed - $sold - $processed - $pickedUp);

                $totalWeighed += $weighed;
                $totalSold += $sold;
                $sumPickedUpInTypes += $pickedUp;

                $typePotential = 0.0;
                if ($type->is_sellable) {
                    $sellableStock += $available;
                    $typePotential = round($available * (float) $type->default_price_per_kg, 2);
                    $potentialRevenue += $typePotential;
                } elseif (strtolower(trim((string) $type->category)) === 'organik') {
                    $organicStock += $available;
                } else {
                    $residualStock += $available;
                }

                WasteTypeStockSummary::updateOrCreate(
                    [
                        'campus_id' => $campusId,
                        'waste_type_id' => $type->id,
                    ],
                    [
                        'total_weighed_kg' => $weighed,
                        'total_sold_kg' => $sold,
                        'total_processed_kg' => $processed,
                        'total_picked_up_kg' => $pickedUp,
                        'available_stock_kg' => $available,
                        'potential_revenue' => $typePotential,
                    ]
                );
            }

            $unallocatedPickedUp = max(0.0, $totalPickedUp - $sumPickedUpInTypes);
            $residualStock = max(0.0, $residualStock - $unallocatedPickedUp);
            $currentStock = max(0.0, $totalWeighed - $totalSold - $totalProcessed - $totalPickedUp);

            $summary = static::updateOrCreate(
                ['campus_id' => $campusId],
                [
                    'total_weighed_kg' => $totalWeighed,
                    'total_sold_kg' => $totalSold,
                    'total_processed_kg' => $totalProcessed,
                    'total_processed_output_kg' => $totalProcessedOutput,
                    'total_picked_up_kg' => $totalPickedUp,
                    'current_stock_kg' => $currentStock,
                    'total_sales_revenue' => $totalSalesRevenue,
                    'total_pickup_cost' => $totalPickupCost,
                    'sellable_stock_kg' => $sellableStock,
                    'organic_stock_kg' => $organicStock,
                    'total_compost_kg' => $totalCompost,
                    'total_feed_kg' => $totalFeed,
                    'residual_stock_kg' => $residualStock,
                    'potential_revenue' => $potentialRevenue,
                    'last_updated_by' => $userId ?? auth()->id(),
                ]
            );

            WasteStockMutation::create([
                'campus_id' => $campusId,
                'reference_type' => 'adjustment',
                'reference_id' => null,
                'action' => 'sync',
                'weight_delta_kg' => 0.00,
                'total_weighed_after' => $totalWeighed,
                'total_sold_after' => $totalSold,
                'total_processed_after' => $totalProcessed,
                'total_picked_up_after' => $totalPickedUp,
                'current_stock_after' => $currentStock,
                'notes' => 'Inisialisasi / Sinkronisasi Ulang Rekap Stok Sampah',
                'performed_by' => $userId ?? auth()->id(),
            ]);

            return $summary;
        });
    }

    /**
     * Resinkronisasi seluruh unit kampus yang terdaftar di sistem.
     */
    public static function syncAllCampuses(?int $userId = null): void
    {
        $campuses = Campus::all();
        foreach ($campuses as $campus) {
            static::recalculateForCampus($campus->id, $userId);
        }
    }
}
