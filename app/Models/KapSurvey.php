<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KapSurvey extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'respondent_name',
        'respondent_identifier',
        'respondent_role',
        'faculty_unit',
        'gender',
        'has_attended_training',
        'is_willing_volunteer',
        'residence_type',
        'knowledge_responses',
        'attitude_responses',
        'practice_responses',
        'satisfaction_responses',
        'facility_responses',
        'barrier_responses',
        'knowledge_score',
        'attitude_score',
        'practice_score',
        'satisfaction_score',
        'overall_score',
        'category',
        'feedback',
        'survey_date',
        'device_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'has_attended_training' => 'boolean',
        'is_willing_volunteer' => 'boolean',
        'knowledge_responses' => 'array',
        'attitude_responses' => 'array',
        'practice_responses' => 'array',
        'satisfaction_responses' => 'array',
        'facility_responses' => 'array',
        'barrier_responses' => 'array',
        'knowledge_score' => 'float',
        'attitude_score' => 'float',
        'practice_score' => 'float',
        'satisfaction_score' => 'float',
        'overall_score' => 'float',
        'survey_date' => 'date',
    ];

    /**
     * Kampus lokasi responden beraktivitas.
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Memeriksa apakah perangkat dengan device_id tertentu sudah pernah mengirim survei pada hari ini.
     * Mencegah spam berulang dari perangkat yang sama (Layer 1 Anti-Spam).
     */
    public static function hasDeviceSubmittedToday(?string $deviceId): bool
    {
        if (empty($deviceId)) {
            return false;
        }

        return static::where('survey_date', Carbon::today()->toDateString())
            ->where('device_id', $deviceId)
            ->exists();
    }

    /**
     * Menghitung kategori otomatis berdasarkan skor total.
     */
    public static function determineCategory(float $overallScore): string
    {
        if ($overallScore >= 80.0) {
            return 'sangat_baik';
        }

        if ($overallScore >= 60.0) {
            return 'sedang';
        }

        return 'kurang';
    }

    /**
     * Label representasi manusia untuk kategori.
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'sangat_baik' => 'Sangat Baik (Tinggi)',
            'sedang' => 'Cukup / Sedang',
            'kurang' => 'Kurang / Perlu Peningkatan',
            default => 'Belum Ditentukan',
        };
    }

    /**
     * Label representasi manusia untuk peran responden.
     */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->respondent_role) {
            'mahasiswa' => 'Mahasiswa',
            'dosen' => 'Dosen',
            'tendik' => 'Tenaga Kependidikan',
            'outsourcing' => 'Tenaga Outsourcing',
            default => ucfirst($this->respondent_role),
        };
    }

    /**
     * Label representasi tempat tinggal.
     */
    public function getResidenceLabelAttribute(): string
    {
        return match ($this->residence_type) {
            'kos' => 'Kos Sekitar Kampus',
            'asrama' => 'Asrama UAD (Persada)',
            'rumah_sendiri' => 'Rumah Sendiri / Keluarga',
            'kontrakan' => 'Rumah Kontrakan',
            default => ucfirst(str_replace('_', ' ', $this->residence_type)),
        };
    }

    /**
     * Scope untuk query survei KAP dengan filter kampus, role, kategori, tanggal.
     */
    public function scopeFiltered($query, ?int $campusId = null, ?string $role = null, ?string $category = null, ?string $dateFrom = null, ?string $dateTo = null)
    {
        return $query->with('campus')
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->when($role, fn($q) => $q->where('respondent_role', $role))
            ->when($category, fn($q) => $q->where('category', $category))
            ->when($dateFrom, fn($q) => $q->whereDate('survey_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('survey_date', '<=', $dateTo))
            ->orderBy('survey_date', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Mengambil agregasi statistik analitik survei KAP.
     */
    public static function getStats(?int $campusId = null, ?string $role = null, ?string $category = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $statsQuery = static::query()
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->when($role, fn($q) => $q->where('respondent_role', $role))
            ->when($category, fn($q) => $q->where('category', $category))
            ->when($dateFrom, fn($q) => $q->whereDate('survey_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('survey_date', '<=', $dateTo));

        $totalRespondents = (int) (clone $statsQuery)->count();
        $avgKnowledge = (float) (clone $statsQuery)->avg('knowledge_score') ?? 0.0;
        $avgAttitude = (float) (clone $statsQuery)->avg('attitude_score') ?? 0.0;
        $avgPractice = (float) (clone $statsQuery)->avg('practice_score') ?? 0.0;
        $avgOverall = (float) (clone $statsQuery)->avg('overall_score') ?? 0.0;

        $highCount = (int) (clone $statsQuery)->where('category', 'sangat_baik')->count();
        $moderateCount = (int) (clone $statsQuery)->where('category', 'sedang')->count();
        $lowCount = (int) (clone $statsQuery)->where('category', 'kurang')->count();

        $highPct = $totalRespondents > 0 ? round(($highCount / $totalRespondents) * 100, 1) : 0.0;
        $moderatePct = $totalRespondents > 0 ? round(($moderateCount / $totalRespondents) * 100, 1) : 0.0;
        $lowPct = $totalRespondents > 0 ? round(($lowCount / $totalRespondents) * 100, 1) : 0.0;

        return [
            'totalRespondents' => $totalRespondents,
            'avgKnowledge' => round($avgKnowledge, 1),
            'avgAttitude' => round($avgAttitude, 1),
            'avgPractice' => round($avgPractice, 1),
            'avgOverall' => round($avgOverall, 1),
            'highCount' => $highCount,
            'moderateCount' => $moderateCount,
            'lowCount' => $lowCount,
            'highPct' => $highPct,
            'moderatePct' => $moderatePct,
            'lowPct' => $lowPct,
        ];
    }

    /**
     * Menghitung nilai skor Knowledge, Attitude, Practice, dan Satisfaction.
     */
    public static function calculateScores(array $knowledge, array $attitude, array $practice, array $satisfaction): array
    {
        $knowledgeKeys = [
            1 => true,
            2 => false,
            3 => true,
            4 => true,
            5 => true,
            6 => true,
            7 => false,
            8 => true,
        ];

        $correctCount = 0;
        foreach ($knowledgeKeys as $idx => $expected) {
            $val = (bool) ($knowledge[$idx] ?? false);
            if ($val === $expected) {
                $correctCount++;
            }
        }
        $kScore = ($correctCount / 8) * 100;

        $aInt = array_map('intval', $attitude);
        $pInt = array_map('intval', $practice);
        $sInt = array_map('intval', $satisfaction);

        $aScore = count($aInt) > 0 ? (array_sum($aInt) / (count($aInt) * 5)) * 100 : 0.0;
        $pScore = count($pInt) > 0 ? (array_sum($pInt) / (count($pInt) * 5)) * 100 : 0.0;
        $sScore = count($sInt) > 0 ? (array_sum($sInt) / (count($sInt) * 5)) * 100 : 0.0;

        $overallScore = round(($kScore + $aScore + $pScore) / 3, 2);

        return [
            'knowledge_score' => round($kScore, 2),
            'attitude_score' => round($aScore, 2),
            'practice_score' => round($pScore, 2),
            'satisfaction_score' => round($sScore, 2),
            'overall_score' => $overallScore,
            'category' => static::determineCategory($overallScore),
            'attitude_int' => $aInt,
            'practice_int' => $pInt,
            'satisfaction_int' => $sInt,
        ];
    }
}

