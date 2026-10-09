<?php

use App\Models\BukuBesar;
use App\Models\Campus;
use App\Models\Expense;
use App\Models\Keuangan;
use App\Models\Pickup;
use App\Models\Sale;
use App\Models\WeighingItem;
use App\Models\WeighingSession;
use App\Services\LedgerService;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $selectedCampusId = null;
    public string $activeActivityTab = 'weighing'; // weighing, sales, pickups, expenses

    public function mount(): void
    {
        $user = auth()->user();
        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $sessionCampus = session('active_campus_id');
            $this->selectedCampusId = $sessionCampus !== null && $sessionCampus !== '' ? (int) $sessionCampus : null;
        } else {
            $this->selectedCampusId = (int) $user->campus_id;
        }
    }

    public function updatedSelectedCampusId($value): void
    {
        $campusId = !empty($value) ? (int) $value : null;
        session(['active_campus_id' => $campusId]);
    }

    public function setActivityTab(string $tab): void
    {
        $this->activeActivityTab = $tab;
    }

    public function with(StockService $stockService, LedgerService $ledgerService): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        $activeCampus = $campusQueryId ? Campus::find($campusQueryId) : null;
        $campuses = Campus::where('is_active', true)->orderBy('id')->get();

        $today = Carbon::today()->format('Y-m-d');

        // 1. Timbangan Hari Ini
        $weighingTodayQuery = WeighingItem::query()
            ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
            ->whereDate('weighing_sessions.weigh_date', $today);

        if ($campusQueryId) {
            $weighingTodayQuery->where('weighing_sessions.campus_id', $campusQueryId);
        }

        $todayWeighedKg = (float) $weighingTodayQuery->sum('weighing_items.weight_kg');

        // Total akumulasi timbangan (kg) & volume (m3)
        $totalWeighedQuery = WeighingItem::query()
            ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id');
        if ($campusQueryId) {
            $totalWeighedQuery->where('weighing_sessions.campus_id', $campusQueryId);
        }

        $totalAllWeighedKg = (float) (clone $totalWeighedQuery)->sum('weighing_items.weight_kg');
        $totalAllVolumeM3 = (float) (clone $totalWeighedQuery)->sum('weighing_items.volume_m3');

        // 2. Stok Terpilah & Residu Realtime dari StockService
        $stockSummary = $stockService->getStockSummary($campusQueryId);

        // 3. Komposisi Sampah (Organik vs Anorganik vs Residu)
        $compositionQuery = WeighingItem::query()
            ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
            ->join('waste_types', 'weighing_items.waste_type_id', '=', 'waste_types.id')
            ->select('waste_types.category', DB::raw('SUM(weighing_items.weight_kg) as total_kg'))
            ->groupBy('waste_types.category');

        if ($campusQueryId) {
            $compositionQuery->where('weighing_sessions.campus_id', $campusQueryId);
        }

        $rawCategoryWeights = $compositionQuery->pluck('total_kg', 'category')->toArray();
        $categoryWeights = [];
        foreach ($rawCategoryWeights as $catKey => $catKg) {
            $normalizedKey = strtolower(trim((string) $catKey));
            if (str_contains($normalizedKey, 'organik') && !str_contains($normalizedKey, 'anorganik')) {
                $categoryWeights['organik'] = ($categoryWeights['organik'] ?? 0.0) + (float) $catKg;
            } elseif (str_contains($normalizedKey, 'anorganik')) {
                $categoryWeights['anorganik'] = ($categoryWeights['anorganik'] ?? 0.0) + (float) $catKg;
            } elseif (str_contains($normalizedKey, 'residu')) {
                $categoryWeights['residu'] = ($categoryWeights['residu'] ?? 0.0) + (float) $catKg;
            } else {
                $categoryWeights[$normalizedKey] = ($categoryWeights[$normalizedKey] ?? 0.0) + (float) $catKg;
            }
        }

        $organikKg = (float) ($categoryWeights['organik'] ?? 0.0);
        $anorganikKg = (float) ($categoryWeights['anorganik'] ?? 0.0);
        $residuKg = (float) ($categoryWeights['residu'] ?? 0.0);
        $totalCompositionKg = $organikKg + $anorganikKg + $residuKg;

        $organikPct = $totalCompositionKg > 0 ? round(($organikKg / $totalCompositionKg) * 100, 1) : 0.0;
        $anorganikPct = $totalCompositionKg > 0 ? round(($anorganikKg / $totalCompositionKg) * 100, 1) : 0.0;
        $residuPct = $totalCompositionKg > 0 ? round(($residuKg / $totalCompositionKg) * 100, 1) : 0.0;

        // Diversion Rate (Tingkat Pengalihan dari TPA: Organik didaur-ulang/kompos + Anorganik dijual)
        $diversionRate = $totalCompositionKg > 0 ? round((($organikKg + $anorganikKg) / $totalCompositionKg) * 100, 1) : 0.0;

        // 4. Tren Timbulan 7 Hari Terakhir
        $shortDays = [0 => 'Min', 1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab'];
        $sevenDaysTrend = [];
        $baseDate = Carbon::today();

        for ($i = 6; $i >= 0; $i--) {
            $curDate = $baseDate->copy()->subDays($i);
            $curDateStr = $curDate->format('Y-m-d');
            $dayOfWeek = (int) $curDate->format('w');
            $shortDay = $shortDays[$dayOfWeek] ?? 'Hari';

            $dayQuery = WeighingItem::query()
                ->join('weighing_sessions', 'weighing_items.weighing_session_id', '=', 'weighing_sessions.id')
                ->whereDate('weighing_sessions.weigh_date', $curDateStr);

            if ($campusQueryId) {
                $dayQuery->where('weighing_sessions.campus_id', $campusQueryId);
            }

            $dayKg = (float) $dayQuery->sum('weighing_items.weight_kg');

            $sevenDaysTrend[] = [
                'date' => $curDateStr,
                'short_day' => $shortDay,
                'day_num' => $curDate->format('d/m'),
                'weight_kg' => $dayKg,
            ];
        }

        $maxTrendWeight = max(1.0, (float) collect($sevenDaysTrend)->max('weight_kg'));

        // 5. Kesehatan Sirkular Finansial & Kas
        $finQuery = Keuangan::query();
        if ($campusQueryId) {
            $finQuery->where('campus_id', $campusQueryId);
        }

        $salesRevenue = (float) (clone $finQuery)->where('sumber', 'penjualan')->sum('nominal');
        $pickupExpense = (float) (clone $finQuery)->where('sumber', 'pengangkutan')->sum('nominal');
        $operationalExpense = (float) (clone $finQuery)->where('sumber', 'operasional')->sum('nominal');

        $totalKredit = (float) (clone $finQuery)->where('jenis', 'K')->sum('nominal');
        $totalDebet = (float) (clone $finQuery)->where('jenis', 'D')->sum('nominal');
        
        // SRS M1 & REV-04: Saldo Kas Sirkular diambil dari saldo_akhir buku besar
        $saldoKas = $ledgerService->getLatestBalance($campusQueryId);

        // SRS M10 — Sistem Notifikasi & Peringatan Otomatis (Alerts)
        $alerts = [];
        if ($todayWeighedKg == 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Reminder Input Harian',
                'message' => 'Belum ada catatan penimbangan sampah yang diinput hari ini (' . Carbon::today()->translatedFormat('l, d F Y') . ').',
                'action_url' => route('weighing'),
                'action_label' => 'Input Timbangan',
            ];
        }
        if (($stockSummary['sellable_stock_kg'] ?? 0) >= 500) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Alert Stok Menumpuk',
                'message' => 'Akumulasi sampah terpilah siap jual mencapai ' . number_format($stockSummary['sellable_stock_kg'], 1, ',', '.') . ' kg. Segera jadwalkan penjualan ke pengepul.',
                'action_url' => route('sales'),
                'action_label' => 'Catat Penjualan',
            ];
        }
        if (($stockSummary['residual_stock_kg'] ?? 0) >= 1000) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Alert Akumulasi Residu Tinggi',
                'message' => 'Akumulasi residu TPS mencapai ' . number_format($stockSummary['residual_stock_kg'], 1, ',', '.') . ' kg. Segera jadwalkan pengangkutan armada ke TPA Piyungan.',
                'action_url' => route('pickups'),
                'action_label' => 'Jadwalkan Angkut',
            ];
        }
        if ($saldoKas < 500000 && $saldoKas > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Alert Saldo Menipis',
                'message' => 'Saldo kas operasional kampus tersisa Rp ' . number_format($saldoKas, 0, ',', '.') . '. Pantau pengeluaran operasional.',
                'action_url' => route('finance'),
                'action_label' => 'Lihat Buku Kas',
            ];
        }

        // 6. Aktivitas Terbaru (Per Modul)
        $recentSessions = WeighingSession::with(['campus', 'wasteSource', 'creator', 'items'])
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->orderBy('weigh_date', 'desc')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $recentSales = Sale::with(['campus', 'buyer', 'creator'])
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->orderBy('sale_date', 'desc')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $recentPickups = Pickup::with(['campus', 'vendor', 'creator'])
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->orderBy('pickup_date', 'desc')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $recentExpenses = Expense::with(['campus', 'category', 'creator'])
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => $campuses,
            'activeCampus' => $activeCampus,
            'todayWeighedKg' => $todayWeighedKg,
            'totalAllWeighedKg' => $totalAllWeighedKg,
            'totalAllVolumeM3' => $totalAllVolumeM3,
            'sellableStockKg' => $stockSummary['sellable_stock_kg'],
            'residualStockKg' => $stockSummary['residual_stock_kg'],
            'organikKg' => $organikKg,
            'anorganikKg' => $anorganikKg,
            'residuKg' => $residuKg,
            'totalCompositionKg' => $totalCompositionKg,
            'organikPct' => $organikPct,
            'anorganikPct' => $anorganikPct,
            'residuPct' => $residuPct,
            'diversionRate' => $diversionRate,
            'sevenDaysTrend' => $sevenDaysTrend,
            'maxTrendWeight' => $maxTrendWeight,
            'salesRevenue' => $salesRevenue,
            'pickupExpense' => $pickupExpense,
            'operationalExpense' => $operationalExpense,
            'saldoKas' => $saldoKas,
            'alerts' => $alerts,
            'recentSessions' => $recentSessions,
            'recentSales' => $recentSales,
            'recentPickups' => $recentPickups,
            'recentExpenses' => $recentExpenses,
        ];
    }
}; ?>

<div>
    <!-- Topbar Header -->
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Dashboard Teknis</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase tracking-wider">
                        PS2 UAD
                    </span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $activeCampus ? $activeCampus->name : 'Semua Kampus UAD (Pusat)' }} — Monitoring sirkular timbulan, pemilahan, logistik armada, dan buku kas terintegrasi.
                </p>
            </div>

            @if(Auth::user()->roles->isNotEmpty())
                <div class="flex items-center">
                    <span class="inline-flex items-center px-2.5 py-1.5 rounded-lg bg-slate-100 border border-slate-200 text-[11px] font-semibold text-slate-600 uppercase tracking-wide">
                        {{ str_replace('_', ' ', Auth::user()->roles->first()->name) }}
                    </span>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 1. Executive Primary KPI Grid (6 Cards Clean Dribbble Style) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3.5">
                    
                    <!-- KPI 1: Timbangan Hari Ini -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Timbang Hari Ini</span>
                        <div class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($todayWeighedKg, 1, ',', '.') }}</span>
                        <span class="text-[11px] text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 truncate">Sampah masuk hari ini</p>
                </div>

                <!-- KPI 2: Total Akumulasi Masuk -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-teal-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Akumulasi</span>
                        <div class="w-6 h-6 rounded-md bg-teal-50 text-teal-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($totalAllWeighedKg, 1, ',', '.') }}</span>
                        <span class="text-[11px] text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 truncate">{{ number_format($totalAllVolumeM3, 2, ',', '.') }} m³ volume</p>
                </div>

                <!-- KPI 3: Stok Terpilah Anorganik -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Stok Terpilah</span>
                        <div class="w-6 h-6 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($sellableStockKg, 1, ',', '.') }}</span>
                        <span class="text-[11px] text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[10px] text-sky-600 font-medium mt-1 truncate">Siap jual ke pengepul</p>
                </div>

                <!-- KPI 4: Stok Residu TPA -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Stok Residu</span>
                        <div class="w-6 h-6 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($residualStockKg, 1, ',', '.') }}</span>
                        <span class="text-[11px] text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[10px] text-amber-600 font-medium mt-1 truncate">Menunggu armada vendor</p>
                </div>

                <!-- KPI 5: Tingkat Pengalihan TPA (Diversion Rate) -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-indigo-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Diversion Rate</span>
                        <div class="w-6 h-6 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-0.5">
                        <span class="font-mono text-xl font-bold text-indigo-700">{{ $diversionRate }}</span>
                        <span class="text-xs text-indigo-500 font-bold">%</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 truncate">Teralihkan dari TPA Piyungan</p>
                </div>

                <!-- KPI 6: Saldo Kas Sirkular Realtime -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-600"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Saldo Kas Sirkular</span>
                        <div class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="text-[10px] font-semibold text-slate-400">Rp</span>
                        <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($saldoKas, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[10px] text-emerald-600 font-medium mt-1 truncate">Kas operasional aktif</p>
                </div>

            </div>

            <!-- 2. Interactive Charts & Analytical Visuals (2-Columns: Tren Timbulan 7 Hari vs Komposisi Sampah & Finansial) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                
                <!-- Chart Kolom Kiri: Tren Timbulan 7 Hari Terakhir (Histogram Bar Bersih) -->
                <div class="lg:col-span-7 bg-white border border-slate-200 rounded-xl p-5 shadow-2xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                <span>Tren Timbulan Sampah Masuk</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600">7 Hari Terakhir</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">Grafik volume bobot sampah masuk harian di TPS Kampus</p>
                        </div>
                        <span class="text-[11px] font-mono font-bold text-slate-700 bg-slate-50 px-2.5 py-1 rounded-md border border-slate-200">
                            Puncak: {{ number_format($maxTrendWeight, 1, ',', '.') }} kg
                        </span>
                    </div>

                    <!-- Visual Histogram Bars -->
                    <div class="pt-6 pb-2">
                        <div class="h-44 flex items-end justify-between gap-2 sm:gap-4 px-2">
                            @foreach($sevenDaysTrend as $trend)
                                @php
                                    $heightPercent = $maxTrendWeight > 0 ? max(6, round(($trend['weight_kg'] / $maxTrendWeight) * 100)) : 6;
                                    $isToday = $trend['date'] === Carbon::today()->format('Y-m-d');
                                @endphp
                                <div class="flex-1 flex flex-col items-center gap-2 group h-full justify-end">
                                    <!-- Tooltip hover popup detail nominal -->
                                    <div class="opacity-0 group-hover:opacity-100 transition-all duration-150 transform group-hover:-translate-y-1 text-center px-2.5 py-1.5 rounded-xl bg-slate-900/95 text-white whitespace-nowrap shadow-xl pointer-events-none mb-1.5 border border-slate-700/80 relative z-30">
                                        <div class="text-[9px] text-slate-300 font-medium">{{ $trend['day_name'] ?? $trend['short_day'] }}, {{ Carbon::parse($trend['date'])->translatedFormat('d M Y') }}</div>
                                        <div class="text-[11px] font-mono font-bold text-emerald-400">{{ number_format($trend['weight_kg'], 1, ',', '.') }} kg</div>
                                        <!-- Bottom pointer arrow -->
                                        <div class="absolute left-1/2 -bottom-1 -translate-x-1/2 w-1.5 h-1.5 bg-slate-900/95 rotate-45 border-r border-b border-slate-700/80"></div>
                                    </div>
                                    
                                    <!-- Bar Column -->
                                    <div class="w-full max-w-[42px] rounded-t-md transition-all duration-300 {{ $isToday ? 'bg-gradient-to-t from-emerald-600 to-emerald-400 shadow-xs' : 'bg-slate-200 hover:bg-emerald-300' }}"
                                         style="height: {{ $heightPercent }}%;">
                                    </div>

                                    <!-- Label Tanggal & Hari -->
                                    <div class="text-center mt-1">
                                        <span class="block text-[11px] font-bold {{ $isToday ? 'text-emerald-700' : 'text-slate-600' }}">
                                            {{ $trend['short_day'] }}
                                        </span>
                                        <span class="block text-[10px] text-slate-400 font-mono">
                                            {{ $trend['day_num'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Legend Info -->
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-xs bg-emerald-500"></span>
                                Hari Ini
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-xs bg-slate-200"></span>
                                Hari Sebelumnya
                            </span>
                        </div>
                        <span class="text-[10px] text-slate-400">Data otomatis terupdate dari penimbangan</span>
                    </div>
                </div>

                <!-- Chart Kolom Kanan: Komposisi Sampah & Kesehatan Sirkular Finansial -->
                <div class="lg:col-span-5 space-y-4">
                    
                    <!-- Card Komposisi Sampah (Stacked Segmented Bar) -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="font-bold text-sm text-slate-900">Komposisi Sampah Kampus</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Proporsi sampah masuk berdasarkan kategori</p>
                            </div>
                            <span class="text-xs font-mono font-bold text-slate-700">
                                {{ number_format($totalCompositionKg, 1, ',', '.') }} kg
                            </span>
                        </div>

                        <!-- Horizontal Stacked Progress Bar -->
                        <div class="w-full h-4 rounded-full overflow-hidden bg-slate-100 flex mt-3 shadow-inner">
                            @if($totalCompositionKg > 0)
                                <div style="width: {{ $organikPct }}%;" class="bg-emerald-500 transition-all duration-500 hover:opacity-90" title="Organik: {{ $organikPct }}%"></div>
                                <div style="width: {{ $anorganikPct }}%;" class="bg-sky-500 transition-all duration-500 hover:opacity-90" title="Anorganik: {{ $anorganikPct }}%"></div>
                                <div style="width: {{ $residuPct }}%;" class="bg-amber-500 transition-all duration-500 hover:opacity-90" title="Residu: {{ $residuPct }}%"></div>
                            @else
                                <div class="w-full bg-slate-200"></div>
                            @endif
                        </div>

                        <!-- Breakdown Legend -->
                        <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                            <div class="p-2.5 rounded-lg bg-emerald-50/60 border border-emerald-100">
                                <span class="block text-[10px] font-bold text-emerald-800 uppercase tracking-wider">Organik</span>
                                <span class="font-mono text-xs font-bold text-slate-900 mt-0.5 block">{{ number_format($organikKg, 1, ',', '.') }} kg</span>
                                <span class="text-[10px] font-bold text-emerald-600 font-mono">{{ $organikPct }}%</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-sky-50/60 border border-sky-100">
                                <span class="block text-[10px] font-bold text-sky-800 uppercase tracking-wider">Anorganik</span>
                                <span class="font-mono text-xs font-bold text-slate-900 mt-0.5 block">{{ number_format($anorganikKg, 1, ',', '.') }} kg</span>
                                <span class="text-[10px] font-bold text-sky-600 font-mono">{{ $anorganikPct }}%</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-amber-50/60 border border-amber-100">
                                <span class="block text-[10px] font-bold text-amber-800 uppercase tracking-wider">Residu</span>
                                <span class="font-mono text-xs font-bold text-slate-900 mt-0.5 block">{{ number_format($residuKg, 1, ',', '.') }} kg</span>
                                <span class="text-[10px] font-bold text-amber-600 font-mono">{{ $residuPct }}%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Neraca Sirkular Finansial -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-bold text-xs text-slate-900">Neraca Sirkular Keuangan TPS</h4>
                            <a href="{{ route('finance') }}" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700">
                                Buku Kas &rarr;
                            </a>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-600 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    Penjualan Anorganik (Kredit)
                                </span>
                                <span class="font-mono font-bold text-emerald-700">+Rp {{ number_format($salesRevenue, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-600 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    Biaya Angkut Residu (Debet)
                                </span>
                                <span class="font-mono font-bold text-amber-700">-Rp {{ number_format($pickupExpense, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-600 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                    Operasional & Upah (Debet)
                                </span>
                                <span class="font-mono font-bold text-rose-700">-Rp {{ number_format($operationalExpense, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <span class="font-semibold text-slate-800">Saldo Kas Bersih</span>
                                <span class="font-mono font-bold text-slate-900 text-sm">Rp {{ number_format($saldoKas, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- 3. Modul Operasional Pengelolaan Sampah (Quick Actions) -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-2xs p-5">
                <div class="mb-3.5 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Akses Cepat Modul Pengelolaan</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Navigasi langsung ke seluruh unit kerja operasional</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
                    <a href="{{ route('weighing') }}" class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-emerald-400 hover:shadow-xs transition duration-150 block group">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800 group-hover:text-emerald-700">Penimbangan</span>
                            <span class="w-5 h-5 rounded-md bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px] font-bold">
                                &rarr;
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1 leading-snug">Pencatatan sampah masuk per titik sumber.</p>
                    </a>

                    <a href="{{ route('sales') }}" class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-sky-400 hover:shadow-xs transition duration-150 block group">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800 group-hover:text-sky-700">Penjualan Bank Sampah</span>
                            <span class="w-5 h-5 rounded-md bg-sky-100 text-sky-800 flex items-center justify-center text-[10px] font-bold">
                                &rarr;
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1 leading-snug">Penyaluran anorganik & penerimaan kas.</p>
                    </a>

                    <a href="{{ route('pickups') }}" class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-amber-400 hover:shadow-xs transition duration-150 block group">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800 group-hover:text-amber-700">Pengangkutan Residu</span>
                            <span class="w-5 h-5 rounded-md bg-amber-100 text-amber-800 flex items-center justify-center text-[10px] font-bold">
                                &rarr;
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1 leading-snug">Pengangkutan residu ke TPA & debet biaya.</p>
                    </a>

                    <a href="{{ route('expenses') }}" class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-rose-400 hover:shadow-xs transition duration-150 block group">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800 group-hover:text-rose-700">Pengeluaran TPS</span>
                            <span class="w-5 h-5 rounded-md bg-rose-100 text-rose-800 flex items-center justify-center text-[10px] font-bold">
                                &rarr;
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1 leading-snug">Upah, konsumsi, karung, pakan & alat TPS.</p>
                    </a>

                    <a href="{{ route('finance') }}" class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-emerald-400 hover:shadow-xs transition duration-150 block group">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800 group-hover:text-emerald-700">Buku Kas & Besar</span>
                            <span class="w-5 h-5 rounded-md bg-emerald-100 text-emerald-800 flex items-center justify-center text-[10px] font-bold">
                                &rarr;
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1 leading-snug">Jurnal mutasi & saldo harian per kampus.</p>
                    </a>

                    <a href="{{ route('kap') }}" class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-indigo-400 hover:shadow-xs transition duration-150 block group">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-xs text-slate-800 group-hover:text-indigo-700">Survei Perilaku (KAP)</span>
                            <span class="w-5 h-5 rounded-md bg-indigo-100 text-indigo-800 flex items-center justify-center text-[10px] font-bold">
                                &rarr;
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1 leading-snug">Indeks kesadaran & evaluasi pemilahan civitas.</p>
                    </a>
                </div>
            </div>

            <!-- 4. Unified Tabbed Feed Aktivitas Operasional Terkini -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Aktivitas Operasional Terkini</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Catatan transaksi dan pergerakan data paling baru di sistem</p>
                    </div>

                    <!-- Tab Buttons Filter -->
                    <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-lg">
                        <button wire:click="setActivityTab('weighing')" 
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition {{ $activeActivityTab === 'weighing' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Timbangan
                        </button>
                        <button wire:click="setActivityTab('sales')" 
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition {{ $activeActivityTab === 'sales' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Penjualan
                        </button>
                        <button wire:click="setActivityTab('pickups')" 
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition {{ $activeActivityTab === 'pickups' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Pengangkutan
                        </button>
                        <button wire:click="setActivityTab('expenses')" 
                                class="px-2.5 py-1 rounded-md text-xs font-semibold transition {{ $activeActivityTab === 'expenses' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Pengeluaran
                        </button>
                    </div>
                </div>

                <!-- Tab Content 1: Timbangan -->
                @if($activeActivityTab === 'weighing')
                    <div class="divide-y divide-slate-100">
                        @forelse ($recentSessions as $session)
                            <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xs shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-800">{{ $session->campus->name }} &bull; {{ $session->wasteSource?->name ?? 'TPS Utama' }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $session->weigh_date->format('d M Y') }} &bull; Petugas: {{ $session->creator?->name }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono text-xs font-bold text-slate-900">{{ number_format($session->total_weight, 1, ',', '.') }} kg</span>
                                    <span class="block text-[10px] text-slate-400">{{ $session->items->count() }} jenis sampah</span>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-slate-400">Belum ada riwayat penimbangan tercatat.</div>
                        @endforelse
                    </div>
                    <div class="p-3 bg-slate-50/60 border-t border-slate-100 text-center">
                        <a href="{{ route('weighing') }}" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700">
                            Buka Modul Penimbangan &rarr;
                        </a>
                    </div>
                @endif

                <!-- Tab Content 2: Penjualan -->
                @if($activeActivityTab === 'sales')
                    <div class="divide-y divide-slate-100">
                        @forelse ($recentSales as $sale)
                            <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center font-bold text-xs shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-800">{{ $sale->campus->name }} &bull; {{ $sale->buyer?->name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $sale->sale_date->format('d M Y') }} &bull; Pembeli: {{ $sale->buyer?->name }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono text-xs font-bold text-emerald-600">+Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</span>
                                    <span class="block text-[10px] text-slate-400">{{ $sale->items->count() }} jenis anorganik</span>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-slate-400">Belum ada riwayat penjualan anorganik.</div>
                        @endforelse
                    </div>
                    <div class="p-3 bg-slate-50/60 border-t border-slate-100 text-center">
                        <a href="{{ route('sales') }}" class="text-xs font-semibold text-sky-600 hover:text-sky-700">
                            Buka Modul Penjualan &rarr;
                        </a>
                    </div>
                @endif

                <!-- Tab Content 3: Pengangkutan -->
                @if($activeActivityTab === 'pickups')
                    <div class="divide-y divide-slate-100">
                        @forelse ($recentPickups as $pickup)
                            <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center font-bold text-xs shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-800">{{ $pickup->campus->name }} &bull; {{ $pickup->vendor?->name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $pickup->pickup_date->format('d M Y') }} &bull; Nopol: {{ $pickup->driver_vehicle ?? '-' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono text-xs font-bold text-amber-700">{{ number_format($pickup->volume_kg, 1, ',', '.') }} kg</span>
                                    <span class="block text-[10px] text-rose-600 font-mono">-Rp {{ number_format($pickup->total_cost, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-slate-400">Belum ada riwayat pengangkutan residu.</div>
                        @endforelse
                    </div>
                    <div class="p-3 bg-slate-50/60 border-t border-slate-100 text-center">
                        <a href="{{ route('pickups') }}" class="text-xs font-semibold text-amber-600 hover:text-amber-700">
                            Buka Modul Pengangkutan &rarr;
                        </a>
                    </div>
                @endif

                <!-- Tab Content 4: Pengeluaran -->
                @if($activeActivityTab === 'expenses')
                    <div class="divide-y divide-slate-100">
                        @forelse ($recentExpenses as $expense)
                            <div class="p-3.5 flex items-center justify-between hover:bg-slate-50 transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center font-bold text-xs shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-800">{{ $expense->campus->name }} &bull; {{ $expense->category?->name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $expense->expense_date->format('d M Y') }} &bull; {{ $expense->description }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono text-xs font-bold text-rose-600">-Rp {{ number_format($expense->amount, 0, ',', '.') }}</span>
                                    <span class="block text-[10px] text-slate-400">Operasional TPS</span>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-slate-400">Belum ada riwayat pengeluaran operasional.</div>
                        @endforelse
                    </div>
                    <div class="p-3 bg-slate-50/60 border-t border-slate-100 text-center">
                        <a href="{{ route('expenses') }}" class="text-xs font-semibold text-rose-600 hover:text-rose-700">
                            Buka Modul Pengeluaran &rarr;
                        </a>
                    </div>
                @endif

            </div>


        </div>
    </div>
</div>
