<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Buyer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact',
    ];

    /**
     * Relasi ke seluruh transaksi penjualan yang menjual ke pembeli ini.
     */
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Mengambil daftar seluruh pembeli / lapak terurut alfabetik.
     */
    public static function getAllBuyers()
    {
        return static::orderBy('name')->get();
    }

    /**
     * Memeriksa keterikatan data relasional (Foreign Key) yang merujuk ke pembeli ini.
     * Mencegah penghapusan jika sudah digunakan pada transaksi penjualan sampah.
     */
    public function getBindingInfo(): array
    {
        $hasSales = Sale::where('buyer_id', $this->id)->exists();
        $features = [];
        $primaryUrl = null;
        $primaryLabel = null;

        if ($hasSales) {
            $features[] = 'Penjualan Sampah';
            $primaryUrl = route('sales');
            $primaryLabel = 'Lihat Penjualan';
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

