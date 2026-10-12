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

    /**
     * Mengambil daftar seluruh jenis sampah aktif terurut kategori dan nama.
     */
    public static function getActiveTypes()
    {
        return static::where('is_active', true)->orderBy('category')->orderBy('name')->get();
    }

    /**
     * Mengambil daftar jenis sampah aktif yang dapat dijual (anorganik/terpilah).
     */
    public static function getActiveSellableTypes()
    {
        return static::where('is_sellable', true)->where('is_active', true)->orderBy('name')->get();
    }
}

