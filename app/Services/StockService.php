<?php

namespace App\Services;

use App\Models\WeighingItem;
use App\Models\WasteType;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Get stock summary for a campus.
     * Returns:
     * - total_weighed_kg
     * - sellable_stock_kg (terpilah siap jual)
     * - residual_stock_kg (residu menunggu angkut)
     * - by_type breakdown
     */
    public function getStockSummary(?int $campusId = null): array
    {
        $query = WeighingItem::query()
            ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
            ->join('waste_types', 'weighing_items.waste_type_id', '=', 'waste_types.id')
            ->select(
                'waste_types.id as waste_type_id',
                'waste_types.name as waste_name',
                'waste_types.category as waste_category',
                'waste_types.is_sellable',
                'waste_types.default_price_per_kg',
                DB::raw('SUM(weighing_items.weight_kg) as total_weight_kg'),
                DB::raw('SUM(COALESCE(weighing_items.volume_m3, 0)) as total_volume_m3'),
                DB::raw('COUNT(weighing_items.id) as total_entries')
            )
            ->groupBy('waste_types.id', 'waste_types.name', 'waste_types.category', 'waste_types.is_sellable', 'waste_types.default_price_per_kg');

        if ($campusId) {
            $query->where('weighing_sessions.campus_id', $campusId);
        }

        $items = $query->get();

        $totalWeighed = $items->sum('total_weight_kg');
        $sellableStock = $items->where('is_sellable', true)->sum('total_weight_kg');
        $residualStock = $items->where('is_sellable', false)->sum('total_weight_kg');

        return [
            'total_weighed_kg' => (float) $totalWeighed,
            'sellable_stock_kg' => (float) $sellableStock,
            'residual_stock_kg' => (float) $residualStock,
            'items_breakdown' => $items,
        ];
    }
}

