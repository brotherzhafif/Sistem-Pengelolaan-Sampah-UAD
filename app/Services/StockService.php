<?php

namespace App\Services;

use App\Models\SaleItem;
use App\Models\WeighingItem;
use App\Models\WasteType;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Get stock summary for a campus taking sales into account.
     * Stok Sampah Terpilah = Total Timbang - Total Terjual.
     *
     * @param int|null $campusId
     * @return array
     */
    public function getStockSummary(?int $campusId = null): array
    {
        // 1. Agregasi total timbangan per waste_type
        $weighQuery = WeighingItem::query()
            ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
            ->select(
                'weighing_items.waste_type_id',
                DB::raw('SUM(weighing_items.weight_kg) as total_weighed_kg'),
                DB::raw('SUM(COALESCE(weighing_items.volume_m3, 0)) as total_volume_m3')
            )
            ->groupBy('weighing_items.waste_type_id');

        if ($campusId) {
            $weighQuery->where('weighing_sessions.campus_id', $campusId);
        }

        $weighedByType = $weighQuery->pluck('total_weighed_kg', 'waste_type_id')->toArray();
        $volumesByType = $weighQuery->pluck('total_volume_m3', 'waste_type_id')->toArray();

        // 2. Agregasi total penjualan per waste_type
        $soldQuery = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->select(
                'sale_items.waste_type_id',
                DB::raw('SUM(sale_items.weight_kg) as total_sold_kg')
            )
            ->groupBy('sale_items.waste_type_id');

        if ($campusId) {
            $soldQuery->where('sales.campus_id', $campusId);
        }

        $soldByType = $soldQuery->pluck('total_sold_kg', 'waste_type_id')->toArray();

        // 3. Gabungkan dengan data waste_types
        $allWasteTypes = WasteType::where('is_active', true)->orderBy('category')->orderBy('name')->get();

        $itemsBreakdown = [];
        $totalWeighed = 0.0;
        $totalSold = 0.0;
        $sellableStock = 0.0;
        $residualStock = 0.0;

        foreach ($allWasteTypes as $type) {
            $weighed = (float) ($weighedByType[$type->id] ?? 0.0);
            $sold = (float) ($soldByType[$type->id] ?? 0.0);
            $available = max(0.0, $weighed - $sold);
            $volume = (float) ($volumesByType[$type->id] ?? 0.0);

            $totalWeighed += $weighed;
            $totalSold += $sold;

            if ($type->is_sellable) {
                $sellableStock += $available;
            } else {
                $residualStock += $available;
            }

            $itemsBreakdown[] = [
                'waste_type_id' => $type->id,
                'waste_name' => $type->name,
                'waste_category' => $type->category,
                'is_sellable' => $type->is_sellable,
                'default_price_per_kg' => (float) $type->default_price_per_kg,
                'total_weighed_kg' => $weighed,
                'total_sold_kg' => $sold,
                'available_stock_kg' => $available,
                'total_volume_m3' => $volume,
            ];
        }

        return [
            'total_weighed_kg' => $totalWeighed,
            'total_sold_kg' => $totalSold,
            'sellable_stock_kg' => $sellableStock,
            'residual_stock_kg' => $residualStock,
            'items_breakdown' => collect($itemsBreakdown),
        ];
    }

    /**
     * Get available stock for a specific waste type and campus.
     */
    public function getAvailableStock(int $wasteTypeId, ?int $campusId = null): float
    {
        $weighedQuery = WeighingItem::query()
            ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
            ->where('weighing_items.waste_type_id', $wasteTypeId);

        if ($campusId) {
            $weighedQuery->where('weighing_sessions.campus_id', $campusId);
        }

        $totalWeighed = (float) $weighedQuery->sum('weighing_items.weight_kg');

        $soldQuery = SaleItem::query()
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sale_items.waste_type_id', $wasteTypeId);

        if ($campusId) {
            $soldQuery->where('sales.campus_id', $campusId);
        }

        $totalSold = (float) $soldQuery->sum('sale_items.weight_kg');

        return max(0.0, $totalWeighed - $totalSold);
    }
}
