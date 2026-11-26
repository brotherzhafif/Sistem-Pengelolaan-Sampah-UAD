<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\OrganicProcessing;
use App\Models\WasteStockSummary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicLandingController extends Controller
{
    /**
     * Menampilkan halaman landing publik dengan infografik sampah terkelola vs tidak terkelola.
     */
    public function index(Request $request)
    {
        $tablesReady = false;
        try {
            $tablesReady = Schema::hasTable('campuses') && Schema::hasTable('waste_stock_summaries');
        } catch (\Throwable) {
            $tablesReady = false;
        }

        if (!$tablesReady) {
            return view('welcome', [
                'campuses' => collect(),
                'activeCampusId' => null,
                'activeCampusName' => 'Seluruh Kampus UAD (Kampus 1 s.d. 6)',
                'campusBreakdown' => [],
                'infographic' => [
                    'managed_kg' => 0.0,
                    'managed_pct' => 0.0,
                    'sold_kg' => 0.0,
                    'sold_share_pct' => 0.0,
                    'processed_kg' => 0.0,
                    'processed_share_pct' => 0.0,
                    'processed_output_kg' => 0.0,
                    'processed_outputs' => [],
                    'compost_kg' => 0.0,
                    'feed_kg' => 0.0,
                    'unmanaged_kg' => 0.0,
                    'unmanaged_pct' => 0.0,
                    'total_handled_kg' => 0.0,
                    'total_weighed_kg' => 0.0,
                    'remaining_stock_kg' => 0.0,
                    'last_updated_label' => null,
                ],
            ]);
        }

        $campuses = Campus::getActiveCampuses();
        $summaries = WasteStockSummary::all()->keyBy('campus_id');

        $requestedCampusId = $request->query('campus_id');
        $activeCampusId = (!empty($requestedCampusId) && $campuses->contains('id', (int) $requestedCampusId))
            ? (int) $requestedCampusId
            : null;

        $campusBreakdown = [];
        foreach ($campuses as $campus) {
            $summary = $summaries->get($campus->id);

            $weighedKg = (float) ($summary?->total_weighed_kg ?? 0.0);
            $soldKg = (float) ($summary?->total_sold_kg ?? 0.0);
            $processedKg = (float) ($summary?->total_processed_kg ?? 0.0);
            $compostKg = (float) ($summary?->total_compost_kg ?? 0.0);
            $feedKg = (float) ($summary?->total_feed_kg ?? 0.0);
            $pickedUpKg = (float) ($summary?->total_picked_up_kg ?? 0.0);
            $currentStockKg = (float) ($summary?->current_stock_kg ?? 0.0);

            $managedKg = round($soldKg + $processedKg, 2);
            $unmanagedKg = round($pickedUpKg, 2);
            $handledKg = round($managedKg + $unmanagedKg, 2);

            $managedPct = $handledKg > 0 ? round(($managedKg / $handledKg) * 100, 1) : 0.0;
            $unmanagedPct = $handledKg > 0 ? round(max(0.0, 100.0 - $managedPct), 1) : 0.0;

            $campusBreakdown[] = [
                'id' => $campus->id,
                'name' => $campus->name,
                'weighed_kg' => $weighedKg,
                'sold_kg' => $soldKg,
                'processed_kg' => $processedKg,
                'compost_kg' => $compostKg,
                'feed_kg' => $feedKg,
                'managed_kg' => $managedKg,
                'unmanaged_kg' => $unmanagedKg,
                'total_handled_kg' => $handledKg,
                'managed_pct' => $managedPct,
                'unmanaged_pct' => $unmanagedPct,
                'remaining_stock_kg' => $currentStockKg,
            ];
        }

        if ($activeCampusId) {
            $selectedRow = collect($campusBreakdown)->firstWhere('id', $activeCampusId);
            $activeCampusName = $selectedRow['name'] ?? 'Unit Kampus UAD';
            $metrics = WasteStockSummary::getMetrics($activeCampusId);
            $soldKg = (float) ($metrics['total_sold_kg'] ?? 0.0);
            $processedKg = (float) ($metrics['total_processed_kg'] ?? 0.0);
            $compostKg = (float) ($metrics['total_compost_kg'] ?? 0.0);
            $feedKg = (float) ($metrics['total_feed_kg'] ?? 0.0);
            $unmanagedKg = (float) ($metrics['total_picked_up_kg'] ?? 0.0);
            $weighedKg = (float) ($metrics['total_weighed_kg'] ?? 0.0);
            $remainingStockKg = (float) ($metrics['current_stock_kg'] ?? 0.0);
            $lastUpdatedAt = $summaries->get($activeCampusId)?->updated_at;
        } else {
            $activeCampusName = 'Seluruh Kampus UAD (Kampus 1 s.d. 6)';
            $metrics = WasteStockSummary::getMetrics(null);
            $soldKg = (float) ($metrics['total_sold_kg'] ?? 0.0);
            $processedKg = (float) ($metrics['total_processed_kg'] ?? 0.0);
            $compostKg = (float) ($metrics['total_compost_kg'] ?? 0.0);
            $feedKg = (float) ($metrics['total_feed_kg'] ?? 0.0);
            $unmanagedKg = (float) ($metrics['total_picked_up_kg'] ?? 0.0);
            $weighedKg = (float) ($metrics['total_weighed_kg'] ?? 0.0);
            $remainingStockKg = (float) ($metrics['current_stock_kg'] ?? 0.0);
            $lastUpdatedAt = $summaries->max('updated_at');
        }

        $managedKg = round($soldKg + $processedKg, 2);
        $totalHandledKg = round($managedKg + $unmanagedKg, 2);

        $managedPct = $totalHandledKg > 0 ? round(($managedKg / $totalHandledKg) * 100, 1) : 0.0;
        $unmanagedPct = $totalHandledKg > 0 ? round(max(0.0, 100.0 - $managedPct), 1) : 0.0;

        $soldSharePct = $totalHandledKg > 0 ? round(($soldKg / $totalHandledKg) * 100, 1) : 0.0;
        $processedSharePct = $totalHandledKg > 0 ? round(max(0.0, $managedPct - $soldSharePct), 1) : 0.0;

        $processingTotals = Schema::hasTable('organic_processings') && Schema::hasTable('organic_output_categories')
            ? OrganicProcessing::getTotals($activeCampusId)
            : ['total_output_kg' => 0.0, 'by_category' => []];

        $lastUpdatedLabel = $lastUpdatedAt
            ? Carbon::parse($lastUpdatedAt)->translatedFormat('d M Y, H:i') . ' WIB'
            : null;

        return view('welcome', [
            'campuses' => $campuses,
            'activeCampusId' => $activeCampusId,
            'activeCampusName' => $activeCampusName,
            'campusBreakdown' => $campusBreakdown,
            'infographic' => [
                'managed_kg' => $managedKg,
                'managed_pct' => $managedPct,
                'sold_kg' => $soldKg,
                'sold_share_pct' => $soldSharePct,
                'processed_kg' => $processedKg,
                'processed_share_pct' => $processedSharePct,
                'processed_output_kg' => (float) ($processingTotals['total_output_kg'] ?? 0.0),
                'processed_outputs' => $processingTotals['by_category'] ?? [],
                'compost_kg' => $compostKg,
                'feed_kg' => $feedKg,
                'unmanaged_kg' => $unmanagedKg,
                'unmanaged_pct' => $unmanagedPct,
                'total_handled_kg' => $totalHandledKg,
                'total_weighed_kg' => $weighedKg,
                'remaining_stock_kg' => $remainingStockKg,
                'last_updated_label' => $lastUpdatedLabel,
            ],
        ]);
    }
}
