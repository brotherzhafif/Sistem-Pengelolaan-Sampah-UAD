<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * Mengambil daftar seluruh jenis sampah terurut kategori dan nama.
     */
    public static function getAllTypes()
    {
        return static::orderBy('category')->orderBy('name')->get();
    }

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

    /**
     * Mengambil ringkasan stok sampah terpilah dan residu per kampus atau keseluruhan (O(1)).
     */
    public static function getStockSummary(int|string|null $campusId = null): array
    {
        $campusId = !empty($campusId) ? (int) $campusId : null;
        $metrics = WasteStockSummary::getMetrics($campusId);

        $allWasteTypes = static::where('is_active', true)->orderBy('category')->orderBy('name')->get();
        $typeSummaries = WasteTypeStockSummary::when($campusId, fn($q) => $q->where('campus_id', $campusId))->get();

        $itemsBreakdown = [];
        foreach ($allWasteTypes as $type) {
            $typeRows = $typeSummaries->where('waste_type_id', $type->id);
            $weighed = (float) $typeRows->sum('total_weighed_kg');
            $sold = (float) $typeRows->sum('total_sold_kg');
            $available = (float) $typeRows->sum('available_stock_kg');
            $itemPotentialRevenue = (float) $typeRows->sum('potential_revenue');

            $itemsBreakdown[] = [
                'waste_type_id' => $type->id,
                'waste_name' => $type->name,
                'waste_category' => $type->category,
                'is_sellable' => $type->is_sellable,
                'default_price_per_kg' => (float) $type->default_price_per_kg,
                'total_weighed_kg' => $weighed,
                'total_sold_kg' => $sold,
                'available_stock_kg' => $available,
                'potential_revenue' => $itemPotentialRevenue,
            ];
        }

        return [
            'total_weighed_kg' => $metrics['total_weighed_kg'],
            'total_sold_kg' => $metrics['total_sold_kg'],
            'total_picked_up_kg' => $metrics['total_picked_up_kg'],
            'sellable_stock_kg' => $metrics['sellable_stock_kg'],
            'potential_revenue' => $metrics['potential_revenue'],
            'residual_stock_kg' => $metrics['residual_stock_kg'],
            'total_sales_revenue' => $metrics['total_sales_revenue'],
            'total_pickup_cost' => $metrics['total_pickup_cost'],
            'current_stock_kg' => $metrics['current_stock_kg'],
            'items_breakdown' => collect($itemsBreakdown),
        ];
    }

    /**
     * Mengambil daftar jenis sampah terpilah yang memiliki stok tersedia (> 0 kg)
     * untuk penimbangan yang sudah dilakukan pada kampus tertentu.
     */
    public static function getSellableTypesWithAvailableStock(int|string|null $campusId = null): array
    {
        $summary = static::getStockSummary($campusId);
        return $summary['items_breakdown']
            ->filter(fn ($item) => !empty($item['is_sellable']) && (float) $item['available_stock_kg'] > 0)
            ->values()
            ->all();
    }

    /**
     * Mengambil sisa stok tersedia untuk jenis sampah tertentu di kampus tertentu (O(1)).
     */
    public static function getAvailableStock(int $wasteTypeId, int|string|null $campusId = null): float
    {
        $campusId = !empty($campusId) ? (int) $campusId : null;
        return WasteTypeStockSummary::getAvailableStock($wasteTypeId, $campusId);
    }

    /**
     * Relasi ke seluruh baris item penimbangan.
     */
    public function weighingItems()
    {
        return $this->hasMany(WeighingItem::class);
    }

    /**
     * Relasi ke seluruh baris item transaksi penjualan.
     */
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Memeriksa keterikatan data relasional (Foreign Key) yang merujuk ke jenis sampah ini.
     * Mencegah penghapusan jika sudah digunakan pada penimbangan atau penjualan.
     */
    public function getBindingInfo(): array
    {
        $features = [];
        $primaryUrl = null;
        $primaryLabel = null;

        if (WeighingItem::where('waste_type_id', $this->id)->exists()) {
            $features[] = 'Penimbangan Sampah';
            $primaryUrl = route('weighing');
            $primaryLabel = 'Lihat Penimbangan';
        }

        if (SaleItem::where('waste_type_id', $this->id)->exists()) {
            $features[] = 'Penjualan Sampah';
            if (!$primaryUrl) {
                $primaryUrl = route('sales');
                $primaryLabel = 'Lihat Penjualan';
            }
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

