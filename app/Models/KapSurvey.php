<?php

namespace App\Models;

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
}

