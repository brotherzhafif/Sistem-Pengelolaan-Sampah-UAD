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
     * Relasi ke seluruh transaksi pengangkutan residu yang menggunakan vendor ini.
     */
    public function pickups()
    {
        return $this->hasMany(Pickup::class);
    }

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

    /**
     * Memeriksa keterikatan data relasional (Foreign Key) yang merujuk ke vendor ini.
     * Mencegah penghapusan jika sudah digunakan pada transaksi pengangkutan residu.
     */
    public function getBindingInfo(): array
    {
        $hasPickups = Pickup::where('vendor_id', $this->id)->exists();
        $features = [];
        $primaryUrl = null;
        $primaryLabel = null;

        if ($hasPickups) {
            $features[] = 'Pengangkutan Residu';
            $primaryUrl = route('pickups');
            $primaryLabel = 'Lihat Pengangkutan';
        }

        return [
            'is_bound' => !empty($features),
            'features' => $features,
            'primary_url' => $primaryUrl,
            'primary_label' => $primaryLabel,
            'reasons' => $features,
            'bindings' => $primaryUrl ? [['url' => $primaryUrl, 'label' => $primaryLabel]] : [],
        ];
    }
}

