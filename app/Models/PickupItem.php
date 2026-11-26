<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pickup_id',
        'waste_type_id',
        'weight_kg',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
    ];

    public function pickup(): BelongsTo
    {
        return $this->belongsTo(Pickup::class);
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }
}
