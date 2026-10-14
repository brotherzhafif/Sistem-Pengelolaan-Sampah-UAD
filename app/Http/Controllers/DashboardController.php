<?php

namespace App\Http\Controllers;

use App\Models\BukuBesar;
use App\Models\Campus;
use App\Models\Expense;
use App\Models\Keuangan;
use App\Models\Pickup;
use App\Models\Sale;
use App\Models\WasteType;
use App\Models\WeighingItem;
use App\Models\WeighingSession;
use App\Livewire\Pages\Dashboard\Index as DashboardComponent;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Menampilkan antarmuka Dashboard Teknis & Operasional PS2 UAD.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        return app(DashboardComponent::class)();
    }

    /**
     * Mengagregasikan seluruh metrik operasional, timbulan, komposisi, stok, dan finansial dashboard.
     */
    public function getMetrics(?int $campusId): array
    {
        // 1. Timbangan Hari Ini & Akumulasi (dari Model WeighingItem)
        $todayWeighedKg = WeighingItem::getTodayWeighedKg($campusId);
        $totalAccumulation = WeighingItem::getTotalAccumulation($campusId);

        // 2. Stok Terpilah & Residu Realtime (dari Model WasteType)
        $stockSummary = WasteType::getStockSummary($campusId);

        // 3. Komposisi Sampah & Diversion Rate (dari Model WeighingItem)
        $composition = WeighingItem::getCompositionStats($campusId);

        // 4. Tren Timbulan 7 Hari Terakhir (dari Model WeighingItem)
        $trendData = WeighingItem::getSevenDaysTrend($campusId);

        // 5. Kesehatan Finansial & Kas Sirkular (dari Model Keuangan & BukuBesar)
        $finances = Keuangan::getFinancialSummary($campusId);
        $saldoKas = BukuBesar::getLatestBalance($campusId);

        // 6. Sistem Peringatan Otomatis (Alerts dari NotificationController)
        $alerts = NotificationController::getAlerts($campusId);

        // 7. Aktivitas Terbaru 5 Transaksi Terakhir (dari Models)
        $recentSessions = WeighingSession::getRecentSessions($campusId, 5);
        $recentSales = Sale::getRecentSales($campusId, 5);
        $recentPickups = Pickup::getRecentPickups($campusId, 5);
        $recentExpenses = Expense::getRecentExpenses($campusId, 5);

        return [
            'todayWeighedKg' => $todayWeighedKg,
            'totalAllWeighedKg' => $totalAccumulation['totalKg'],
            'totalAllVolumeM3' => $totalAccumulation['totalVolumeM3'],
            'sellableStockKg' => $stockSummary['sellable_stock_kg'],
            'potentialRevenue' => $stockSummary['potential_revenue'] ?? 0.0,
            'residualStockKg' => $stockSummary['residual_stock_kg'],
            'organikKg' => $composition['organikKg'],
            'anorganikKg' => $composition['anorganikKg'],
            'residuKg' => $composition['residuKg'],
            'totalCompositionKg' => $composition['totalCompositionKg'],
            'organikPct' => $composition['organikPct'],
            'anorganikPct' => $composition['anorganikPct'],
            'residuPct' => $composition['residuPct'],
            'diversionRate' => $composition['diversionRate'],
            'sevenDaysTrend' => $trendData['trend'],
            'maxTrendWeight' => $trendData['maxTrendWeight'],
            'salesRevenue' => $finances['salesRevenue'],
            'pickupExpense' => $finances['pickupExpense'],
            'operationalExpense' => $finances['operationalExpense'],
            'saldoKas' => $saldoKas,
            'alerts' => $alerts,
            'recentSessions' => $recentSessions,
            'recentSales' => $recentSales,
            'recentPickups' => $recentPickups,
            'recentExpenses' => $recentExpenses,
        ];
    }
}

