<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class OrganicProcessing extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'process_date',
        'input_weight_kg',
        'organic_output_category_id',
        'output_weight_kg',
        'compost_output_kg',
        'feed_output_kg',
        'method',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'process_date' => 'date',
        'input_weight_kg' => 'decimal:2',
        'output_weight_kg' => 'decimal:2',
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

    public function outputCategory(): BelongsTo
    {
        return $this->belongsTo(OrganicOutputCategory::class, 'organic_output_category_id');
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
        return static::with(['campus', 'creator', 'outputCategory', 'items.wasteType'])
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
        return static::with(['campus', 'creator', 'outputCategory', 'items.wasteType'])->find($id);
    }

    /**
     * Mengambil akumulasi total input organik dan hasil olah per kategori Master Data.
     */
    public static function getTotals(?int $campusId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $baseQuery = static::forCampus($campusId)
            ->when($dateFrom, fn ($q) => $q->whereDate('process_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('process_date', '<=', $dateTo));

        $row = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(input_weight_kg), 0) as input_kg, COALESCE(SUM(output_weight_kg), 0) as output_kg')
            ->first();

        $byCategory = static::query()
            ->join('organic_output_categories', 'organic_processings.organic_output_category_id', '=', 'organic_output_categories.id')
            ->when($campusId, fn ($q) => $q->where('organic_processings.campus_id', $campusId))
            ->when($dateFrom, fn ($q) => $q->whereDate('organic_processings.process_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('organic_processings.process_date', '<=', $dateTo))
            ->groupBy('organic_output_categories.id', 'organic_output_categories.name')
            ->select(
                'organic_output_categories.id as category_id',
                'organic_output_categories.name as category_name',
                DB::raw('COALESCE(SUM(organic_processings.output_weight_kg), 0) as total_output_kg')
            )
            ->orderByDesc('total_output_kg')
            ->get()
            ->map(fn ($r) => [
                'category_id' => (int) $r->category_id,
                'category_name' => $r->category_name,
                'total_output_kg' => (float) $r->total_output_kg,
            ])
            ->toArray();

        return [
            'total_input_kg' => (float) ($row->input_kg ?? 0.0),
            'total_output_kg' => (float) ($row->output_kg ?? 0.0),
            'by_category' => $byCategory,
        ];
    }
}
