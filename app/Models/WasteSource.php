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
     * Mengambil daftar sumber timbulan berdasarkan unit kampus.
     */
    public static function getByCampus(?int $campusId)
    {
        return static::when($campusId, fn($q) => $q->where('campus_id', $campusId))->get();
    }
}

