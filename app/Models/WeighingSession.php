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
     * Mengecek apakah data penimbangan ini terikat dengan transaksi penjualan atau pengangkutan.
     * Mengembalikan status keterikatan, daftar transaksi terkait (id, tanggal, link), dan alasan.
     */
    public function getBindingInfo(): array
    {
        $features = [];
        $primaryUrl = null;
        $primaryLabel = null;

        // Cek keterikatan dengan penjualan sampah bernilai
        $sellableTypeIds = $this->items
            ->filter(fn($i) => (float) $i->weight_kg > 0 && !empty($i->wasteType?->is_sellable))
            ->pluck('waste_type_id');

        if ($sellableTypeIds->isNotEmpty()) {
            $hasSales = Sale::where('campus_id', $this->campus_id)
                ->where('sale_date', '>=', $this->weigh_date)
                ->whereHas('items', fn($q) => $q->whereIn('waste_type_id', $sellableTypeIds))
                ->exists();

            if ($hasSales) {
                $features[] = 'Penjualan Sampah';
                $primaryUrl = route('sales');
                $primaryLabel = 'Lihat Penjualan';
            }
        }

        // Cek keterikatan dengan pengangkutan residu
        $hasResidual = $this->items->contains(fn($i) => (float) $i->weight_kg > 0 && !empty($i->wasteType) && !$i->wasteType->is_sellable);
        if ($hasResidual) {
            $hasPickups = Pickup::where('campus_id', $this->campus_id)
                ->where('pickup_date', '>=', $this->weigh_date)
                ->exists();

            if ($hasPickups) {
                $features[] = 'Pengangkutan Residu';
                if (!$primaryUrl) {
                    $primaryUrl = route('pickups');
                    $primaryLabel = 'Lihat Pengangkutan';
                }
            }
        }

        return [
            'is_bound' => !empty($features),
            'features' => $features,
            'primary_url' => $primaryUrl,
            'primary_label' => $primaryLabel,
            'reasons' => $features,
            'sales' => collect(),
            'pickups' => collect(),
            'bindings' => $primaryUrl ? [['url' => $primaryUrl, 'label' => $primaryLabel]] : [],
        ];
    }
}

