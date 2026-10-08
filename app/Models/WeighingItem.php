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
}

