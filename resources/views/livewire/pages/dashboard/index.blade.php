<?php

use App\Models\BukuBesar;
use App\Models\Campus;
use App\Models\Expense;
use App\Models\KapSurvey;
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
    public string $dashboardMode = 'technical'; // 'technical' (Dashboard Teknis) or 'kap' (Dashboard KAP)
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

    public function setDashboardMode(string $mode): void
    {
        $this->dashboardMode = $mode;
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

        $categoryWeights = $compositionQuery->pluck('total_kg', 'category')->toArray();
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

        // SRS M1 (b) & M11: Dashboard KAP Metrics
        $kapQuery = KapSurvey::when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId));
        $kapTotal = (clone $kapQuery)->count();
        $kapAvgKnowledge = $kapTotal > 0 ? round((float) (clone $kapQuery)->avg('knowledge_score'), 1) : 0.0;
        $kapAvgAttitude = $kapTotal > 0 ? round((float) (clone $kapQuery)->avg('attitude_score'), 1) : 0.0;
        $kapAvgPractice = $kapTotal > 0 ? round((float) (clone $kapQuery)->avg('practice_score'), 1) : 0.0;
        $kapAvgSatisfaction = $kapTotal > 0 ? round((float) (clone $kapQuery)->avg('satisfaction_score'), 1) : 0.0;
        $kapAvgOverall = $kapTotal > 0 ? round((float) (clone $kapQuery)->avg('overall_score'), 1) : 0.0;
        $kapVolunteerPct = $kapTotal > 0 ? round(((clone $kapQuery)->where('is_willing_volunteer', true)->count() / $kapTotal) * 100, 1) : 0.0;
        $kapTrainingPct = $kapTotal > 0 ? round(((clone $kapQuery)->where('has_attended_training', true)->count() / $kapTotal) * 100, 1) : 0.0;

        // Perbandingan Antar Kampus untuk Dashboard KAP (saat Semua Kampus dipilih)
        $campusKapComparison = [];
        if (!$campusQueryId) {
            foreach ($campuses as $c) {
                $cSurveys = KapSurvey::where('campus_id', $c->id)->get();
                $cCount = $cSurveys->count();
                $campusKapComparison[] = [
                    'campus' => $c->name,
                    'count' => $cCount,
                    'overall' => $cCount > 0 ? round($cSurveys->avg('overall_score'), 1) : 0.0,
                    'knowledge' => $cCount > 0 ? round($cSurveys->avg('knowledge_score'), 1) : 0.0,
                    'attitude' => $cCount > 0 ? round($cSurveys->avg('attitude_score'), 1) : 0.0,
                    'practice' => $cCount > 0 ? round($cSurveys->avg('practice_score'), 1) : 0.0,
                    'satisfaction' => $cCount > 0 ? round($cSurveys->avg('satisfaction_score'), 1) : 0.0,
                ];
            }
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
            'kapTotal' => $kapTotal,
            'kapAvgKnowledge' => $kapAvgKnowledge,
            'kapAvgAttitude' => $kapAvgAttitude,
            'kapAvgPractice' => $kapAvgPractice,
            'kapAvgSatisfaction' => $kapAvgSatisfaction,
            'kapAvgOverall' => $kapAvgOverall,
            'kapVolunteerPct' => $kapVolunteerPct,
            'kapTrainingPct' => $kapTrainingPct,
            'campusKapComparison' => $campusKapComparison,
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
                    <span>Ringkasan Eksekutif Pengelolaan Sampah</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase tracking-wider">
                        PS2 UAD
                    </span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Monitoring sirkular timbulan, pemilahan, logistik armada, dan buku kas terintegrasi.
                </p>
            </div>

            <!-- Unit Kampus Selector & Role Badge -->
            <div class="flex flex-wrap items-center gap-2.5">
                @if($isSuperAdmin)
                    <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 shadow-xs">
                        <span class="text-[11px] font-semibold text-slate-500">Unit:</span>
                        <select wire:model.live="selectedCampusId" class="text-xs font-semibold text-slate-800 bg-transparent border-0 focus:ring-0 p-0 cursor-pointer">
                            <option value="">Semua Kampus (Pusat UAD)</option>
                            @foreach($campuses as $campus)
                                <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="text-xs font-semibold text-slate-700 bg-white border border-slate-200 px-3 py-1.5 rounded-lg">
                        {{ $activeCampus ? $activeCampus->name : 'Unit Kampus Terdaftar' }}
                    </div>
                @endif

                @if(Auth::user()->roles->isNotEmpty())
                    <span class="inline-flex items-center px-2.5 py-1.5 rounded-lg bg-slate-100 border border-slate-200 text-[11px] font-semibold text-slate-600 uppercase tracking-wide">
                        {{ str_replace('_', ' ', Auth::user()->roles->first()->name) }}
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Switcher: (a) Dashboard Teknis vs (b) Dashboard KAP (SRS M1) -->
            <div class="bg-white rounded-2xl p-1.5 border border-slate-200 shadow-2xs flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1.5">
                    <button type="button" 
                            wire:click="setDashboardMode('technical')" 
                            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $dashboardMode === 'technical' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span>(a) Dashboard Teknis Operasional</span>
                    </button>

                    <button type="button" 
                            wire:click="setDashboardMode('kap')" 
                            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $dashboardMode === 'kap' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>(b) Dashboard Survei KAP</span>
                    </button>
                </div>

                <div class="px-3 text-[11px] text-slate-500 font-medium">
                    {{ $activeCampus ? $activeCampus->name : 'Semua Kampus UAD (Pusat)' }}
                </div>
            </div>

            @if($dashboardMode === 'technical')
                <!-- M10 — Alerts & Notification Banners -->
                @if(!empty($alerts))
                    <div class="space-y-2.5">
                        @foreach($alerts as $alert)
                            <div class="p-3.5 rounded-2xl border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-2xs {{ $alert['type'] === 'danger' ? 'bg-rose-50/70 border-rose-200 text-rose-900' : ($alert['type'] === 'warning' ? 'bg-amber-50/70 border-amber-200 text-amber-900' : 'bg-emerald-50/70 border-emerald-200 text-emerald-900') }}">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 {{ $alert['type'] === 'danger' ? 'bg-rose-100 text-rose-700' : ($alert['type'] === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">
                                        @if($alert['type'] === 'danger')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        @elseif($alert['type'] === 'warning')
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold">{{ $alert['title'] }}</h4>
                                        <p class="text-[11px] opacity-90">{{ $alert['message'] }}</p>
                                    </div>
                                </div>
                                <a href="{{ $alert['action_url'] }}" class="px-3.5 py-1.5 rounded-xl bg-white text-xs font-bold border border-slate-200 hover:bg-slate-50 transition shrink-0 text-center shadow-2xs">
                                    {{ $alert['action_label'] }} &rarr;
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif

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
                                    <!-- Tooltip hover nominal -->
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-150 text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-800 text-white whitespace-nowrap shadow-xs pointer-events-none mb-1">
                                        {{ number_format($trend['weight_kg'], 1, ',', '.') }} kg
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

            @endif {{-- end dashboardMode === 'technical' --}}

            @if($dashboardMode === 'kap')
                <!-- (b) Dashboard Survei KAP (Knowledge, Attitude, Practice) — SRS M1 & M11 -->
                
                <!-- KAP Header & Quick Action -->
                <div class="bg-gradient-to-r from-emerald-800 to-teal-900 rounded-2xl p-6 text-white shadow-xs relative overflow-hidden flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="relative z-10 max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-[11px] font-bold tracking-wide uppercase mb-2 border border-emerald-400/20">
                            <span>Modul M11 — Survei KAP Civitas Akademika</span>
                        </div>
                        <h3 class="text-xl font-bold tracking-tight text-white">Indeks Perilaku Pemilahan Sampah Kampus UAD</h3>
                        <p class="text-xs text-emerald-100/90 mt-1">
                            Monitoring komprehensif tingkat pengetahuan, sikap, praktik nyata pemilahan, dan kepuasan fasilitas persampahan seluruh civitas akademika Universitas Ahmad Dahlan.
                        </p>
                    </div>
                    <div class="relative z-10 flex flex-wrap items-center gap-2.5 shrink-0">
                        <a href="{{ route('public.survey') }}" target="_blank" class="px-4 py-2.5 rounded-xl bg-white text-emerald-900 hover:bg-emerald-50 text-xs font-bold transition shadow-xs flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            <span>Buka Form Kuesioner</span>
                        </a>
                        <a href="{{ route('kap') }}" class="px-4 py-2.5 rounded-xl bg-emerald-700/60 hover:bg-emerald-700 text-white text-xs font-bold transition border border-emerald-500/30 flex items-center gap-2">
                            <span>Modul Analisis KAP &rarr;</span>
                        </a>
                    </div>
                </div>

                <!-- KAP 6 Primary KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3.5">
                    
                    <!-- KPI 1: Total Responden -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Responden</span>
                            <div class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                        </div>
                        <div class="mt-2.5 flex items-baseline gap-1">
                            <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($kapTotal, 0, ',', '.') }}</span>
                            <span class="text-[11px] text-slate-500 font-medium">orang</span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1 truncate">Partisipasi civitas</p>
                    </div>

                    <!-- KPI 2: Indeks Rata-rata KAP -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-teal-500"></div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Indeks Skor KAP</span>
                            <div class="w-6 h-6 rounded-md bg-teal-50 text-teal-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                        <div class="mt-2.5 flex items-baseline gap-1">
                            <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($kapAvgOverall, 1, ',', '.') }}</span>
                            <span class="text-[11px] text-slate-500 font-medium">/ 100</span>
                        </div>
                        <p class="text-[10px] text-teal-600 font-medium mt-1 truncate">
                            {{ $kapAvgOverall >= 80 ? 'Sangat Baik' : ($kapAvgOverall >= 60 ? 'Cukup / Sedang' : 'Perlu Peningkatan') }}
                        </p>
                    </div>

                    <!-- KPI 3: Pengetahuan (Knowledge) -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pengetahuan</span>
                            <div class="w-6 h-6 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            </div>
                        </div>
                        <div class="mt-2.5 flex items-baseline gap-1">
                            <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($kapAvgKnowledge, 1, ',', '.') }}</span>
                            <span class="text-[11px] text-slate-500 font-medium">/ 100</span>
                        </div>
                        <p class="text-[10px] text-sky-600 font-medium mt-1 truncate">Dimensi Knowledge</p>
                    </div>

                    <!-- KPI 4: Sikap (Attitude) -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-violet-500"></div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sikap</span>
                            <div class="w-6 h-6 rounded-md bg-violet-50 text-violet-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            </div>
                        </div>
                        <div class="mt-2.5 flex items-baseline gap-1">
                            <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($kapAvgAttitude, 1, ',', '.') }}</span>
                            <span class="text-[11px] text-slate-500 font-medium">/ 100</span>
                        </div>
                        <p class="text-[10px] text-violet-600 font-medium mt-1 truncate">Dimensi Attitude</p>
                    </div>

                    <!-- KPI 5: Perilaku (Practice) -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Perilaku Nyata</span>
                            <div class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        </div>
                        <div class="mt-2.5 flex items-baseline gap-1">
                            <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($kapAvgPractice, 1, ',', '.') }}</span>
                            <span class="text-[11px] text-slate-500 font-medium">/ 100</span>
                        </div>
                        <p class="text-[10px] text-emerald-600 font-medium mt-1 truncate">Dimensi Practice</p>
                    </div>

                    <!-- KPI 6: Kepuasan Fasilitas -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden transition hover:shadow-xs">
                        <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kepuasan Fasilitas</span>
                            <div class="w-6 h-6 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                        <div class="mt-2.5 flex items-baseline gap-1">
                            <span class="font-mono text-xl font-bold text-slate-900">{{ number_format($kapAvgSatisfaction, 1, ',', '.') }}</span>
                            <span class="text-[11px] text-slate-500 font-medium">/ 100</span>
                        </div>
                        <p class="text-[10px] text-amber-600 font-medium mt-1 truncate">Evaluasi TPS3R</p>
                    </div>

                </div>

                <!-- 2-Column Section: 4 Dimensions Breakdown & Campus Comparison -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <!-- Col 1: Analisis 4 Dimensi & Keterlibatan -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs space-y-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Pencapaian 4 Dimensi KAP</h3>
                                <p class="text-xs text-slate-400">Evaluasi rata-rata skor per pilar kuesioner</p>
                            </div>
                            <span class="text-xs font-mono font-bold text-emerald-600">{{ number_format($kapAvgOverall, 1) }}%</span>
                        </div>

                        <!-- Progress Bars -->
                        <div class="space-y-4 pt-1">
                            <!-- Knowledge -->
                            <div>
                                <div class="flex justify-between text-xs font-semibold mb-1.5">
                                    <span class="text-slate-700 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                                        Pengetahuan (Knowledge)
                                    </span>
                                    <span class="font-mono text-slate-900">{{ number_format($kapAvgKnowledge, 1) }}%</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                    <div class="bg-sky-500 h-2 rounded-full transition-all duration-500" style="width: {{ min(100, $kapAvgKnowledge) }}%"></div>
                                </div>
                            </div>

                            <!-- Attitude -->
                            <div>
                                <div class="flex justify-between text-xs font-semibold mb-1.5">
                                    <span class="text-slate-700 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                                        Sikap (Attitude)
                                    </span>
                                    <span class="font-mono text-slate-900">{{ number_format($kapAvgAttitude, 1) }}%</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                    <div class="bg-violet-500 h-2 rounded-full transition-all duration-500" style="width: {{ min(100, $kapAvgAttitude) }}%"></div>
                                </div>
                            </div>

                            <!-- Practice -->
                            <div>
                                <div class="flex justify-between text-xs font-semibold mb-1.5">
                                    <span class="text-slate-700 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        Perilaku Praktik (Practice)
                                    </span>
                                    <span class="font-mono text-slate-900">{{ number_format($kapAvgPractice, 1) }}%</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                    <div class="bg-emerald-500 h-2 rounded-full transition-all duration-500" style="width: {{ min(100, $kapAvgPractice) }}%"></div>
                                </div>
                            </div>

                            <!-- Satisfaction -->
                            <div>
                                <div class="flex justify-between text-xs font-semibold mb-1.5">
                                    <span class="text-slate-700 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        Kepuasan Fasilitas (Satisfaction)
                                    </span>
                                    <span class="font-mono text-slate-900">{{ number_format($kapAvgSatisfaction, 1) }}%</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                    <div class="bg-amber-500 h-2 rounded-full transition-all duration-500" style="width: {{ min(100, $kapAvgSatisfaction) }}%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Keterlibatan Civitas (SRS M11: % Relawan & % Sosialisasi) -->
                        <div class="pt-3 border-t border-slate-100 grid grid-cols-2 gap-3">
                            <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                                <p class="text-[10px] uppercase font-bold text-slate-400">Kesediaan Relawan</p>
                                <p class="text-lg font-mono font-bold text-emerald-700 mt-0.5">{{ number_format($kapVolunteerPct, 1) }}%</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">Siap menjadi kader pemilahan</p>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
                                <p class="text-[10px] uppercase font-bold text-slate-400">Pernah Sosialisasi</p>
                                <p class="text-lg font-mono font-bold text-teal-700 mt-0.5">{{ number_format($kapTrainingPct, 1) }}%</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">Telah teredukasi program TPS</p>
                            </div>
                        </div>
                    </div>

                    <!-- Col 2: Perbandingan Antar Kampus (SRS M1 & M11) -->
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">Perbandingan Indeks Antar Kampus</h3>
                                    <p class="text-xs text-slate-400">Distribusi skor KAP per unit lokasi kampus UAD</p>
                                </div>
                                <span class="text-[11px] px-2.5 py-1 rounded-md bg-slate-100 text-slate-600 font-medium">
                                    {{ count($campusKapComparison) }} Kampus
                                </span>
                            </div>

                            @if(!empty($campusKapComparison))
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs text-left">
                                        <thead>
                                            <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase text-[10px]">
                                                <th class="pb-2.5">Kampus</th>
                                                <th class="pb-2.5 text-center">Responden</th>
                                                <th class="pb-2.5 text-right">Skor Total</th>
                                                <th class="pb-2.5 text-right">Kategori</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach($campusKapComparison as $row)
                                                <tr class="hover:bg-slate-50/80 transition">
                                                    <td class="py-2.5 font-semibold text-slate-800">{{ $row['campus'] }}</td>
                                                    <td class="py-2.5 text-center font-mono text-slate-600">{{ $row['count'] }}</td>
                                                    <td class="py-2.5 text-right font-mono font-bold {{ $row['overall'] >= 80 ? 'text-emerald-600' : ($row['overall'] >= 60 ? 'text-amber-600' : 'text-rose-600') }}">
                                                        {{ number_format($row['overall'], 1) }}
                                                    </td>
                                                    <td class="py-2.5 text-right">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $row['overall'] >= 80 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($row['overall'] >= 60 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200') }}">
                                                            {{ $row['overall'] >= 80 ? 'Tinggi' : ($row['overall'] >= 60 ? 'Sedang' : 'Rendah') }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="p-8 text-center text-xs text-slate-400">
                                    Pilih filter "Semua Kampus" di bagian atas untuk melihat komparasi matriks 6 kampus UAD secara serentak.
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                            <span class="text-slate-400 text-[11px]">Formula KAP terkalibrasi Standar BSI UAD</span>
                            <a href="{{ route('kap') }}" class="font-bold text-emerald-600 hover:text-emerald-700 transition">
                                Buka Detail Responden &rarr;
                            </a>
                        </div>
                    </div>

                </div>

            @endif {{-- end dashboardMode === 'kap' --}}

        </div>
    </div>
</div>
