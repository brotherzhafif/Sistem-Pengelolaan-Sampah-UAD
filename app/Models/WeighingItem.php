<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeighingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'weighing_session_id',
        'waste_type_id',
        'weight_kg',
        'volume_m3',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
        'volume_m3' => 'decimal:3',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WeighingSession::class, 'weighing_session_id');
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }

    /**
     * Menghitung total timbangan sampah pada hari tertentu (kg).
     */
    public static function getTodayWeighedKg(?int $campusId, ?string $date = null): float
    {
        $date = $date ?? \Carbon\Carbon::today()->format('Y-m-d');

        return (float) static::query()
            ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
            ->whereDate('weighing_sessions.weigh_date', $date)
            ->when($campusId, fn($q) => $q->where('weighing_sessions.campus_id', $campusId))
            ->sum('weighing_items.weight_kg');
    }

    /**
     * Menghitung total akumulasi seluruh timbangan (kg) & volume (m3).
     */
    public static function getTotalAccumulation(?int $campusId): array
    {
        $query = static::query()
            ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
            ->when($campusId, fn($q) => $q->where('weighing_sessions.campus_id', $campusId));

        return [
            'totalKg' => (float) (clone $query)->sum('weighing_items.weight_kg'),
            'totalVolumeM3' => (float) (clone $query)->sum('weighing_items.volume_m3'),
        ];
    }

    /**
     * Menghitung statistik komposisi sampah (Organik, Anorganik, Residu) & Diversion Rate.
     */
    public static function getCompositionStats(?int $campusId): array
    {
        $query = static::query()
            ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
            ->join('waste_types', 'weighing_items.waste_type_id', '=', 'waste_types.id')
            ->select('waste_types.category', \Illuminate\Support\Facades\DB::raw('SUM(weighing_items.weight_kg) as total_kg'))
            ->when($campusId, fn($q) => $q->where('weighing_sessions.campus_id', $campusId))
            ->groupBy('waste_types.category');

        $rawWeights = $query->pluck('total_kg', 'category')->toArray();
        $categoryWeights = [];
        foreach ($rawWeights as $catKey => $catKg) {
            $normalizedKey = strtolower(trim((string) $catKey));
            if (str_contains($normalizedKey, 'organik') && !str_contains($normalizedKey, 'anorganik')) {
                $categoryWeights['organik'] = ($categoryWeights['organik'] ?? 0.0) + (float) $catKg;
            } elseif (str_contains($normalizedKey, 'anorganik')) {
                $categoryWeights['anorganik'] = ($categoryWeights['anorganik'] ?? 0.0) + (float) $catKg;
            } elseif (str_contains($normalizedKey, 'residu')) {
                $categoryWeights['residu'] = ($categoryWeights['residu'] ?? 0.0) + (float) $catKg;
            } else {
                $categoryWeights[$normalizedKey] = ($categoryWeights[$normalizedKey] ?? 0.0) + (float) $catKg;
            }
        }

        $organikKg = (float) ($categoryWeights['organik'] ?? 0.0);
        $anorganikKg = (float) ($categoryWeights['anorganik'] ?? 0.0);
        $residuKg = (float) ($categoryWeights['residu'] ?? 0.0);
        $totalCompositionKg = $organikKg + $anorganikKg + $residuKg;

        $organikPct = $totalCompositionKg > 0 ? round(($organikKg / $totalCompositionKg) * 100, 1) : 0.0;
        $anorganikPct = $totalCompositionKg > 0 ? round(($anorganikKg / $totalCompositionKg) * 100, 1) : 0.0;
        $residuPct = $totalCompositionKg > 0 ? round(($residuKg / $totalCompositionKg) * 100, 1) : 0.0;
        $diversionRate = $totalCompositionKg > 0 ? round((($organikKg + $anorganikKg) / $totalCompositionKg) * 100, 1) : 0.0;

        return [
            'organikKg' => $organikKg,
            'anorganikKg' => $anorganikKg,
            'residuKg' => $residuKg,
            'totalCompositionKg' => $totalCompositionKg,
            'organikPct' => $organikPct,
            'anorganikPct' => $anorganikPct,
            'residuPct' => $residuPct,
            'diversionRate' => $diversionRate,
        ];
    }

    /**
     * Menghitung tren timbulan sampah harian selama 7 hari terakhir.
     */
    public static function getSevenDaysTrend(?int $campusId): array
    {
        $shortDays = [0 => 'Min', 1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab'];
        $sevenDaysTrend = [];
        $baseDate = \Carbon\Carbon::today();

        for ($i = 6; $i >= 0; $i--) {
            $curDate = $baseDate->copy()->subDays($i);
            $curDateStr = $curDate->format('Y-m-d');
            $dayOfWeek = (int) $curDate->format('w');
            $shortDay = $shortDays[$dayOfWeek] ?? 'Hari';

            $dayKg = (float) static::query()
                ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
                ->whereDate('weighing_sessions.weigh_date', $curDateStr)
                ->when($campusId, fn($q) => $q->where('weighing_sessions.campus_id', $campusId))
                ->sum('weighing_items.weight_kg');

            $sevenDaysTrend[] = [
                'date' => $curDateStr,
                'short_day' => $shortDay,
                'day_num' => $curDate->format('d/m'),
                'weight_kg' => $dayKg,
            ];
        }

        $maxTrendWeight = max(1.0, (float) collect($sevenDaysTrend)->max('weight_kg'));

        return [
            'trend' => $sevenDaysTrend,
            'maxTrendWeight' => $maxTrendWeight,
        ];
    }
}

