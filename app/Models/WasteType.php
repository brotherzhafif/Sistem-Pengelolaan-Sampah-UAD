<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WasteType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'default_price_per_kg',
        'is_sellable',
        'is_active',
    ];

    protected $casts = [
        'default_price_per_kg' => 'decimal:2',
        'is_sellable' => 'boolean',
        'is_active' => 'boolean',
    ];
}

