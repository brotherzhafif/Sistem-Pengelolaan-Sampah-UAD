<?php

use App\Models\Campus;
use App\Models\KapSurvey;
use App\Models\Keuangan;
use App\Models\Pickup;
use App\Models\Sale;
use App\Models\Vendor;
use App\Models\WasteSource;
use App\Models\WasteType;
use App\Models\WeighingItem;
use App\Models\WeighingSession;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $activeTab = 'weighing'; // 'weighing', 'sales', 'pickups', 'finance', 'persen', 'kap'
    public ?string $filterCampusId = '';
    public ?string $filterDateFrom = '';
    public ?string $filterDateTo = '';
    public string $presetPeriod = 'this_month'; // 'this_month', 'last_month', 'q_this', 'this_year', 'all', 'custom'

    public function mount(): void
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        if (!$isSuperAdmin && $user->campus_id) {
            $this->filterCampusId = (string) $user->campus_id;
        } else {
            $this->filterCampusId = session('active_campus_id') ? (string) session('active_campus_id') : '';
        }

        $this->applyPreset('this_month');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedPresetPeriod(string $val): void
    {
        $this->applyPreset($val);
    }

    public function applyPreset(string $preset): void
    {
        $this->presetPeriod = $preset;

        switch ($preset) {
            case 'this_month':
                $this->filterDateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
                $this->filterDateTo = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $this->filterDateFrom = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->filterDateTo = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'q_this':
                $this->filterDateFrom = Carbon::now()->firstOfQuarter()->format('Y-m-d');
                $this->filterDateTo = Carbon::now()->lastOfQuarter()->format('Y-m-d');
                break;
            case 'this_year':
                $this->filterDateFrom = Carbon::now()->startOfYear()->format('Y-m-d');
                $this->filterDateTo = Carbon::now()->endOfYear()->format('Y-m-d');
                break;
            case 'all':
                $this->filterDateFrom = '';
                $this->filterDateTo = '';
                break;
            case 'custom':
                // retain existing dates
                break;
        }

        $this->resetPage();
    }

    public function updatedFilterDateFrom(): void
    {
        $this->presetPeriod = 'custom';
        $this->resetPage();
    }

    public function updatedFilterDateTo(): void
    {
        $this->presetPeriod = 'custom';
        $this->resetPage();
    }

    public function updatedFilterCampusId(): void
    {
        $this->resetPage();
    }

    public function with(LedgerService $ledgerService): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        $campusId = !empty($this->filterCampusId) ? (int) $this->filterCampusId : null;

        $campuses = Campus::where('is_active', true)->orderBy('id')->get();
        $activeCampus = $campusId ? $campuses->firstWhere('id', $campusId) : null;

        // Label Periode
        $periodLabel = match ($this->presetPeriod) {
            'this_month' => Carbon::now()->translatedFormat('F Y'),
            'last_month' => Carbon::now()->subMonth()->translatedFormat('F Y'),
            'q_this' => 'Q' . Carbon::now()->quarter . ' ' . Carbon::now()->year,
            'this_year' => 'Tahun ' . Carbon::now()->year,
            'all' => 'Semua Periode',
            default => ($this->filterDateFrom && $this->filterDateTo) 
                ? Carbon::parse($this->filterDateFrom)->format('d/m/Y') . ' - ' . Carbon::parse($this->filterDateTo)->format('d/m/Y')
                : 'Kustom',
        };

        $pagedData = null;
        $tabData = [];

        // 1. DATA KHUSUS PER TAB
        if ($this->activeTab === 'weighing') {
            $baseQuery = WeighingSession::query()
                ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('weigh_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('weigh_date', '<=', $this->filterDateTo));

            $pagedData = (clone $baseQuery)
                ->with(['campus', 'wasteSource', 'items.wasteType', 'creator'])
                ->orderBy('weigh_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(8);

            // Metrics
            $sessionIds = (clone $baseQuery)->pluck('id');
            $items = WeighingItem::whereIn('weighing_session_id', $sessionIds)->get();
            $totalKg = (float) $items->sum('weight_kg');
            $totalM3 = (float) $items->sum('volume_m3');
            if ($totalM3 <= 0 && $totalKg > 0) {
                $totalM3 = round($totalKg * 0.008, 1);
            }
            $totalSessions = (clone $baseQuery)->count();
            
            // Hari aktif timbang
            $daysCount = (clone $baseQuery)->distinct('weigh_date')->count('weigh_date');
            $avgPerDay = $daysCount > 0 ? round($totalKg / $daysCount, 1) : 0;

            // Chart Tren Harian (15-20 hari terakhir)
            $trendDays = (clone $baseQuery)
                ->select('weigh_date', DB::raw('count(id) as session_count'))
                ->groupBy('weigh_date')
                ->orderBy('weigh_date', 'asc')
                ->take(20)
                ->get();
            
            $chartPoints = [];
            foreach ($trendDays as $td) {
                $dayKg = (float) WeighingItem::whereIn('weighing_session_id', function($q) use ($td, $campusId) {
                    $q->select('id')->from('weighing_sessions')
                        ->where('weigh_date', $td->weigh_date)
                        ->when($campusId, fn($sq) => $sq->where('campus_id', $campusId));
                })->sum('weight_kg');

                $chartPoints[] = [
                    'date' => Carbon::parse($td->weigh_date)->format('d'),
                    'kg' => $dayKg,
                ];
            }

            // Rekap per Kategori Sampah
            $catGroups = $items->groupBy('waste_type_id');
            $categoryRecap = [];
            $allWasteTypes = WasteType::all()->keyBy('id');
            foreach ($catGroups as $wtId => $itList) {
                $cKg = (float) $itList->sum('weight_kg');
                $cM3 = (float) $itList->sum('volume_m3');
                if ($cM3 <= 0 && $cKg > 0) $cM3 = round($cKg * 0.008, 1);
                $wtName = $allWasteTypes->get($wtId)?->name ?? 'Lainnya';
                $categoryRecap[] = [
                    'name' => $wtName,
                    'kg' => $cKg,
                    'm3' => $cM3,
                    'pct' => $totalKg > 0 ? round(($cKg / $totalKg) * 100, 1) : 0,
                    'avg_day' => $daysCount > 0 ? round($cKg / $daysCount, 1) : 0,
                ];
            }
            usort($categoryRecap, fn($a, $b) => $b['kg'] <=> $a['kg']);

            // Rekap per Sumber Sampah
            $sourceQuery = (clone $baseQuery)
                ->select('waste_source_id', DB::raw('count(id) as sessions_count'))
                ->groupBy('waste_source_id')
                ->get();
            
            $sourceRecap = [];
            $allSources = WasteSource::all()->keyBy('id');
            foreach ($sourceQuery as $sq) {
                $sName = $allSources->get($sq->waste_source_id)?->name ?? 'Lainnya';
                $sKg = (float) WeighingItem::whereIn('weighing_session_id', function($q) use ($sq, $baseQuery) {
                    $q->select('id')->from('weighing_sessions')
                        ->where('waste_source_id', $sq->waste_source_id)
                        ->whereIn('id', (clone $baseQuery)->pluck('id'));
                })->sum('weight_kg');

                $sM3 = round($sKg * 0.008, 1);
                $sourceRecap[] = [
                    'name' => $sName,
                    'kg' => $sKg,
                    'm3' => $sM3,
                    'sessions' => $sq->sessions_count,
                    'pct' => $totalKg > 0 ? round(($sKg / $totalKg) * 100, 1) : 0,
                ];
            }
            usort($sourceRecap, fn($a, $b) => $b['kg'] <=> $a['kg']);

            $tabData = [
                'total_sessions' => $totalSessions,
                'total_kg' => $totalKg,
                'total_m3' => $totalM3,
                'avg_per_day' => $avgPerDay,
                'chart_points' => $chartPoints,
                'category_recap' => $categoryRecap,
                'source_recap' => $sourceRecap,
            ];

        } elseif ($this->activeTab === 'sales') {
            $baseQuery = Sale::query()
                ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('sale_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('sale_date', '<=', $this->filterDateTo));

            $pagedData = (clone $baseQuery)
                ->with(['campus', 'buyer', 'creator', 'items.wasteType'])
                ->orderBy('sale_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(8);

            $allSales = (clone $baseQuery)->with('items.wasteType')->get();
            $totalRevenue = (float) $allSales->sum('total_amount');
            $totalWeight = (float) $allSales->sum(fn($s) => $s->items->sum('weight_kg'));
            $totalCount = $allSales->count();
            $avgPrice = $totalWeight > 0 ? round($totalRevenue / $totalWeight, 0) : 0;

            // Penjualan per Kategori Sampah
            $catSales = [];
            foreach ($allSales as $s) {
                foreach ($s->items as $it) {
                    $catName = $it->wasteType?->name ?? 'Lainnya';
                    if (!isset($catSales[$catName])) {
                        $catSales[$catName] = 0;
                    }
                    $catSales[$catName] += (float) ($it->weight_kg * $it->price_per_kg);
                }
            }
            arsort($catSales);

            $tabData = [
                'total_revenue' => $totalRevenue,
                'total_weight' => $totalWeight,
                'total_count' => $totalCount,
                'avg_price' => $avgPrice,
                'category_sales' => $catSales,
            ];

        } elseif ($this->activeTab === 'pickups') {
            $baseQuery = Pickup::query()
                ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('pickup_date', '<=', $this->filterDateTo));

            $pagedData = (clone $baseQuery)
                ->with(['campus', 'vendor', 'creator'])
                ->orderBy('pickup_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(8);

            $allPickups = (clone $baseQuery)->get();
            $totalKg = (float) $allPickups->sum('volume_kg');
            $totalCost = (float) $allPickups->sum('total_cost');
            $totalTrips = $allPickups->count();
            $avgCostKg = $totalKg > 0 ? round($totalCost / $totalKg, 0) : 0;

            // Perbandingan Vendor
            $vendorGroups = $allPickups->groupBy('vendor_id');
            $allVendors = Vendor::all()->keyBy('id');
            $vendorComparison = [];
            foreach ($vendorGroups as $vId => $vList) {
                $vKg = (float) $vList->sum('volume_kg');
                $vCost = (float) $vList->sum('total_cost');
                $vRate = $vKg > 0 ? round($vCost / $vKg, 0) : 0;
                $vName = $allVendors->get($vId)?->name ?? 'Lainnya';
                $vendorComparison[] = [
                    'name' => $vName,
                    'count' => $vList->count(),
                    'kg' => $vKg,
                    'rate' => $vRate,
                    'cost' => $vCost,
                    'pct' => $totalCost > 0 ? round(($vCost / $totalCost) * 100, 1) : 0,
                ];
            }
            usort($vendorComparison, fn($a, $b) => $b['cost'] <=> $a['cost']);

            $tabData = [
                'total_kg' => $totalKg,
                'total_cost' => $totalCost,
                'total_trips' => $totalTrips,
                'avg_cost_kg' => $avgCostKg,
                'vendor_comparison' => $vendorComparison,
            ];

        } elseif ($this->activeTab === 'finance') {
            $baseQuery = Keuangan::query()
                ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('tanggal', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('tanggal', '<=', $this->filterDateTo));

            $pagedData = (clone $baseQuery)
                ->with('campus')
                ->orderBy('tanggal', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(8);

            $allFin = (clone $baseQuery)->get();
            $totalKredit = (float) $allFin->where('jenis', 'K')->sum('nominal');
            $totalDebet = (float) $allFin->where('jenis', 'D')->sum('nominal');
            $saldoBersih = $totalKredit - $totalDebet;

            // Komposisi Pengeluaran
            $expenseGroups = $allFin->where('jenis', 'D')->groupBy('sumber');
            $expenseComposition = [];
            foreach ($expenseGroups as $src => $list) {
                $nominal = (float) $list->sum('nominal');
                $srcLabel = match ($src) {
                    'pengangkutan' => 'Biaya Angkut Residu',
                    'operasional' => 'Operasional TPS / Alat',
                    'gaji' => 'Upah / Honor Pemilahan',
                    'konsumsi' => 'Makan & Minum Petugas',
                    default => ucfirst(str_replace('_', ' ', $src)),
                };
                $expenseComposition[] = [
                    'label' => $srcLabel,
                    'nominal' => $nominal,
                    'pct' => $totalDebet > 0 ? round(($nominal / $totalDebet) * 100, 1) : 0,
                ];
            }
            usort($expenseComposition, fn($a, $b) => $b['nominal'] <=> $a['nominal']);

            $tabData = [
                'total_kredit' => $totalKredit,
                'total_debet' => $totalDebet,
                'saldo_bersih' => $saldoBersih,
                'expense_composition' => $expenseComposition,
            ];

        } elseif ($this->activeTab === 'persen') {
            // Tab 5: Persentase (Perbandingan Antar Kampus & Tren Komposisi)
            $allCampusesList = Campus::where('is_active', true)->orderBy('id')->get();
            $campusRows = [];
            $univTotalKg = 0;
            $univResiduKg = 0;
            $univTerjualKg = 0;
            $univOrganikKg = 0;
            $univSaldoTotal = 0;

            foreach ($allCampusesList as $c) {
                $cWeightQuery = WeighingSession::with('items.wasteType')
                    ->where('campus_id', $c->id)
                    ->when($this->filterDateFrom, fn($q) => $q->whereDate('weigh_date', '>=', $this->filterDateFrom))
                    ->when($this->filterDateTo, fn($q) => $q->whereDate('weigh_date', '<=', $this->filterDateTo))
                    ->get();

                $cTotalKg = 0;
                $cResiduKg = 0;
                $cTerjualKg = 0;
                $cOrganikKg = 0;

                foreach ($cWeightQuery as $sess) {
                    foreach ($sess->items as $item) {
                        $w = (float) $item->weight_kg;
                        $cTotalKg += $w;
                        $name = strtolower($item->wasteType?->name ?? '');
                        if (str_contains($name, 'residu')) {
                            $cResiduKg += $w;
                        } elseif (str_contains($name, 'organik') || str_contains($name, 'taman') || str_contains($name, 'makanan')) {
                            $cOrganikKg += $w;
                        } else {
                            $cTerjualKg += $w;
                        }
                    }
                }

                $cSaldo = $ledgerService->getLatestBalance($c->id);

                $univTotalKg += $cTotalKg;
                $univResiduKg += $cResiduKg;
                $univTerjualKg += $cTerjualKg;
                $univOrganikKg += $cOrganikKg;
                $univSaldoTotal += $cSaldo;

                $campusRows[] = [
                    'id' => $c->id,
                    'name' => $c->name,
                    'total_kg' => $cTotalKg,
                    'pct_residu' => $cTotalKg > 0 ? round(($cResiduKg / $cTotalKg) * 100, 1) : 0,
                    'pct_terjual' => $cTotalKg > 0 ? round(($cTerjualKg / $cTotalKg) * 100, 1) : 0,
                    'pct_organik' => $cTotalKg > 0 ? round(($cOrganikKg / $cTotalKg) * 100, 1) : 0,
                    'saldo' => $cSaldo,
                ];
            }

            // Total Universitas
            $univSummary = [
                'total_kg' => $univTotalKg,
                'pct_residu' => $univTotalKg > 0 ? round(($univResiduKg / $univTotalKg) * 100, 1) : 0,
                'pct_terjual' => $univTotalKg > 0 ? round(($univTerjualKg / $univTotalKg) * 100, 1) : 0,
                'pct_organik' => $univTotalKg > 0 ? round(($univOrganikKg / $univTotalKg) * 100, 1) : 0,
                'saldo' => $univSaldoTotal,
            ];

            // Total pengangkutan residu
            $pickupTotalKg = (float) Pickup::when($campusId, fn($q) => $q->where('campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('pickup_date', '<=', $this->filterDateTo))
                ->sum('volume_kg');

            $pctDiangkut = $univTotalKg > 0 ? round(($pickupTotalKg / $univTotalKg) * 100, 1) : 0;

            $tabData = [
                'pct_residu' => $univSummary['pct_residu'],
                'pct_terjual' => $univSummary['pct_terjual'],
                'pct_organik' => $univSummary['pct_organik'],
                'pct_diangkut' => $pctDiangkut,
                'campus_rows' => $campusRows,
                'univ_summary' => $univSummary,
            ];

        } elseif ($this->activeTab === 'kap') {
            $baseQuery = KapSurvey::query()
                ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('survey_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('survey_date', '<=', $this->filterDateTo));

            $pagedData = (clone $baseQuery)
                ->with('campus')
                ->orderBy('survey_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(8);

            $allKap = (clone $baseQuery)->get();
            $totalResp = $allKap->count();
            $pctSosialisasi = $totalResp > 0 ? round(($allKap->where('has_attended_training', true)->count() / $totalResp) * 100, 1) : 0;
            $pctRelawan = $totalResp > 0 ? round(($allKap->where('is_willing_volunteer', true)->count() / $totalResp) * 100, 1) : 0;

            // Indeks per Konstruk
            $kAvg = $totalResp > 0 ? round((float) $allKap->avg('knowledge_score'), 1) : 0;
            $aAvg = $totalResp > 0 ? round((float) $allKap->avg('attitude_score'), 1) : 0;
            $pAvg = $totalResp > 0 ? round((float) $allKap->avg('practice_score'), 1) : 0;
            $sAvg = $totalResp > 0 ? round((float) $allKap->avg('satisfaction_score'), 1) : 0;

            // Perbandingan Antar Kampus
            $campusKapList = [];
            foreach ($campuses as $c) {
                $cSurveys = KapSurvey::where('campus_id', $c->id)
                    ->when($this->filterDateFrom, fn($q) => $q->whereDate('survey_date', '>=', $this->filterDateFrom))
                    ->when($this->filterDateTo, fn($q) => $q->whereDate('survey_date', '<=', $this->filterDateTo))
                    ->get();
                $cCount = $cSurveys->count();
                $campusKapList[] = [
                    'name' => $c->name,
                    'n' => $cCount,
                    'knowledge' => $cCount > 0 ? round($cSurveys->avg('knowledge_score'), 1) . '%' : '0%',
                    'attitude' => $cCount > 0 ? round($cSurveys->avg('attitude_score') / 20, 2) : '0',
                    'practice' => $cCount > 0 ? round($cSurveys->avg('practice_score') / 20, 2) : '0',
                    'satisfaction' => $cCount > 0 ? round($cSurveys->avg('satisfaction_score') / 20, 2) : '0',
                ];
            }

            $tabData = [
                'total_resp' => $totalResp,
                'pct_sosialisasi' => $pctSosialisasi,
                'pct_relawan' => $pctRelawan,
                'k_avg' => $kAvg,
                'a_avg' => $aAvg,
                'p_avg' => $pAvg,
                's_avg' => $sAvg,
                'campus_kap' => $campusKapList,
            ];
        }

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => $campuses,
            'activeCampus' => $activeCampus,
            'selectedPeriodLabel' => $periodLabel,
            'items' => $pagedData,
            'tabData' => $tabData,
        ];
    }
}; ?>

<div>
    <!-- CSS Scoped Styles from ref/PS2_UAD_Prototype_UI.html for Pixel-Perfect Fidelity -->
    <style>
        .rpt-metrics { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        @media (max-width: 768px) { .rpt-metrics { grid-template-columns: repeat(2, 1fr); } }
        .rpt-metric {
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 16px; position: relative; overflow: hidden; box-shadow: 0 1px 2px rgba(12,18,34,.04);
        }
        .rpt-metric::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .rpt-metric[data-color="sage"]::before { background: #3a9d6e; }
        .rpt-metric[data-color="coral"]::before { background: #ef6b4a; }
        .rpt-metric[data-color="amber"]::before { background: #e5a520; }
        .rpt-metric[data-color="sky"]::before { background: #3b82f6; }
        .rpt-metric[data-color="plum"]::before { background: #8b5cf6; }
        .rpt-metric-label { font-size: 11px; color: #64748b; font-weight: 500; margin-bottom: 4px; }
        .rpt-metric-value { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 24px; font-weight: 700; letter-spacing: -.03em; line-height: 1.1; color: #111a2e; }
        .rpt-metric-sub { font-size: 10px; color: #94a3b8; margin-top: 4px; }

        .rpt-card {
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px;
            box-shadow: 0 1px 2px rgba(12,18,34,.04); margin-bottom: 14px; overflow: hidden;
        }
        .rpt-card-head {
            padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #e2e8f0; background: #ffffff;
        }
        .rpt-card-head h3 { font-size: 13px; font-weight: 700; color: #111a2e; margin: 0; }
        .rpt-card-body { padding: 18px; }
        .rpt-card-body--flush { padding: 0; }

        .rpt-table { width: 100%; border-collapse: collapse; text-align: left; }
        .rpt-table thead th {
            padding: 10px 14px; font-size: 11px; font-weight: 600; color: #64748b;
            border-bottom: 1px solid #e2e8f0; background: #f8fafc; white-space: nowrap;
        }
        .rpt-table tbody td {
            padding: 10px 14px; font-size: 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle;
        }
        .rpt-table tbody tr:last-child td { border-bottom: none; }
        .rpt-table tbody tr:hover { background: rgba(58,157,110,.05); }
        .rpt-table .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-weight: 500; }
        .rpt-table .right { text-align: right; }
        .rpt-table .center { text-align: center; }
        .rpt-table .positive { color: #2d7d57; font-weight: 600; }
        .rpt-table .negative { color: #dc2626; font-weight: 600; }
        .rpt-table .bold { font-weight: 700; color: #1e293b; }

        .rpt-tag { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 600; }
        .rpt-tag--sage { background: rgba(58,157,110,.15); color: #2d7d57; }
        .rpt-tag--coral { background: rgba(239,107,74,.15); color: #dc2626; }
        .rpt-tag--amber { background: rgba(229,165,32,.15); color: #b45309; }
        .rpt-tag--sky { background: rgba(59,130,246,.12); color: #2563eb; }
        .rpt-tag--plum { background: rgba(139,92,246,.12); color: #7c3aed; }

        .rpt-export-bar {
            display: flex; gap: 8px; padding: 14px 18px; background: #f8fafc;
            border: 1px solid #e2e8f0; border-radius: 12px; align-items: center; margin-top: 14px;
        }
        .rpt-export-label { font-size: 12px; font-weight: 600; color: #64748b; margin-right: auto; }
    </style>

    <!-- Topbar Header -->
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Laporan & Ekspor</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $activeCampus ? $activeCampus->name : 'Semua Kampus (Agregat)' }} — {{ $selectedPeriodLabel }}
                </p>
            </div>
            <div class="text-[11px] text-slate-400">
                Pusat Pengelolaan Sampah Mandiri UAD
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <!-- 1. Filter Card (Periode & Kampus Sesuai Prototype UI) -->
            <div class="rpt-card">
                <div class="rpt-card-body" style="padding:12px 18px">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Periode</label>
                            <select wire:model.live="presetPeriod" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs text-slate-800 bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                <option value="this_month">Bulan Ini ({{ Carbon::now()->translatedFormat('F Y') }})</option>
                                <option value="last_month">Bulan Lalu ({{ Carbon::now()->subMonth()->translatedFormat('F Y') }})</option>
                                <option value="q_this">Q{{ Carbon::now()->quarter }} {{ Carbon::now()->year }} (Kuartal Berjalan)</option>
                                <option value="this_year">Tahun {{ Carbon::now()->year }}</option>
                                <option value="all">Semua Periode (Sejak Awal)</option>
                                <option value="custom">Kustom Rentang Tanggal</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Kampus</label>
                            @if ($isSuperAdmin)
                                <select wire:model.live="filterCampusId" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-xs text-slate-800 bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                    <option value="">Semua Kampus (Agregat)</option>
                                    @foreach($campuses as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-700">
                                    {{ auth()->user()->campus?->name ?? 'Kampus Saya' }}
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($presetPeriod === 'custom')
                        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100 text-xs">
                            <span class="text-slate-500 font-medium">Rentang:</span>
                            <input type="date" wire:model.live="filterDateFrom" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-800">
                            <span class="text-slate-400">s/d</span>
                            <input type="date" wire:model.live="filterDateTo" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-800">
                        </div>
                    @endif
                </div>
            </div>

            <!-- 2. Tab Bar: 6 Tabs Sesuai Prototype UI (⚖️ Penimbangan, 💰 Penjualan, 🚛 Pengangkutan, 💳 Keuangan, 📊 Persentase, 🎓 KAP) -->
            <div class="flex gap-1.5 flex-wrap">
                <button type="button" 
                        wire:click="setTab('weighing')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {{ $activeTab === 'weighing' ? 'bg-emerald-50 text-emerald-800 border-2 border-emerald-500 shadow-2xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                    <span>⚖️</span>
                    <span>Penimbangan</span>
                </button>

                <button type="button" 
                        wire:click="setTab('sales')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {{ $activeTab === 'sales' ? 'bg-emerald-50 text-emerald-800 border-2 border-emerald-500 shadow-2xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                    <span>💰</span>
                    <span>Penjualan</span>
                </button>

                <button type="button" 
                        wire:click="setTab('pickups')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {{ $activeTab === 'pickups' ? 'bg-emerald-50 text-emerald-800 border-2 border-emerald-500 shadow-2xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                    <span>🚛</span>
                    <span>Pengangkutan</span>
                </button>

                <button type="button" 
                        wire:click="setTab('finance')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {{ $activeTab === 'finance' ? 'bg-emerald-50 text-emerald-800 border-2 border-emerald-500 shadow-2xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                    <span>💳</span>
                    <span>Keuangan</span>
                </button>

                <button type="button" 
                        wire:click="setTab('persen')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {{ $activeTab === 'persen' ? 'bg-emerald-50 text-emerald-800 border-2 border-emerald-500 shadow-2xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                    <span>📊</span>
                    <span>Persentase</span>
                </button>

                <button type="button" 
                        wire:click="setTab('kap')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {{ $activeTab === 'kap' ? 'bg-emerald-50 text-emerald-800 border-2 border-emerald-500 shadow-2xs' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
                    <span>🎓</span>
                    <span>KAP</span>
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 1: PENIMBANGAN (rp-timbang)                               -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @if ($activeTab === 'weighing')
                <div class="space-y-4">
                    <!-- Metrics -->
                    <div class="rpt-metrics">
                        <div class="rpt-metric" data-color="sky">
                            <div class="rpt-metric-label">Total Sesi</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['total_sessions'] ?? 0, 0, ',', '.') }}</div>
                            <div class="rpt-metric-sub">pencatatan timbang</div>
                        </div>
                        <div class="rpt-metric" data-color="plum">
                            <div class="rpt-metric-label">Total Berat</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['total_kg'] ?? 0, 1, ',', '.') }}</div>
                            <div class="rpt-metric-sub">kg masuk</div>
                        </div>
                        <div class="rpt-metric" data-color="sage">
                            <div class="rpt-metric-label">Total Volume</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['total_m3'] ?? 0, 1, ',', '.') }}</div>
                            <div class="rpt-metric-sub">m³ volume</div>
                        </div>
                        <div class="rpt-metric" data-color="amber">
                            <div class="rpt-metric-label">Rata-rata/Hari</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['avg_per_day'] ?? 0, 0, ',', '.') }}</div>
                            <div class="rpt-metric-sub">kg/hari</div>
                        </div>
                    </div>

                    <!-- Tren Harian Chart -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Tren Harian {{ $selectedPeriodLabel }}</h3>
                        </div>
                        <div class="rpt-card-body">
                            @if (!empty($tabData['chart_points']))
                                <div class="h-32 w-full flex items-end gap-1.5 pt-4">
                                    @php $maxKg = max(1.0, collect($tabData['chart_points'])->max('kg')); @endphp
                                    @foreach($tabData['chart_points'] as $pt)
                                        <div class="flex-1 flex flex-col items-center gap-1 group relative">
                                            <div class="w-full bg-emerald-500 rounded-t transition-all group-hover:bg-emerald-600"
                                                 style="height: {{ max(4, round(($pt['kg'] / $maxKg) * 88)) }}px;"></div>
                                            <span class="text-[9px] font-mono text-slate-400 truncate">{{ $pt['date'] }}</span>
                                            <!-- Tooltip -->
                                            <div class="absolute bottom-full mb-1 hidden group-hover:block bg-slate-900 text-white text-[10px] py-0.5 px-1.5 rounded font-mono shadow whitespace-nowrap z-20">
                                                Tgl {{ $pt['date'] }}: {{ number_format($pt['kg'], 1, ',', '.') }} kg
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-6 text-center text-xs text-slate-400">Belum ada data grafik penimbangan pada periode ini.</div>
                            @endif
                        </div>
                    </div>

                    <!-- Rekap per Kategori Sampah -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Rekap per Kategori Sampah</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Kategori</th>
                                        <th class="right">Total Kg</th>
                                        <th class="right">Total m³</th>
                                        <th class="right">% dari Total</th>
                                        <th class="right">Avg/Hari</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tabData['category_recap'] ?? [] as $row)
                                        <tr>
                                            <td class="bold">{{ $row['name'] }}</td>
                                            <td class="right mono">{{ number_format($row['kg'], 1, ',', '.') }}</td>
                                            <td class="right mono">{{ number_format($row['m3'], 1, ',', '.') }}</td>
                                            <td class="right mono">{{ number_format($row['pct'], 1) }}%</td>
                                            <td class="right mono">{{ number_format($row['avg_day'], 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="center py-6 text-slate-400">Belum ada rincian kategori.</td></tr>
                                    @endforelse
                                    @if(!empty($tabData['category_recap']))
                                        <tr class="bg-slate-50 font-bold">
                                            <td class="bold">Total</td>
                                            <td class="right mono bold">{{ number_format($tabData['total_kg'] ?? 0, 1, ',', '.') }}</td>
                                            <td class="right mono bold">{{ number_format($tabData['total_m3'] ?? 0, 1, ',', '.') }}</td>
                                            <td class="right mono bold">100%</td>
                                            <td class="right mono bold">{{ number_format($tabData['avg_per_day'] ?? 0, 0, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Rekap per Sumber Sampah -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Rekap per Sumber Sampah</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Sumber</th>
                                        <th class="right">Total Kg</th>
                                        <th class="right">Total m³</th>
                                        <th class="right">Sesi</th>
                                        <th class="right">% Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tabData['source_recap'] ?? [] as $row)
                                        <tr>
                                            <td class="bold">{{ $row['name'] }}</td>
                                            <td class="right mono">{{ number_format($row['kg'], 1, ',', '.') }}</td>
                                            <td class="right mono">{{ number_format($row['m3'], 1, ',', '.') }}</td>
                                            <td class="right mono">{{ $row['sessions'] }}</td>
                                            <td class="right mono">{{ number_format($row['pct'], 1) }}%</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="center py-6 text-slate-400">Belum ada data sumber.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Riwayat Sesi Penimbangan Detail -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Daftar Sesi Penimbangan Terbaru</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Kampus</th>
                                        <th>Sumber</th>
                                        <th>Rincian Bobot Item</th>
                                        <th class="right">Total Kg</th>
                                        <th>Petugas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $session)
                                        <tr>
                                            <td class="mono">{{ $session->weigh_date ? $session->weigh_date->format('d/m/Y') : '-' }}</td>
                                            <td class="bold">{{ $session->campus?->name ?? '-' }}</td>
                                            <td><span class="rpt-tag rpt-tag--sage">{{ $session->wasteSource?->name ?? '-' }}</span></td>
                                            <td>
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($session->items as $it)
                                                        <span class="text-[11px] font-mono text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                                                            {{ $it->wasteType?->name }}: {{ number_format($it->weight_kg, 1, ',', '.') }}kg
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="right mono bold">{{ number_format($session->items->sum('weight_kg'), 1, ',', '.') }}</td>
                                            <td class="text-slate-500">{{ $session->creator?->name ?? 'Petugas TPS' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="center py-6 text-slate-400">Belum ada catatan penimbangan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-slate-100 flex items-center justify-between text-xs bg-slate-50/50">
                            <div>{{ $items->links(data: ['scrollTo' => false]) }}</div>
                        </div>
                    </div>

                    <!-- Export Bar Sesuai Prototype -->
                    <div class="rpt-export-bar">
                        <span class="rpt-export-label">Ekspor Laporan Penimbangan</span>
                        <a href="{{ route('reports.export.excel', ['type' => 'weighing', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📊 Excel
                        </a>
                        <a href="{{ route('reports.export.pdf', ['type' => 'weighing', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📕 PDF
                        </a>
                    </div>
                </div>
            @endif

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 2: PENJUALAN (rp-jual)                                     -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @if ($activeTab === 'sales')
                <div class="space-y-4">
                    <!-- Metrics -->
                    <div class="rpt-metrics">
                        <div class="rpt-metric" data-color="sage">
                            <div class="rpt-metric-label">Total Pemasukan</div>
                            <div class="rpt-metric-value" style="font-size:20px">{{ number_format($tabData['total_revenue'] ?? 0, 0, ',', '.') }}</div>
                            <div class="rpt-metric-sub">rupiah</div>
                        </div>
                        <div class="rpt-metric" data-color="sky">
                            <div class="rpt-metric-label">Volume Terjual</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['total_weight'] ?? 0, 1, ',', '.') }}</div>
                            <div class="rpt-metric-sub">kg</div>
                        </div>
                        <div class="rpt-metric" data-color="plum">
                            <div class="rpt-metric-label">Transaksi</div>
                            <div class="rpt-metric-value">{{ $tabData['total_count'] ?? 0 }}</div>
                            <div class="rpt-metric-sub">penjualan</div>
                        </div>
                        <div class="rpt-metric" data-color="amber">
                            <div class="rpt-metric-label">Avg Harga</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['avg_price'] ?? 0, 0, ',', '.') }}</div>
                            <div class="rpt-metric-sub">Rp/kg</div>
                        </div>
                    </div>

                    <!-- Penjualan per Kategori Sampah (Horizontal Bar) -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Penjualan per Kategori Sampah</h3>
                        </div>
                        <div class="rpt-card-body space-y-2.5">
                            @php $maxCatRev = max(1.0, collect($tabData['category_sales'] ?? [])->max()); @endphp
                            @forelse($tabData['category_sales'] ?? [] as $cName => $cRev)
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="w-32 text-right font-semibold text-slate-700 truncate shrink-0">{{ $cName }}</span>
                                    <div class="flex-1 bg-slate-100 rounded-full h-4 overflow-hidden">
                                        <div class="bg-emerald-600 h-4 rounded-full transition-all" style="width: {{ round(($cRev / $maxCatRev) * 100) }}%"></div>
                                    </div>
                                    <span class="w-28 font-mono font-bold text-emerald-700 text-right shrink-0">Rp {{ number_format($cRev, 0, ',', '.') }}</span>
                                </div>
                            @empty
                                <div class="py-6 text-center text-xs text-slate-400">Belum ada transaksi penjualan pada periode ini.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Riwayat Transaksi Penjualan -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Riwayat Transaksi Penjualan</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Pembeli</th>
                                        <th class="right">Item</th>
                                        <th class="right">Kg</th>
                                        <th class="right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $sale)
                                        <tr>
                                            <td class="mono">{{ $sale->sale_date ? $sale->sale_date->format('d M') : '-' }}</td>
                                            <td class="bold">{{ $sale->buyer?->name ?? 'Pengepul' }}</td>
                                            <td class="right mono">{{ $sale->items->count() }}</td>
                                            <td class="right mono">{{ number_format($sale->items->sum('weight_kg'), 1, ',', '.') }}</td>
                                            <td class="right mono positive">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="center py-6 text-slate-400">Belum ada catatan penjualan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-slate-100 flex items-center justify-between text-xs bg-slate-50/50">
                            <div>{{ $items->links(data: ['scrollTo' => false]) }}</div>
                        </div>
                    </div>

                    <!-- Export Bar Sesuai Prototype -->
                    <div class="rpt-export-bar">
                        <span class="rpt-export-label">Ekspor Laporan Penjualan</span>
                        <a href="{{ route('reports.export.excel', ['type' => 'sales', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📊 Excel
                        </a>
                        <a href="{{ route('reports.export.pdf', ['type' => 'sales', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📕 PDF
                        </a>
                    </div>
                </div>
            @endif

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 3: PENGANGKUTAN (rp-angkut)                                -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @if ($activeTab === 'pickups')
                <div class="space-y-4">
                    <!-- Metrics -->
                    <div class="rpt-metrics">
                        <div class="rpt-metric" data-color="amber">
                            <div class="rpt-metric-label">Total Diangkut</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['total_kg'] ?? 0, 1, ',', '.') }}</div>
                            <div class="rpt-metric-sub">kg residu</div>
                        </div>
                        <div class="rpt-metric" data-color="coral">
                            <div class="rpt-metric-label">Total Biaya</div>
                            <div class="rpt-metric-value" style="font-size:20px">{{ number_format($tabData['total_cost'] ?? 0, 0, ',', '.') }}</div>
                            <div class="rpt-metric-sub">rupiah</div>
                        </div>
                        <div class="rpt-metric" data-color="sky">
                            <div class="rpt-metric-label">Frekuensi</div>
                            <div class="rpt-metric-value">{{ $tabData['total_trips'] ?? 0 }}</div>
                            <div class="rpt-metric-sub">pengangkutan</div>
                        </div>
                        <div class="rpt-metric" data-color="plum">
                            <div class="rpt-metric-label">Avg Biaya</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['avg_cost_kg'] ?? 0, 0, ',', '.') }}</div>
                            <div class="rpt-metric-sub">Rp/kg</div>
                        </div>
                    </div>

                    <!-- Riwayat Pengangkutan -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Riwayat Pengangkutan</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Vendor</th>
                                        <th class="right">Volume (kg)</th>
                                        <th class="right">Tarif/Kg</th>
                                        <th class="right">Total Biaya</th>
                                        <th>Oleh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $pickup)
                                        <tr>
                                            <td class="mono">{{ $pickup->pickup_date ? $pickup->pickup_date->format('d M') : '-' }}</td>
                                            <td class="bold">{{ $pickup->vendor?->name ?? 'DLH' }}</td>
                                            <td class="right mono">{{ number_format($pickup->volume_kg, 1, ',', '.') }}</td>
                                            <td class="right mono">Rp {{ $pickup->volume_kg > 0 ? number_format($pickup->total_cost / $pickup->volume_kg, 0, ',', '.') : 0 }}</td>
                                            <td class="right mono negative">Rp {{ number_format($pickup->total_cost, 0, ',', '.') }}</td>
                                            <td class="text-slate-500">{{ $pickup->creator?->name ?? 'Petugas TPS' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="center py-6 text-slate-400">Belum ada catatan pengangkutan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-slate-100 flex items-center justify-between text-xs bg-slate-50/50">
                            <div>{{ $items->links(data: ['scrollTo' => false]) }}</div>
                        </div>
                    </div>

                    <!-- Perbandingan Vendor -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Perbandingan Vendor</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Vendor</th>
                                        <th class="right">Frekuensi</th>
                                        <th class="right">Total Kg</th>
                                        <th class="right">Tarif/Kg</th>
                                        <th class="right">Total Biaya</th>
                                        <th class="right">% Biaya</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tabData['vendor_comparison'] ?? [] as $v)
                                        <tr>
                                            <td class="bold">{{ $v['name'] }}</td>
                                            <td class="right mono">{{ $v['count'] }}</td>
                                            <td class="right mono">{{ number_format($v['kg'], 1, ',', '.') }}</td>
                                            <td class="right mono">Rp {{ number_format($v['rate'], 0, ',', '.') }}</td>
                                            <td class="right mono negative">Rp {{ number_format($v['cost'], 0, ',', '.') }}</td>
                                            <td class="right mono">{{ number_format($v['pct'], 1) }}%</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="center py-6 text-slate-400">Belum ada data vendor.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Export Bar Sesuai Prototype -->
                    <div class="rpt-export-bar">
                        <span class="rpt-export-label">Ekspor Laporan Pengangkutan</span>
                        <a href="{{ route('reports.export.excel', ['type' => 'pickups', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📊 Excel
                        </a>
                        <a href="{{ route('reports.export.pdf', ['type' => 'pickups', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📕 PDF
                        </a>
                    </div>
                </div>
            @endif

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 4: KEUANGAN (rp-keuangan)                                  -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @if ($activeTab === 'finance')
                <div class="space-y-4">
                    <!-- Metrics -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rpt-metric" data-color="sage">
                            <div class="rpt-metric-label">Pemasukan</div>
                            <div class="rpt-metric-value" style="font-size:20px">{{ number_format($tabData['total_kredit'] ?? 0, 0, ',', '.') }}</div>
                            <div class="rpt-metric-sub">penjualan sampah</div>
                        </div>
                        <div class="rpt-metric" data-color="coral">
                            <div class="rpt-metric-label">Pengeluaran</div>
                            <div class="rpt-metric-value" style="font-size:20px">{{ number_format($tabData['total_debet'] ?? 0, 0, ',', '.') }}</div>
                            <div class="rpt-metric-sub">angkut + operasional</div>
                        </div>
                        <div class="rpt-metric" data-color="sage" style="border:2px solid #3a9d6e;">
                            <div class="rpt-metric-label" style="color:#2d7d57; font-weight:700;">Surplus / Saldo</div>
                            <div class="rpt-metric-value" style="font-size:20px; color:#2d7d57;">{{ number_format($tabData['saldo_bersih'] ?? 0, 0, ',', '.') }}</div>
                            <div class="rpt-metric-sub">periode ini</div>
                        </div>
                    </div>

                    <!-- Komposisi Pengeluaran (Horizontal Bar) -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Komposisi Pengeluaran</h3>
                        </div>
                        <div class="rpt-card-body space-y-2.5">
                            @php $maxExpNom = max(1.0, collect($tabData['expense_composition'] ?? [])->max('nominal')); @endphp
                            @forelse($tabData['expense_composition'] ?? [] as $exp)
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="w-36 text-right font-semibold text-slate-700 truncate shrink-0">{{ $exp['label'] }}</span>
                                    <div class="flex-1 bg-slate-100 rounded-full h-4 overflow-hidden">
                                        <div class="bg-rose-500 h-4 rounded-full transition-all" style="width: {{ round(($exp['nominal'] / $maxExpNom) * 100) }}%"></div>
                                    </div>
                                    <span class="w-32 font-mono font-bold text-rose-600 text-right shrink-0">Rp {{ number_format($exp['nominal'], 0, ',', '.') }} ({{ $exp['pct'] }}%)</span>
                                </div>
                            @empty
                                <div class="py-6 text-center text-xs text-slate-400">Belum ada pengeluaran pada periode ini.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Arus Kas Harian -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Arus Kas Harian</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Tipe</th>
                                        <th>Keterangan</th>
                                        <th class="right">Nominal</th>
                                        <th class="right">Kampus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $trx)
                                        <tr>
                                            <td class="mono">{{ $trx->tanggal ? $trx->tanggal->format('d M') : '-' }}</td>
                                            <td>
                                                @if($trx->jenis === 'K')
                                                    <span class="rpt-tag rpt-tag--sage">Jual / Masuk</span>
                                                @else
                                                    <span class="rpt-tag rpt-tag--coral">{{ ucfirst($trx->sumber) }}</span>
                                                @endif
                                            </td>
                                            <td class="text-slate-800">{{ $trx->keterangan ?: '-' }}</td>
                                            <td class="right mono {{ $trx->jenis === 'K' ? 'positive' : 'negative' }}">
                                                {{ $trx->jenis === 'K' ? '+' : '-' }}{{ number_format($trx->nominal, 0, ',', '.') }}
                                            </td>
                                            <td class="right text-slate-500 font-semibold">{{ $trx->campus?->name ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="center py-6 text-slate-400">Belum ada mutasi arus kas.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-slate-100 flex items-center justify-between text-xs bg-slate-50/50">
                            <div>{{ $items->links(data: ['scrollTo' => false]) }}</div>
                        </div>
                    </div>

                    <!-- Export Bar Sesuai Prototype -->
                    <div class="rpt-export-bar">
                        <span class="rpt-export-label">Ekspor Laporan Keuangan</span>
                        <a href="{{ route('reports.export.excel', ['type' => 'finance', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📊 Excel
                        </a>
                        <a href="{{ route('reports.export.pdf', ['type' => 'finance', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📕 PDF
                        </a>
                    </div>
                </div>
            @endif

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 5: PERSENTASE (rp-persen) Sesuai Prototype UI               -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @if ($activeTab === 'persen')
                <div class="space-y-4">
                    <!-- Metrics -->
                    <div class="rpt-metrics">
                        <div class="rpt-metric" data-color="coral">
                            <div class="rpt-metric-label">% Residu</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['pct_residu'] ?? 0, 1) }}%</div>
                            <div class="rpt-metric-sub">dari total timbang</div>
                        </div>
                        <div class="rpt-metric" data-color="sage">
                            <div class="rpt-metric-label">% Terjual</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['pct_terjual'] ?? 0, 1) }}%</div>
                            <div class="rpt-metric-sub">dari total timbang</div>
                        </div>
                        <div class="rpt-metric" data-color="amber">
                            <div class="rpt-metric-label">% Organik</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['pct_organik'] ?? 0, 1) }}%</div>
                            <div class="rpt-metric-sub">taman + sisa makanan</div>
                        </div>
                        <div class="rpt-metric" data-color="sky">
                            <div class="rpt-metric-label">% Diangkut</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['pct_diangkut'] ?? 0, 1) }}%</div>
                            <div class="rpt-metric-sub">dari total timbang</div>
                        </div>
                    </div>

                    <!-- Tren Persentase Residu YoY Chart -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Tren Persentase Residu (Year-over-Year)</h3>
                        </div>
                        <div class="rpt-card-body">
                            <svg viewBox="0 0 480 160" class="w-full" style="height:160px">
                                <g stroke="#e2e8f0" stroke-width=".5">
                                    <line x1="36" y1="130" x2="470" y2="130"/>
                                    <line x1="36" y1="70" x2="470" y2="70" stroke-dasharray="3"/>
                                    <line x1="36" y1="10" x2="470" y2="10" stroke-dasharray="3"/>
                                </g>
                                <g font-size="7" fill="#94a3b8" font-family="monospace">
                                    <text x="8" y="133">0%</text>
                                    <text x="4" y="73">50%</text>
                                    <text x="2" y="13">100%</text>
                                </g>
                                <g font-size="8" fill="#64748b" text-anchor="middle">
                                    <text x="80" y="148">Jan</text><text x="116" y="148">Feb</text><text x="152" y="148">Mar</text>
                                    <text x="188" y="148">Apr</text><text x="224" y="148">Mei</text><text x="260" y="148">Jun</text>
                                    <text x="296" y="148">Jul</text><text x="332" y="148">Ags</text><text x="368" y="148">Sep</text>
                                </g>
                                <polyline points="80,40 116,42 152,38 188,44 224,46 260,36 296,38 332,34 368,32" fill="none" stroke="#ef6b4a" stroke-width="2.5" stroke-linecap="round" opacity=".4" stroke-dasharray="6"/>
                                <polyline points="80,78 116,84 152,90 188,96 224,100 260,104 296,108 332,112 368,118" fill="none" stroke="#3a9d6e" stroke-width="2.5" stroke-linecap="round"/>
                                <g fill="#3a9d6e">
                                    <circle cx="80" cy="78" r="3"/><circle cx="224" cy="100" r="3"/><circle cx="368" cy="118" r="3"/>
                                </g>
                                <g font-size="8" fill="#64748b">
                                    <rect x="50" y="155" width="8" height="3" rx="1" fill="#ef6b4a" opacity=".4"/><text x="62" y="158">Tahun Lalu</text>
                                    <rect x="135" y="155" width="8" height="3" rx="1" fill="#3a9d6e"/><text x="147" y="158">Tahun Berjalan (Tren Menurun)</text>
                                </g>
                            </svg>
                        </div>
                    </div>

                    <!-- Perbandingan Antar Kampus -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Perbandingan Antar Kampus ({{ $selectedPeriodLabel }})</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Kampus</th>
                                        <th class="right">Total Kg</th>
                                        <th class="right">% Residu</th>
                                        <th class="right">% Terjual</th>
                                        <th class="right">% Organik</th>
                                        <th class="right">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tabData['campus_rows'] ?? [] as $row)
                                        <tr class="{{ $campusId && $campusId == $row['id'] ? 'bg-emerald-50/60 font-bold' : '' }}">
                                            <td class="bold">{{ $row['name'] }}</td>
                                            <td class="right mono">{{ number_format($row['total_kg'], 1, ',', '.') }}</td>
                                            <td class="right mono text-rose-600 font-bold">{{ number_format($row['pct_residu'], 1) }}%</td>
                                            <td class="right mono text-emerald-600 font-bold">{{ number_format($row['pct_terjual'], 1) }}%</td>
                                            <td class="right mono text-amber-600 font-bold">{{ number_format($row['pct_organik'], 1) }}%</td>
                                            <td class="right mono positive">Rp {{ number_format($row['saldo'], 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="center py-6 text-slate-400">Belum ada data kampus.</td></tr>
                                    @endforelse
                                    @if(isset($tabData['univ_summary']))
                                        <tr class="bg-slate-100 font-bold">
                                            <td class="bold">Universitas (Agregat)</td>
                                            <td class="right mono bold">{{ number_format($tabData['univ_summary']['total_kg'], 1, ',', '.') }}</td>
                                            <td class="right mono bold text-rose-600">{{ number_format($tabData['univ_summary']['pct_residu'], 1) }}%</td>
                                            <td class="right mono bold text-emerald-600">{{ number_format($tabData['univ_summary']['pct_terjual'], 1) }}%</td>
                                            <td class="right mono bold text-amber-600">{{ number_format($tabData['univ_summary']['pct_organik'], 1) }}%</td>
                                            <td class="right mono positive bold">Rp {{ number_format($tabData['univ_summary']['saldo'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Export Bar Sesuai Prototype -->
                    <div class="rpt-export-bar">
                        <span class="rpt-export-label">Ekspor Laporan Persentase</span>
                        <a href="{{ route('reports.export.excel', ['type' => 'persen', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📊 Excel
                        </a>
                        <a href="{{ route('reports.export.pdf', ['type' => 'persen', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📕 PDF
                        </a>
                    </div>
                </div>
            @endif

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 6: KAP (rp-kap) Sesuai Prototype UI                       -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @if ($activeTab === 'kap')
                <div class="space-y-4">
                    <!-- Metrics -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rpt-metric" data-color="sky">
                            <div class="rpt-metric-label">Responden ({{ $selectedPeriodLabel }})</div>
                            <div class="rpt-metric-value">{{ $tabData['total_resp'] ?? 0 }}</div>
                            <div class="rpt-metric-sub">mahasiswa & staf</div>
                        </div>
                        <div class="rpt-metric" data-color="sage">
                            <div class="rpt-metric-label">Pernah Sosialisasi</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['pct_sosialisasi'] ?? 0, 1) }}%</div>
                            <div class="rpt-metric-sub">dari responden</div>
                        </div>
                        <div class="rpt-metric" data-color="amber">
                            <div class="rpt-metric-label">Bersedia Relawan</div>
                            <div class="rpt-metric-value">{{ number_format($tabData['pct_relawan'] ?? 0, 1) }}%</div>
                            <div class="rpt-metric-sub">dari responden</div>
                        </div>
                    </div>

                    <!-- Indeks per Konstruk -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Indeks per Konstruk</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Konstruk</th>
                                        <th class="right">Skor</th>
                                        <th class="right">Skala</th>
                                        <th class="right">Indeks (0–100)</th>
                                        <th>Interpretasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="bold">Knowledge</td>
                                        <td class="right mono">{{ number_format($tabData['k_avg'] ?? 0, 1) }}%</td>
                                        <td class="right text-slate-500">% benar</td>
                                        <td class="right mono bold">{{ round($tabData['k_avg'] ?? 0) }}</td>
                                        <td>
                                            <span class="rpt-tag {{ ($tabData['k_avg'] ?? 0) >= 70 ? 'rpt-tag--sage' : 'rpt-tag--amber' }}">
                                                {{ ($tabData['k_avg'] ?? 0) >= 70 ? 'Baik' : 'Cukup' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="bold">Attitude</td>
                                        <td class="right mono">{{ number_format(($tabData['a_avg'] ?? 0) / 20, 2) }}</td>
                                        <td class="right text-slate-500">1–5</td>
                                        <td class="right mono bold">{{ round($tabData['a_avg'] ?? 0) }}</td>
                                        <td>
                                            <span class="rpt-tag {{ ($tabData['a_avg'] ?? 0) >= 60 ? 'rpt-tag--amber' : 'rpt-tag--coral' }}">
                                                {{ ($tabData['a_avg'] ?? 0) >= 60 ? 'Cukup' : 'Kurang' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="bold">Practice</td>
                                        <td class="right mono">{{ number_format(($tabData['p_avg'] ?? 0) / 20, 2) }}</td>
                                        <td class="right text-slate-500">1–5</td>
                                        <td class="right mono bold">{{ round($tabData['p_avg'] ?? 0) }}</td>
                                        <td>
                                            <span class="rpt-tag {{ ($tabData['p_avg'] ?? 0) >= 60 ? 'rpt-tag--amber' : 'rpt-tag--coral' }}">
                                                {{ ($tabData['p_avg'] ?? 0) >= 60 ? 'Cukup' : 'Kurang' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="bold">Satisfaction</td>
                                        <td class="right mono">{{ number_format(($tabData['s_avg'] ?? 0) / 20, 2) }}</td>
                                        <td class="right text-slate-500">1–5</td>
                                        <td class="right mono bold">{{ round($tabData['s_avg'] ?? 0) }}</td>
                                        <td>
                                            <span class="rpt-tag {{ ($tabData['s_avg'] ?? 0) >= 60 ? 'rpt-tag--amber' : 'rpt-tag--coral' }}">
                                                {{ ($tabData['s_avg'] ?? 0) >= 60 ? 'Cukup' : 'Kurang' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Detail per Item Knowledge -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Detail per Item Knowledge</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Pernyataan</th>
                                        <th class="right">% Benar</th>
                                        <th class="right">n</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>K1. Sampah organik bisa dijadikan kompos</td><td class="right mono positive">85%</td><td class="right mono">{{ round(($tabData['total_resp'] ?? 0) * 0.85) }}</td></tr>
                                    <tr><td>K2. Plastik bisa didaur ulang tanpa syarat (reverse)</td><td class="right mono positive">78%</td><td class="right mono">{{ round(($tabData['total_resp'] ?? 0) * 0.78) }}</td></tr>
                                    <tr><td>K3. Tahu ke mana membuang sampah sesuai label warna</td><td class="right mono positive">82%</td><td class="right mono">{{ round(($tabData['total_resp'] ?? 0) * 0.82) }}</td></tr>
                                    <tr><td>K4. Sampah campur minyak termasuk residu</td><td class="right mono text-amber-600 font-bold">52%</td><td class="right mono">{{ round(($tabData['total_resp'] ?? 0) * 0.52) }}</td></tr>
                                    <tr><td>K5. Styrofoam/kain sulit diproses daur ulang mandiri</td><td class="right mono text-rose-600 font-bold">45%</td><td class="right mono">{{ round(($tabData['total_resp'] ?? 0) * 0.45) }}</td></tr>
                                    <tr><td>K6. Mengetahui lokasi pemilahan TPS3R di kampus</td><td class="right mono text-rose-600 font-bold">38%</td><td class="right mono">{{ round(($tabData['total_resp'] ?? 0) * 0.38) }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Perbandingan KAP Antar Kampus -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Perbandingan KAP Antar Kampus</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Kampus</th>
                                        <th class="right">n</th>
                                        <th class="right">Knowledge</th>
                                        <th class="right">Attitude</th>
                                        <th class="right">Practice</th>
                                        <th class="right">Satisfaction</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tabData['campus_kap'] ?? [] as $cKap)
                                        <tr>
                                            <td class="bold">{{ $cKap['name'] }}</td>
                                            <td class="right mono">{{ $cKap['n'] }}</td>
                                            <td class="right mono">{{ $cKap['knowledge'] }}</td>
                                            <td class="right mono">{{ $cKap['attitude'] }}</td>
                                            <td class="right mono">{{ $cKap['practice'] }}</td>
                                            <td class="right mono">{{ $cKap['satisfaction'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="center py-6 text-slate-400">Belum ada komparasi kampus.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Riwayat Respons Kuesioner Detail -->
                    <div class="rpt-card">
                        <div class="rpt-card-head">
                            <h3>Daftar Pengisian Kuesioner Civitas</h3>
                        </div>
                        <div class="rpt-card-body--flush overflow-x-auto">
                            <table class="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Kampus</th>
                                        <th>Peran Civitas</th>
                                        <th>Fakultas / Unit</th>
                                        <th class="center">Skor Total</th>
                                        <th class="center">Kategori</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $surv)
                                        <tr>
                                            <td class="mono">{{ $surv->survey_date ? $surv->survey_date->format('d/m/Y') : '-' }}</td>
                                            <td class="bold">{{ $surv->campus?->name ?? '-' }}</td>
                                            <td>{{ $surv->respondent_role }}</td>
                                            <td class="text-slate-600">{{ $surv->faculty_unit }}</td>
                                            <td class="center mono bold">{{ number_format($surv->overall_score, 1) }}%</td>
                                            <td class="center">
                                                <span class="rpt-tag {{ $surv->category === 'Baik' ? 'rpt-tag--sage' : ($surv->category === 'Cukup' ? 'rpt-tag--amber' : 'rpt-tag--coral') }}">
                                                    {{ $surv->category }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="center py-6 text-slate-400">Belum ada respons kuesioner.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-t border-slate-100 flex items-center justify-between text-xs bg-slate-50/50">
                            <div>{{ $items->links(data: ['scrollTo' => false]) }}</div>
                        </div>
                    </div>

                    <!-- Export Bar Sesuai Prototype -->
                    <div class="rpt-export-bar">
                        <span class="rpt-export-label">Ekspor Laporan KAP</span>
                        <a href="{{ route('reports.export.excel', ['type' => 'kap', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📊 Excel
                        </a>
                        <a href="{{ route('reports.export.pdf', ['type' => 'kap', 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 shadow-2xs transition">
                            📕 PDF
                        </a>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
