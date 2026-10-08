<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'waste_type_id',
        'weight_kg',
        'price_per_kg',
        'subtotal',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
        'price_per_kg' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }
}

