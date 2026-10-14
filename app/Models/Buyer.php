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
        $salesCount = Sale::where('buyer_id', $this->id)->count();

        $bindings = [];
        $reasons = [];

        if ($salesCount > 0) {
            $sampleSales = Sale::where('buyer_id', $this->id)
                ->orderByDesc('id')
                ->take(3)
                ->get();

            $bindings[] = [
                'type' => 'sales',
                'label' => 'Transaksi Penjualan',
                'count' => $salesCount,
                'url' => route('sales', $sampleSales->isNotEmpty() ? ['view' => $sampleSales->first()->id] : []),
                'badge' => "{$salesCount} Penjualan",
                'sample_ids' => $sampleSales->pluck('id')->toArray(),
            ];
            $sampleText = $sampleSales->isNotEmpty() ? " (contoh: Penjualan #" . $sampleSales->pluck('id')->implode(', #') . ")" : "";
            $reasons[] = "Terikat dengan {$salesCount} transaksi penjualan sampah bernilai{$sampleText}";
        }

        return [
            'is_bound' => count($bindings) > 0,
            'bindings' => $bindings,
            'reasons' => $reasons,
        ];
    }
}

