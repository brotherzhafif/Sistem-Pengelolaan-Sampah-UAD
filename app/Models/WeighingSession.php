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
}

