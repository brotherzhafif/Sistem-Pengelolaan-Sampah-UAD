<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganicProcessingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'organic_processing_id',
        'waste_type_id',
        'weight_kg',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
    ];

    public function processing(): BelongsTo
    {
        return $this->belongsTo(OrganicProcessing::class, 'organic_processing_id');
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }
}
