<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class WasteTypeStockSummary extends Model
{
    use HasFactory;

    protected $table = 'waste_type_stock_summaries';

    protected $fillable = [
        'campus_id',
        'waste_type_id',
        'total_weighed_kg',
        'total_sold_kg',
        'available_stock_kg',
        'potential_revenue',
    ];

    protected $casts = [
        'total_weighed_kg' => 'decimal:2',
        'total_sold_kg' => 'decimal:2',
        'available_stock_kg' => 'decimal:2',
        'potential_revenue' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }

    /**
     * Mengambil stok tersedia (O(1)) untuk jenis sampah tertentu di kampus tertentu.
     * Tidak lagi menjalankan query SUM() ke tabel transaksi.
     */
    public static function getAvailableStock(int $wasteTypeId, ?int $campusId = null): float
    {
        if ($campusId) {
            $summary = static::where('campus_id', $campusId)
                ->where('waste_type_id', $wasteTypeId)
                ->first();

            return (float) ($summary?->available_stock_kg ?? 0.0);
        }

        // Jika global (seluruh kampus)
        return (float) static::where('waste_type_id', $wasteTypeId)->sum('available_stock_kg');
    }

    /**
     * Memperbarui stok per jenis sampah untuk unit kampus secara atomic.
     */
    public static function updateStock(
        int $campusId,
        int $wasteTypeId,
        float $weighedDelta = 0.0,
        float $soldDelta = 0.0
    ): self {
        return DB::transaction(function () use ($campusId, $wasteTypeId, $weighedDelta, $soldDelta) {
            $record = static::where('campus_id', $campusId)
                ->where('waste_type_id', $wasteTypeId)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                $record = static::create([
                    'campus_id' => $campusId,
                    'waste_type_id' => $wasteTypeId,
                    'total_weighed_kg' => 0.00,
                    'total_sold_kg' => 0.00,
                    'available_stock_kg' => 0.00,
                    'potential_revenue' => 0.00,
                ]);
            }

            $newWeighed = max(0.0, (float) $record->total_weighed_kg + $weighedDelta);
            $newSold = max(0.0, (float) $record->total_sold_kg + $soldDelta);
            $available = max(0.0, $newWeighed - $newSold);

            $wasteType = WasteType::find($wasteTypeId);
            $potential = 0.00;
            if ($wasteType && $wasteType->is_sellable) {
                $potential = round($available * (float) $wasteType->default_price_per_kg, 2);
            }

            $record->update([
                'total_weighed_kg' => $newWeighed,
                'total_sold_kg' => $newSold,
                'available_stock_kg' => $available,
                'potential_revenue' => $potential,
            ]);

            return $record;
        });
    }
}
