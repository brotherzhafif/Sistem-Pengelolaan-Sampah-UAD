<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pickup extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'vendor_id',
        'pickup_date',
        'volume_kg',
        'cost_per_kg',
        'total_cost',
        'driver_name',
        'vehicle_plate',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'volume_kg' => 'decimal:2',
        'cost_per_kg' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
