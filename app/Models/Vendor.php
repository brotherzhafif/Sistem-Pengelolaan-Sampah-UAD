<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact',
        'cost_per_kg',
        'is_active',
    ];

    protected $casts = [
        'cost_per_kg' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}

