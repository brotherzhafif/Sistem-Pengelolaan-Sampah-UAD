<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeighingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'waste_source_id',
        'weigh_date',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'weigh_date' => 'date',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function wasteSource(): BelongsTo
    {
        return $this->belongsTo(WasteSource::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WeighingItem::class);
    }

    public function getTotalWeightAttribute(): float
    {
        return (float) $this->items->sum('weight_kg');
    }

    /**
     * Scope query berdasarkan ID Kampus.
     */
    public function scopeForCampus($query, ?int $campusId)
    {
        return $query->when($campusId, fn($q) => $q->where('campus_id', $campusId));
    }

    /**
     * Mengambil 5 riwayat penimbangan terbaru.
     */
    public static function getRecentSessions(?int $campusId, int $limit = 5)
    {
        return static::with(['campus', 'wasteSource', 'creator', 'items'])
            ->forCampus($campusId)
            ->orderBy('weigh_date', 'desc')
            ->orderBy('id', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Mengambil daftar data sesi penimbangan terpaginasi.
     */
    public static function getFilteredList(?int $campusId, ?string $dateFrom, ?string $dateTo, int $perPage = 8)
    {
        return static::with(['campus', 'wasteSource', 'creator', 'items.wasteType'])
            ->forCampus($campusId)
            ->when($dateFrom, fn($q) => $q->whereDate('weigh_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('weigh_date', '<=', $dateTo))
            ->orderBy('weigh_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Mengambil detail sesi penimbangan dengan item dan jenis sampah.
     */
    public static function getDetailedSession(int $id): ?self
    {
        return static::with(['campus', 'wasteSource', 'creator', 'items.wasteType'])->find($id);
    }

    /**
     * Menghitung rincian penyesuaian penghapusan data penimbangan:
     * - Berapa bobot yang dapat dihapus/dipotong (belum terjual/diangkut)
     * - Berapa bobot yang harus dipertahankan (karena sudah tercatat pada transaksi penjualan/pengangkutan)
     */
    public function getDeductionSummary(): array
    {
        $campusId = $this->campus_id;
        $items = $this->items()->with('wasteType')->get();
        
        $totalSessionWeight = 0.0;
        $totalDeductible = 0.0;
        $totalRetained = 0.0;
        $itemsBreakdown = [];
        $features = [];

        // Hitung stok tersedia untuk residu di kampus ini
        $residualWeighed = (float) WeighingItem::join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
            ->where('weighing_sessions.campus_id', $campusId)
            ->whereHas('wasteType', fn($q) => $q->where('is_sellable', false))
            ->sum('weighing_items.weight_kg');
        $residualPickedUp = (float) Pickup::where('campus_id', $campusId)->sum('volume_kg');
        $availableResidual = max(0.0, $residualWeighed - $residualPickedUp);

        foreach ($items as $item) {
            $weight = (float) $item->weight_kg;
            $totalSessionWeight += $weight;
            $isSellable = (bool) ($item->wasteType?->is_sellable ?? false);

            if ($isSellable) {
                $available = WasteType::getAvailableStock($item->waste_type_id, $campusId);
            } else {
                $available = $availableResidual;
            }

            // Jumlah yang bisa dipotong (yang belum terjual / terangkut)
            $deductible = min($weight, max(0.0, $available));
            // Jumlah yang harus dipertahankan (sudah tercatat transaksi)
            $retained = max(0.0, $weight - $deductible);

            if ($retained > 0.0001) {
                if ($isSellable) {
                    $features['Penjualan Sampah'] = true;
                } else {
                    $features['Pengangkutan Residu'] = true;
                }
            }

            $totalDeductible += $deductible;
            $totalRetained += $retained;

            $itemsBreakdown[] = [
                'item_id' => $item->id,
                'waste_type_id' => $item->waste_type_id,
                'waste_type_name' => $item->wasteType?->name ?? 'Sampah',
                'current_weight' => $weight,
                'deductible_weight' => $deductible,
                'retained_weight' => $retained,
                'is_sellable' => $isSellable,
            ];
        }

        $featuresList = array_keys($features);

        return [
            'total_session_weight' => $totalSessionWeight,
            'total_deductible' => $totalDeductible,
            'total_retained' => $totalRetained,
            'can_fully_delete' => $totalRetained <= 0.0001,
            'can_partially_deduct' => $totalRetained > 0.0001 && $totalDeductible > 0.0001,
            'is_fully_locked' => $totalDeductible <= 0.0001 && $totalSessionWeight > 0.0001,
            'features' => $featuresList,
            'items_breakdown' => $itemsBreakdown,
            'primary_url' => in_array('Penjualan Sampah', $featuresList) ? route('sales') : (in_array('Pengangkutan Residu', $featuresList) ? route('pickups') : null),
            'primary_label' => in_array('Penjualan Sampah', $featuresList) ? 'Lihat Penjualan' : (in_array('Pengangkutan Residu', $featuresList) ? 'Lihat Pengangkutan' : null),
        ];
    }

    /**
     * Informasi keterikatan penimbangan dengan transaksi penjualan/pengangkutan.
     */
    public function getBindingInfo(): array
    {
        $summary = $this->getDeductionSummary();

        return array_merge($summary, [
            'is_bound' => !$summary['can_fully_delete'],
            'reasons' => $summary['features'],
            'bindings' => $summary['primary_url'] ? [['url' => $summary['primary_url'], 'label' => $summary['primary_label']]] : [],
        ]);
    }
}

