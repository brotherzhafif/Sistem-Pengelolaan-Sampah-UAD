<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteStockMutation extends Model
{
    use HasFactory;

    protected $table = 'waste_stock_mutations';

    protected $fillable = [
        'campus_id',
        'reference_type',
        'reference_id',
        'action',
        'weight_delta_kg',
        'total_weighed_after',
        'total_sold_after',
        'total_picked_up_after',
        'current_stock_after',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'weight_delta_kg' => 'decimal:2',
        'total_weighed_after' => 'decimal:2',
        'total_sold_after' => 'decimal:2',
        'total_picked_up_after' => 'decimal:2',
        'current_stock_after' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
