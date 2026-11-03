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
        'total_picked_up_kg',
        'current_stock_kg',
        'total_sales_revenue',
        'total_pickup_cost',
        'sellable_stock_kg',
        'residual_stock_kg',
        'potential_revenue',
        'last_updated_by',
    ];

    protected $casts = [
        'total_weighed_kg' => 'decimal:2',
        'total_sold_kg' => 'decimal:2',
        'total_picked_up_kg' => 'decimal:2',
        'current_stock_kg' => 'decimal:2',
        'total_sales_revenue' => 'decimal:2',
        'total_pickup_cost' => 'decimal:2',
        'sellable_stock_kg' => 'decimal:2',
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
                    'total_picked_up_kg' => 0.00,
                    'current_stock_kg' => 0.00,
                    'total_sales_revenue' => 0.00,
                    'total_pickup_cost' => 0.00,
                    'sellable_stock_kg' => 0.00,
                    'residual_stock_kg' => 0.00,
                    'potential_revenue' => 0.00,
                ]
            );

            return [
                'total_weighed_kg' => (float) $summary->total_weighed_kg,
                'total_sold_kg' => (float) $summary->total_sold_kg,
                'total_picked_up_kg' => (float) $summary->total_picked_up_kg,
                'current_stock_kg' => (float) $summary->current_stock_kg,
                'total_sales_revenue' => (float) $summary->total_sales_revenue,
                'total_pickup_cost' => (float) $summary->total_pickup_cost,
                'sellable_stock_kg' => (float) $summary->sellable_stock_kg,
                'residual_stock_kg' => (float) $summary->residual_stock_kg,
                'potential_revenue' => (float) $summary->potential_revenue,
                'last_updated_at' => $summary->updated_at,
            ];
        }

        // Jika null (Super Admin / Global view), agregasi dari tabel summary kampus
        $totals = static::selectRaw('
            COALESCE(SUM(total_weighed_kg), 0) as total_weighed,
            COALESCE(SUM(total_sold_kg), 0) as total_sold,
            COALESCE(SUM(total_picked_up_kg), 0) as total_picked_up,
            COALESCE(SUM(current_stock_kg), 0) as current_stock,
            COALESCE(SUM(total_sales_revenue), 0) as total_revenue,
            COALESCE(SUM(total_pickup_cost), 0) as total_cost,
            COALESCE(SUM(sellable_stock_kg), 0) as sellable_stock,
            COALESCE(SUM(residual_stock_kg), 0) as residual_stock,
            COALESCE(SUM(potential_revenue), 0) as pot_revenue
        ')->first();

        return [
            'total_weighed_kg' => (float) ($totals->total_weighed ?? 0.0),
            'total_sold_kg' => (float) ($totals->total_sold ?? 0.0),
            'total_picked_up_kg' => (float) ($totals->total_picked_up ?? 0.0),
            'current_stock_kg' => (float) ($totals->current_stock ?? 0.0),
            'total_sales_revenue' => (float) ($totals->total_revenue ?? 0.0),
            'total_pickup_cost' => (float) ($totals->total_cost ?? 0.0),
            'sellable_stock_kg' => (float) ($totals->sellable_stock ?? 0.0),
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
        ?int $userId = null
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
            $userId
        ) {
            $summary = static::where('campus_id', $campusId)->lockForUpdate()->first();

            if (!$summary) {
                $summary = static::create([
                    'campus_id' => $campusId,
                    'total_weighed_kg' => 0.00,
                    'total_sold_kg' => 0.00,
                    'total_picked_up_kg' => 0.00,
                    'current_stock_kg' => 0.00,
                    'total_sales_revenue' => 0.00,
                    'total_pickup_cost' => 0.00,
                    'sellable_stock_kg' => 0.00,
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
                    WasteTypeStockSummary::updateStock($campusId, $wTypeId, $wDelta, $sDelta);
                }
            }

            // 2. Hitung delta kampus level
            $newWeighed = max(0.0, (float) $summary->total_weighed_kg + $weighedDelta);
            $newSold = max(0.0, (float) $summary->total_sold_kg + $soldDelta);
            $newPickedUp = max(0.0, (float) $summary->total_picked_up_kg + $pickedUpDelta);
            $newCurrentStock = max(0.0, $newWeighed - $newSold - $newPickedUp);
            $newSalesRevenue = max(0.0, (float) $summary->total_sales_revenue + $salesRevenueDelta);
            $newPickupCost = max(0.0, (float) $summary->total_pickup_cost + $pickupCostDelta);

            // 3. Hitung ulang sellable_stock, residual_stock, dan potential_revenue dari waste_type_stock_summaries (hanya ~10 baris jenis sampah!)
            $typeRecords = WasteTypeStockSummary::join('waste_types', 'waste_type_stock_summaries.waste_type_id', '=', 'waste_types.id')
                ->where('waste_type_stock_summaries.campus_id', $campusId)
                ->select(
                    'waste_types.is_sellable',
                    'waste_type_stock_summaries.total_weighed_kg',
                    'waste_type_stock_summaries.available_stock_kg',
                    'waste_type_stock_summaries.potential_revenue'
                )
                ->get();

            $sellableStock = 0.0;
            $grossResidual = 0.0;
            $potentialRevenue = 0.0;

            foreach ($typeRecords as $rec) {
                if ($rec->is_sellable) {
                    $sellableStock += (float) $rec->available_stock_kg;
                    $potentialRevenue += (float) $rec->potential_revenue;
                } else {
                    $grossResidual += (float) $rec->total_weighed_kg;
                }
            }

            $residualStock = max(0.0, $grossResidual - $newPickedUp);

            $summary->update([
                'total_weighed_kg' => $newWeighed,
                'total_sold_kg' => $newSold,
                'total_picked_up_kg' => $newPickedUp,
                'current_stock_kg' => $newCurrentStock,
                'total_sales_revenue' => $newSalesRevenue,
                'total_pickup_cost' => $newPickupCost,
                'sellable_stock_kg' => $sellableStock,
                'residual_stock_kg' => $residualStock,
                'potential_revenue' => $potentialRevenue,
                'last_updated_by' => $userId ?? auth()->id(),
            ]);

            // Catat log mutasi riwayat perubahan stok
            $netStockDelta = $weighedDelta - $soldDelta - $pickedUpDelta;

            WasteStockMutation::create([
                'campus_id' => $campusId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'action' => $action,
                'weight_delta_kg' => $netStockDelta,
                'total_weighed_after' => $newWeighed,
                'total_sold_after' => $newSold,
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

            // 3. Total pengangkutan residu keluar & biaya
            $totalPickedUp = (float) Pickup::where('campus_id', $campusId)->sum('volume_kg');
            $totalPickupCost = (float) Pickup::where('campus_id', $campusId)->sum('total_cost');

            // 4. Total pendapatan penjualan
            $totalSalesRevenue = (float) Sale::where('campus_id', $campusId)->sum('total_amount');

            // 5. Agregasi per jenis sampah & simpan ke waste_type_stock_summaries
            $totalWeighed = 0.0;
            $totalSold = 0.0;
            $sellableStock = 0.0;
            $grossResidualStock = 0.0;
            $potentialRevenue = 0.0;

            foreach ($activeWasteTypes as $type) {
                $weighed = (float) ($weighedByType[$type->id] ?? 0.0);
                $sold = (float) ($soldByType[$type->id] ?? 0.0);
                $available = max(0.0, $weighed - $sold);

                $totalWeighed += $weighed;
                $totalSold += $sold;

                $typePotential = 0.0;
                if ($type->is_sellable) {
                    $sellableStock += $available;
                    $typePotential = round($available * (float) $type->default_price_per_kg, 2);
                    $potentialRevenue += $typePotential;
                } else {
                    $grossResidualStock += $available;
                }

                WasteTypeStockSummary::updateOrCreate(
                    [
                        'campus_id' => $campusId,
                        'waste_type_id' => $type->id,
                    ],
                    [
                        'total_weighed_kg' => $weighed,
                        'total_sold_kg' => $sold,
                        'available_stock_kg' => $available,
                        'potential_revenue' => $typePotential,
                    ]
                );
            }

            $residualStock = max(0.0, $grossResidualStock - $totalPickedUp);
            $currentStock = max(0.0, $totalWeighed - $totalSold - $totalPickedUp);

            $summary = static::updateOrCreate(
                ['campus_id' => $campusId],
                [
                    'total_weighed_kg' => $totalWeighed,
                    'total_sold_kg' => $totalSold,
                    'total_picked_up_kg' => $totalPickedUp,
                    'current_stock_kg' => $currentStock,
                    'total_sales_revenue' => $totalSalesRevenue,
                    'total_pickup_cost' => $totalPickupCost,
                    'sellable_stock_kg' => $sellableStock,
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
