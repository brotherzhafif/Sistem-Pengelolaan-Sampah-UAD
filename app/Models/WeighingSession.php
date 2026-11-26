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
    public static function getFilteredList(?int $campusId, ?string $dateFrom, ?string $dateTo, int $perPage = 10)
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
     * Menghitung status keterikatan data penimbangan dengan transaksi penjualan/pengangkutan:
     * Jika ada sebagian atau seluruh bobot yang sudah terjual/diangkut, maka sesi tidak boleh dihapus.
     */
    public function getDeductionSummary(): array
    {
        $campusId = $this->campus_id;
        $items = $this->items()->with('wasteType')->get();

        $totalSessionWeight = 0.0;
        $totalRetained = 0.0;
        $features = [];

        foreach ($items as $item) {
            $weight = (float) $item->weight_kg;
            $totalSessionWeight += $weight;
            $wasteType = $item->wasteType;
            $isSellable = (bool) ($wasteType?->is_sellable ?? false);
            $isOrganic = !$isSellable && strtolower(trim((string) ($wasteType?->category ?? ''))) === 'organik';

            $available = WasteType::getAvailableStock($item->waste_type_id, $campusId);

            // Jumlah yang bisa dipotong/dihapus berdasarkan sisa stok jenis sampah tersebut
            $deductible = min($weight, max(0.0, $available));
            // Jumlah yang sudah terikat transaksi penjualan / pengolahan organik / pengangkutan
            $retained = max(0.0, $weight - $deductible);

            if ($retained > 0.0001) {
                if ($isSellable) {
                    $features['Penjualan Sampah'] = true;
                } elseif ($isOrganic) {
                    $features['Pengolahan Organik'] = true;
                } else {
                    $features['Pengangkutan Residu'] = true;
                }
            }

            $totalRetained += $retained;
        }

        $featuresList = array_keys($features);
        $canFullyDelete = $totalRetained <= 0.0001;

        $primaryUrl = null;
        $primaryLabel = null;
        if (in_array('Penjualan Sampah', $featuresList)) {
            $primaryUrl = route('sales');
            $primaryLabel = 'Lihat Penjualan';
        } elseif (in_array('Pengolahan Organik', $featuresList)) {
            $primaryUrl = route('processing');
            $primaryLabel = 'Lihat Pengolahan Organik';
        } elseif (in_array('Pengangkutan Residu', $featuresList)) {
            $primaryUrl = route('pickups');
            $primaryLabel = 'Lihat Pengangkutan';
        }

        return [
            'total_session_weight' => $totalSessionWeight,
            'total_retained' => $totalRetained,
            'can_fully_delete' => $canFullyDelete,
            'is_fully_locked' => !$canFullyDelete,
            'features' => $featuresList,
            'primary_url' => $primaryUrl,
            'primary_label' => $primaryLabel,
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

