<?php

namespace App\Services;

use App\Models\Keuangan;
use App\Models\WeighingSession;
use Carbon\Carbon;

class AlertService
{
    public function __construct(
        protected StockService $stockService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Retrieve system notification alerts (M10).
     *
     * @param int|string|null $campusId
     * @return array
     */
    public function getAlerts(int|string|null $campusId = null): array
    {
        $campusId = !empty($campusId) ? (int) $campusId : null;
        $alerts = [];
        $today = Carbon::today()->format('Y-m-d');

        // 1. Reminder Input Harian
        $todaySessionsCount = WeighingSession::when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->whereDate('weigh_date', $today)
            ->count();

        if ($todaySessionsCount === 0) {
            $alerts[] = [
                'id' => 'daily_input_reminder',
                'type' => 'info',
                'title' => 'Reminder Input Harian',
                'message' => 'Belum ada catatan timbangan yang diinput hari ini (' . Carbon::today()->translatedFormat('l, d M Y') . ').',
                'action_url' => route('weighing'),
                'action_label' => 'Input Timbangan',
                'time' => 'Hari Ini',
            ];
        }

        // 2. Alert Stok Menumpuk & 3. Alert Akumulasi Residu Tinggi
        $stockSummary = $this->stockService->getStockSummary($campusId);
        $sellableKg = $stockSummary['sellable_stock_kg'] ?? 0;
        $residualKg = $stockSummary['residual_stock_kg'] ?? 0;

        if ($sellableKg >= 500) {
            $alerts[] = [
                'id' => 'stock_sellable_high',
                'type' => 'warning',
                'title' => 'Alert Stok Menumpuk',
                'message' => 'Akumulasi sampah terpilah siap jual mencapai ' . number_format($sellableKg, 1, ',', '.') . ' kg. Segera jadwalkan penjualan.',
                'action_url' => route('sales'),
                'action_label' => 'Catat Penjualan',
                'time' => 'Operasional',
            ];
        }

        if ($residualKg >= 1000) {
            $alerts[] = [
                'id' => 'stock_residual_high',
                'type' => 'danger',
                'title' => 'Alert Akumulasi Residu Tinggi',
                'message' => 'Akumulasi residu TPS mencapai ' . number_format($residualKg, 1, ',', '.') . ' kg. Segera jadwalkan pengangkutan armada.',
                'action_url' => route('pickups'),
                'action_label' => 'Jadwalkan Angkut',
                'time' => 'Kritis',
            ];
        }

        // 4. Alert Saldo Menipis
        $saldoKas = $this->ledgerService->getLatestBalance($campusId);
        if ($saldoKas < 500000 && $saldoKas > 0) {
            $alerts[] = [
                'id' => 'balance_low',
                'type' => 'warning',
                'title' => 'Alert Saldo Menipis',
                'message' => 'Saldo kas operasional tersisa Rp ' . number_format($saldoKas, 0, ',', '.') . '. Pantau pengeluaran.',
                'action_url' => route('finance'),
                'action_label' => 'Lihat Buku Kas',
                'time' => 'Finansial',
            ];
        }

        return $alerts;
    }
}

