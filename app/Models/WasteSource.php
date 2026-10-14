<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Relasi ke seluruh sesi penimbangan yang berasal dari titik sumber ini.
     */
    public function weighingSessions()
    {
        return $this->hasMany(WeighingSession::class);
    }

    /**
     * Mengambil daftar sumber timbulan berdasarkan unit kampus.
     */
    public static function getByCampus(?int $campusId)
    {
        return static::when($campusId, fn($q) => $q->where('campus_id', $campusId))->orderBy('name')->get();
    }

    /**
     * Memeriksa keterikatan data relasional (Foreign Key) yang merujuk ke titik sumber ini.
     * Mencegah penghapusan jika sudah digunakan pada sesi penimbangan.
     */
    public function getBindingInfo(): array
    {
        $sessionCount = WeighingSession::where('waste_source_id', $this->id)->count();

        $bindings = [];
        $reasons = [];

        if ($sessionCount > 0) {
            $sampleSessions = WeighingSession::where('waste_source_id', $this->id)
                ->orderByDesc('id')
                ->take(3)
                ->get();

            $bindings[] = [
                'type' => 'weighing',
                'label' => 'Sesi Penimbangan',
                'count' => $sessionCount,
                'url' => route('weighing', $sampleSessions->isNotEmpty() ? ['view' => $sampleSessions->first()->id] : []),
                'badge' => "{$sessionCount} Penimbangan",
                'sample_ids' => $sampleSessions->pluck('id')->toArray(),
            ];
            $sampleText = $sampleSessions->isNotEmpty() ? " (contoh: Sesi #" . $sampleSessions->pluck('id')->implode(', #') . ")" : "";
            $reasons[] = "Terikat dengan {$sessionCount} sesi penimbangan sampah kampus{$sampleText}";
        }

        return [
            'is_bound' => count($bindings) > 0,
            'bindings' => $bindings,
            'reasons' => $reasons,
        ];
    }
}

