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

    /**
     * Mengambil daftar seluruh vendor pengangkutan residu terurut nama.
     */
    public static function getAllVendors()
    {
        return static::orderBy('name')->get();
    }

    /**
     * Mengambil daftar vendor pengangkutan residu yang aktif.
     */
    public static function getActiveVendors()
    {
        return static::where('is_active', true)->orderBy('name')->get();
    }
}

