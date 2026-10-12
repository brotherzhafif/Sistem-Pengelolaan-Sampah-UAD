<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pickup extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'vendor_id',
        'pickup_date',
        'volume_kg',
        'cost_per_kg',
        'total_cost',
        'driver_name',
        'vehicle_plate',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'volume_kg' => 'decimal:2',
        'cost_per_kg' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query berdasarkan ID Kampus.
     */
    public function scopeForCampus($query, ?int $campusId)
    {
        return $query->when($campusId, fn($q) => $q->where('campus_id', $campusId));
    }

    /**
     * Mengambil 5 riwayat pengangkutan residu terbaru.
     */
    public static function getRecentPickups(?int $campusId, int $limit = 5)
    {
        return static::with(['campus', 'vendor', 'creator'])
            ->forCampus($campusId)
            ->orderBy('pickup_date', 'desc')
            ->orderBy('id', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Mengambil daftar data transaksi pengangkutan terpaginasi dengan filter.
     */
    public static function getFilteredList(?int $campusId, ?string $dateFrom, ?string $dateTo, int $perPage = 8)
    {
        return static::with(['campus', 'vendor', 'creator'])
            ->forCampus($campusId)
            ->when($dateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('pickup_date', '<=', $dateTo))
            ->orderBy('pickup_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Menghitung total biaya pengangkutan residu terfilter.
     */
    public static function getTotalCost(?int $campusId, ?string $dateFrom, ?string $dateTo): float
    {
        return (float) static::forCampus($campusId)
            ->when($dateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('pickup_date', '<=', $dateTo))
            ->sum('total_cost');
    }

    /**
     * Menghitung total bobot volume pengangkutan residu terfilter.
     */
    public static function getTotalVolume(?int $campusId, ?string $dateFrom, ?string $dateTo): float
    {
        return (float) static::forCampus($campusId)
            ->when($dateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('pickup_date', '<=', $dateTo))
            ->sum('volume_kg');
    }

    /**
     * Mengambil detail transaksi pengangkutan.
     */
    public static function getDetailedPickup(int $id): ?self
    {
        return static::with(['campus', 'vendor', 'creator'])->find($id);
    }
}
