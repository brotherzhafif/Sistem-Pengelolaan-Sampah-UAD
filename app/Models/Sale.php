<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'buyer_id',
        'sale_date',
        'total_amount',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function financialTransaction()
    {
        return $this->hasOne(FinancialTransaction::class, 'ref_id')->where('source', 'sales');
    }

    public function getTotalWeightAttribute(): float
    {
        return (float) $this->items->sum('weight_kg');
    }

    /**
     * Mengambil informasi keterikatan transaksi penjualan dengan Buku Kas Keuangan.
     */
    public function getBindingInfo(): array
    {
        $ft = FinancialTransaction::where('source', 'sales')->where('ref_id', $this->id)->first();
        $features = $ft ? ['Buku Kas Keuangan'] : [];
        $primaryUrl = $ft ? route('finance', ['view' => $ft->id]) : null;
        $primaryLabel = 'Lihat Buku Kas';

        return [
            'is_bound' => $ft !== null,
            'features' => $features,
            'primary_url' => $primaryUrl,
            'primary_label' => $primaryLabel,
            'financial_transaction' => $ft,
            'buyer' => $this->buyer,
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
     * Mengambil 5 riwayat penjualan terbaru.
     */
    public static function getRecentSales(?int $campusId, int $limit = 5)
    {
        return static::with(['campus', 'buyer', 'creator'])
            ->forCampus($campusId)
            ->orderBy('sale_date', 'desc')
            ->orderBy('id', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Mengambil daftar data transaksi penjualan terpaginasi dengan filter.
     */
    public static function getFilteredList(?int $campusId, ?string $dateFrom, ?string $dateTo, int $perPage = 8)
    {
        return static::with(['campus', 'buyer', 'creator', 'items.wasteType'])
            ->forCampus($campusId)
            ->when($dateFrom, fn($q) => $q->whereDate('sale_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('sale_date', '<=', $dateTo))
            ->orderBy('sale_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Menghitung total nilai rupiah penjualan terfilter.
     */
    public static function getTotalRevenue(?int $campusId, ?string $dateFrom, ?string $dateTo): float
    {
        return (float) static::forCampus($campusId)
            ->when($dateFrom, fn($q) => $q->whereDate('sale_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('sale_date', '<=', $dateTo))
            ->sum('total_amount');
    }

    /**
     * Menghitung total bobot (kg) sampah terjual terfilter.
     */
    public static function getTotalSoldWeight(?int $campusId, ?string $dateFrom, ?string $dateTo): float
    {
        return (float) SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->when($campusId, fn($q) => $q->where('sales.campus_id', $campusId))
            ->when($dateFrom, fn($q) => $q->whereDate('sales.sale_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('sales.sale_date', '<=', $dateTo))
            ->sum('sale_items.weight_kg');
    }

    /**
     * Mengambil detail transaksi penjualan dengan item dan jenis sampah.
     */
    public static function getDetailedSale(int $id): ?self
    {
        return static::with(['campus', 'buyer', 'creator', 'items.wasteType'])->find($id);
    }

    /**
     * The "booted" method of the model.
     * Auto-record Kredit (K) entry to Buku Kas & recalculate ledger on create/delete.
     */
    protected static function booted(): void
    {
        static::created(function (Sale $sale) {
            if ($sale->total_amount > 0) {
                $buyerName = $sale->buyer?->name ?? 'Mitra Pengepul';
                Keuangan::recordTransaction(
                    campusId: $sale->campus_id,
                    date: $sale->sale_date,
                    jenis: 'K', // Kredit = Pemasukan
                    sumber: 'penjualan',
                    nominal: (float) $sale->total_amount,
                    keterangan: 'Penjualan sampah terpilah kepada ' . $buyerName,
                    refId: $sale->id,
                    createdBy: $sale->created_by
                );
            }
        });

        static::deleted(function (Sale $sale) {
            Keuangan::deleteTransactionByRef('penjualan', $sale->id);
        });
    }
}

