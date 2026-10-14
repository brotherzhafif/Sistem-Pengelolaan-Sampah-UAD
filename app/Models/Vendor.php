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
        $pickupCount = Pickup::where('vendor_id', $this->id)->count();

        $bindings = [];
        $reasons = [];

        if ($pickupCount > 0) {
            $samplePickups = Pickup::where('vendor_id', $this->id)
                ->orderByDesc('id')
                ->take(3)
                ->get();

            $bindings[] = [
                'type' => 'pickups',
                'label' => 'Pengangkutan Residu',
                'count' => $pickupCount,
                'url' => route('pickups', $samplePickups->isNotEmpty() ? ['view' => $samplePickups->first()->id] : []),
                'badge' => "{$pickupCount} Pengangkutan",
                'sample_ids' => $samplePickups->pluck('id')->toArray(),
            ];
            $sampleText = $samplePickups->isNotEmpty() ? " (contoh: Pengangkutan #" . $samplePickups->pluck('id')->implode(', #') . ")" : "";
            $reasons[] = "Terikat dengan {$pickupCount} transaksi pengangkutan residu vendor{$sampleText}";
        }

        return [
            'is_bound' => count($bindings) > 0,
            'bindings' => $bindings,
            'reasons' => $reasons,
        ];
    }
}

