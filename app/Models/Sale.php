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
}

