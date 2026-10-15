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
        $hasWeighing = WeighingSession::where('waste_source_id', $this->id)->exists();
        $features = [];
        $primaryUrl = null;
        $primaryLabel = null;

        if ($hasWeighing) {
            $features[] = 'Penimbangan Sampah';
            $primaryUrl = route('weighing');
            $primaryLabel = 'Lihat Penimbangan';
        }

        return [
            'is_bound' => !empty($features),
            'features' => $features,
            'primary_url' => $primaryUrl,
            'primary_label' => $primaryLabel,
            'reasons' => $features,
            'bindings' => $primaryUrl ? [['url' => $primaryUrl, 'label' => $primaryLabel]] : [],
        ];
    }
}

