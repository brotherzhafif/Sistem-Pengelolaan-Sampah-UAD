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
        'last_updated_by',
    ];

    protected $casts = [
        'total_weighed_kg' => 'decimal:2',
        'total_sold_kg' => 'decimal:2',
        'total_picked_up_kg' => 'decimal:2',
        'current_stock_kg' => 'decimal:2',
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
                ]
            );

            return [
                'total_weighed_kg' => (float) $summary->total_weighed_kg,
                'total_sold_kg' => (float) $summary->total_sold_kg,
                'total_picked_up_kg' => (float) $summary->total_picked_up_kg,
                'current_stock_kg' => (float) $summary->current_stock_kg,
                'last_updated_at' => $summary->updated_at,
            ];
        }

        // Jika null (Super Admin / Global view), agregasi dari tabel summary kampus
        $totals = static::selectRaw('
            COALESCE(SUM(total_weighed_kg), 0) as total_weighed,
            COALESCE(SUM(total_sold_kg), 0) as total_sold,
            COALESCE(SUM(total_picked_up_kg), 0) as total_picked_up,
            COALESCE(SUM(current_stock_kg), 0) as current_stock
        ')->first();

        return [
            'total_weighed_kg' => (float) ($totals->total_weighed ?? 0.0),
            'total_sold_kg' => (float) ($totals->total_sold ?? 0.0),
            'total_picked_up_kg' => (float) ($totals->total_picked_up ?? 0.0),
            'current_stock_kg' => (float) ($totals->current_stock ?? 0.0),
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
                    'last_updated_by' => $userId ?? auth()->id(),
                ]);
            }

            $newWeighed = max(0.0, (float) $summary->total_weighed_kg + $weighedDelta);
            $newSold = max(0.0, (float) $summary->total_sold_kg + $soldDelta);
            $newPickedUp = max(0.0, (float) $summary->total_picked_up_kg + $pickedUpDelta);
            $newCurrentStock = max(0.0, $newWeighed - $newSold - $newPickedUp);

            $summary->update([
                'total_weighed_kg' => $newWeighed,
                'total_sold_kg' => $newSold,
                'total_picked_up_kg' => $newPickedUp,
                'current_stock_kg' => $newCurrentStock,
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
            // 1. Total timbangan masuk
            $totalWeighed = (float) WeighingItem::query()
                ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
                ->where('weighing_sessions.campus_id', $campusId)
                ->sum('weighing_items.weight_kg');

            // 2. Total penjualan keluar
            $totalSold = (float) SaleItem::query()
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->where('sales.campus_id', $campusId)
                ->sum('sale_items.weight_kg');

            // 3. Total pengangkutan residu keluar
            $totalPickedUp = (float) Pickup::query()
                ->where('campus_id', $campusId)
                ->sum('volume_kg');

            $currentStock = max(0.0, $totalWeighed - $totalSold - $totalPickedUp);

            $summary = static::updateOrCreate(
                ['campus_id' => $campusId],
                [
                    'total_weighed_kg' => $totalWeighed,
                    'total_sold_kg' => $totalSold,
                    'total_picked_up_kg' => $totalPickedUp,
                    'current_stock_kg' => $currentStock,
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
