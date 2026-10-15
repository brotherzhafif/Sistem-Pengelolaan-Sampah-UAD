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

    public function financialTransaction()
    {
        return $this->hasOne(FinancialTransaction::class, 'ref_id')->where('source', 'pickup');
    }

    /**
     * Mengambil informasi keterikatan transaksi pengangkutan residu dengan Buku Kas Keuangan.
     */
    public function getBindingInfo(): array
    {
        $ft = FinancialTransaction::where('source', 'pickup')->where('ref_id', $this->id)->first();
        $features = $ft ? ['Buku Kas Keuangan'] : [];
        $primaryUrl = $ft ? route('finance', ['view' => $ft->id]) : null;
        $primaryLabel = 'Lihat Buku Kas';

        return [
            'is_bound' => $ft !== null,
            'features' => $features,
            'primary_url' => $primaryUrl,
            'primary_label' => $primaryLabel,
            'financial_transaction' => $ft,
            'vendor' => $this->vendor,
            'reason' => 'Buku Kas Keuangan',
            'reasons' => $features,
            'bindings' => $primaryUrl ? [['url' => $primaryUrl, 'label' => $primaryLabel]] : [],
        ];
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

    /**
     * The "booted" method of the model.
     * Auto-record Debet (D) entry to Buku Kas & recalculate daily ledger on create/delete.
     */
    protected static function booted(): void
    {
        static::created(function (Pickup $pickup) {
            if ($pickup->total_cost > 0) {
                $vendorName = $pickup->vendor?->name ?? 'Mitra Pengangkut';
                Keuangan::recordTransaction(
                    campusId: $pickup->campus_id,
                    date: $pickup->pickup_date,
                    jenis: 'D', // Debet = Pengeluaran / Biaya
                    sumber: 'pengangkutan',
                    nominal: (float) $pickup->total_cost,
                    keterangan: 'Biaya pengangkutan residu sampah oleh ' . $vendorName . ' (' . number_format($pickup->volume_kg, 1, ',', '.') . ' kg)',
                    refId: $pickup->id,
                    createdBy: $pickup->created_by
                );
            }
        });

        static::deleted(function (Pickup $pickup) {
            Keuangan::deleteTransactionByRef('pengangkutan', $pickup->id);
        });
    }
}
