<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganicProcessing extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'process_date',
        'input_weight_kg',
        'compost_output_kg',
        'feed_output_kg',
        'method',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'process_date' => 'date',
        'input_weight_kg' => 'decimal:2',
        'compost_output_kg' => 'decimal:2',
        'feed_output_kg' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrganicProcessingItem::class);
    }

    public function scopeForCampus($query, ?int $campusId)
    {
        return $query->when($campusId, fn ($q) => $q->where('campus_id', $campusId));
    }

    /**
     * Mengambil daftar riwayat pengolahan sampah organik terpaginasi dengan filter.
     */
    public static function getFilteredList(?int $campusId, ?string $dateFrom, ?string $dateTo, int $perPage = 10)
    {
        return static::with(['campus', 'creator', 'items.wasteType'])
            ->forCampus($campusId)
            ->when($dateFrom, fn ($q) => $q->whereDate('process_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('process_date', '<=', $dateTo))
            ->orderBy('process_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Mengambil detail transaksi pengolahan organik.
     */
    public static function getDetailedProcessing(int $id): ?self
    {
        return static::with(['campus', 'creator', 'items.wasteType'])->find($id);
    }

    /**
     * Mengambil akumulasi total input organik dan hasil olah (pupuk & pakan) terfilter.
     */
    public static function getTotals(?int $campusId, ?string $dateFrom, ?string $dateTo): array
    {
        if (empty($dateFrom) && empty($dateTo)) {
            $metrics = WasteStockSummary::getMetrics($campusId);
            return [
                'total_input_kg' => (float) ($metrics['total_processed_kg'] ?? 0.0),
                'total_compost_kg' => (float) ($metrics['total_compost_kg'] ?? 0.0),
                'total_feed_kg' => (float) ($metrics['total_feed_kg'] ?? 0.0),
            ];
        }

        $row = static::forCampus($campusId)
            ->when($dateFrom, fn ($q) => $q->whereDate('process_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('process_date', '<=', $dateTo))
            ->selectRaw('COALESCE(SUM(input_weight_kg), 0) as input_kg, COALESCE(SUM(compost_output_kg), 0) as compost_kg, COALESCE(SUM(feed_output_kg), 0) as feed_kg')
            ->first();

        return [
            'total_input_kg' => (float) ($row->input_kg ?? 0.0),
            'total_compost_kg' => (float) ($row->compost_kg ?? 0.0),
            'total_feed_kg' => (float) ($row->feed_kg ?? 0.0),
        ];
    }
}
