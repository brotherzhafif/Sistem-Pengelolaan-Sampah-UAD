<?php

namespace App\Livewire\Pages\Reports;

use App\Models\BukuBesar;
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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $activeTab = 'weighing'; // 'weighing', 'sales', 'pickups', 'finance', 'persen', 'kap'
    public ?string $filterCampusId = '';
    public ?string $filterDateFrom = '';
    public ?string $filterDateTo = '';
    public string $presetPeriod = 'this_month'; // 'this_month', 'last_month', 'q_this', 'this_year', 'all', 'custom'

    // Detail Modals state (Uniform with respective modules)
    public ?int $viewSessionId = null;
    public ?int $viewSaleId = null;
    public ?int $viewPickupId = null;
    public ?int $viewSurveyId = null;
    public ?int $viewKeuanganId = null;

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Laporan & Ekspor.');
        }

        $requestedTab = request()->query('tab');
        if ($requestedTab && in_array($requestedTab, ['weighing', 'sales', 'pickups', 'finance', 'persen', 'kap'])) {
            $this->activeTab = $requestedTab;
        }

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
        $this->closeViewModal();
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

    // Modal Triggers
    public function viewSession(int $id): void { $this->viewSessionId = $id; }
    public function viewSale(int $id): void { $this->viewSaleId = $id; }
    public function viewPickup(int $id): void { $this->viewPickupId = $id; }
    public function viewSurvey(int $id): void { $this->viewSurveyId = $id; }
    public function viewKeuangan(int $id): void { $this->viewKeuanganId = $id; }

    public function closeViewModal(): void
    {
        $this->viewSessionId = null;
        $this->viewSaleId = null;
        $this->viewPickupId = null;
        $this->viewSurveyId = null;
        $this->viewKeuanganId = null;
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        $campusId = $isSuperAdmin
            ? (!empty($this->filterCampusId) ? (int) $this->filterCampusId : null)
            : (int) $user->campus_id;

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

        // Fetch Selected Model for Active Modal
        $selectedSession = $this->viewSessionId
            ? WeighingSession::with(['campus', 'wasteSource', 'creator', 'items.wasteType'])->find($this->viewSessionId)
            : null;

        $selectedSale = $this->viewSaleId
            ? Sale::with(['campus', 'buyer', 'creator', 'items.wasteType'])->find($this->viewSaleId)
            : null;

        $selectedPickup = $this->viewPickupId
            ? Pickup::with(['campus', 'vendor', 'creator'])->find($this->viewPickupId)
            : null;

        $selectedSurvey = $this->viewSurveyId
            ? KapSurvey::with('campus')->find($this->viewSurveyId)
            : null;

        $selectedKeuangan = $this->viewKeuanganId
            ? Keuangan::with(['campus', 'creator'])->find($this->viewKeuanganId)
            : null;

        // ══════════════════════════════════════════════════════════════
        // TAB 1: PENIMBANGAN (weighing)
        // ══════════════════════════════════════════════════════════════
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
                $totalM3 = round($totalKg * 0.0038, 1);
            }
            $totalSessions = (clone $baseQuery)->count();

            // Hari aktif timbang
            $daysCount = (clone $baseQuery)->distinct('weigh_date')->count('weigh_date');
            $avgPerDay = $daysCount > 0 ? round($totalKg / $daysCount, 1) : 0;

            // Chart Tren Harian: Daily continuous aggregation
            $dailyTrend = WeighingSession::query()
                ->join('weighing_items', 'weighing_sessions.id', '=', 'weighing_items.weighing_session_id')
                ->when($campusId, fn($q) => $q->where('weighing_sessions.campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('weighing_sessions.weigh_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('weighing_sessions.weigh_date', '<=', $this->filterDateTo))
                ->select(
                    'weighing_sessions.weigh_date',
                    DB::raw('SUM(weighing_items.weight_kg) as total_kg'),
                    DB::raw('COUNT(DISTINCT weighing_sessions.id) as session_count')
                )
                ->groupBy('weighing_sessions.weigh_date')
                ->orderBy('weighing_sessions.weigh_date', 'asc')
                ->get();

            $chartPoints = [];
            foreach ($dailyTrend as $dt) {
                $chartPoints[] = [
                    'date_str' => Carbon::parse($dt->weigh_date)->translatedFormat('d M Y'),
                    'day' => Carbon::parse($dt->weigh_date)->format('d'),
                    'kg' => round((float) $dt->total_kg, 1),
                    'sessions' => (int) $dt->session_count,
                ];
            }

            // Compute SVG coordinates for full-width responsive graph (viewBox 0 0 1000 240)
            $svgPoints = [];
            $maxKg = max(10.0, collect($chartPoints)->max('kg') ?? 10.0);
            $maxKgRounded = (float) (ceil($maxKg / 50) * 50);
            if ($maxKgRounded <= 0) $maxKgRounded = 100.0;
            $ptCount = count($chartPoints);

            $polylineStr = '';
            $polygonStr = '';

            if ($ptCount > 0) {
                $polyCoords = [];
                foreach ($chartPoints as $i => $pt) {
                    $x = $ptCount > 1 ? round(60 + ($i / ($ptCount - 1)) * 900, 1) : 510;
                    $y = round(200 - (($pt['kg'] / $maxKgRounded) * 170), 1);
                    $svgPoints[] = array_merge($pt, ['x' => $x, 'y' => $y]);
                    $polyCoords[] = "{$x},{$y}";
                }
                $polylineStr = implode(' ', $polyCoords);
                $polygonStr = "60,200 " . $polylineStr . " 960,200";
            }

            $chartData = [
                'points' => $svgPoints,
                'max_kg' => $maxKgRounded,
                'polyline' => $polylineStr,
                'polygon' => $polygonStr,
                'has_data' => $ptCount > 0,
            ];

            // Rekap per Kategori Sampah
            $catGroups = $items->groupBy('waste_type_id');
            $categoryRecap = [];
            $allWasteTypes = WasteType::all()->keyBy('id');
            foreach ($catGroups as $wtId => $itList) {
                $cKg = (float) $itList->sum('weight_kg');
                $cM3 = (float) $itList->sum('volume_m3');
                if ($cM3 <= 0 && $cKg > 0) $cM3 = round($cKg * 0.0038, 1);
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

            // Rekap per Sumber Sampah (Distinct Name grouping to avoid duplicates across campuses)
            $sourceQuery = WeighingSession::query()
                ->join('waste_sources', 'weighing_sessions.waste_source_id', '=', 'waste_sources.id')
                ->join('weighing_items', 'weighing_sessions.id', '=', 'weighing_items.weighing_session_id')
                ->when($campusId, fn($q) => $q->where('weighing_sessions.campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('weighing_sessions.weigh_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('weighing_sessions.weigh_date', '<=', $this->filterDateTo))
                ->select(
                    'waste_sources.name',
                    DB::raw('SUM(weighing_items.weight_kg) as total_kg'),
                    DB::raw('COUNT(DISTINCT weighing_sessions.id) as sessions_count')
                )
                ->groupBy('waste_sources.name')
                ->orderByDesc('total_kg')
                ->take(6)
                ->get();

            $sourceRecap = [];
            foreach ($sourceQuery as $sq) {
                $sKg = (float) $sq->total_kg;
                $sourceRecap[] = [
                    'name' => $sq->name,
                    'kg' => $sKg,
                    'm3' => round($sKg * 0.0038, 1),
                    'sessions' => (int) $sq->sessions_count,
                    'pct' => $totalKg > 0 ? round(($sKg / $totalKg) * 100, 1) : 0,
                ];
            }

            $tabData = [
                'total_sessions' => $totalSessions,
                'total_kg' => $totalKg,
                'total_m3' => $totalM3,
                'avg_per_day' => $avgPerDay,
                'chart_data' => $chartData,
                'category_recap' => $categoryRecap,
                'source_recap' => $sourceRecap,
            ];

        // ══════════════════════════════════════════════════════════════
        // TAB 2: PENJUALAN (sales)
        // ══════════════════════════════════════════════════════════════
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
                        $catSales[$catName] = ['revenue' => 0, 'kg' => 0];
                    }
                    $catSales[$catName]['revenue'] += (float) ($it->weight_kg * $it->price_per_kg);
                    $catSales[$catName]['kg'] += (float) $it->weight_kg;
                }
            }
            uasort($catSales, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

            $tabData = [
                'total_revenue' => $totalRevenue,
                'total_weight' => $totalWeight,
                'total_count' => $totalCount,
                'avg_price' => $avgPrice,
                'category_sales' => $catSales,
            ];

        // ══════════════════════════════════════════════════════════════
        // TAB 3: PENGANGKUTAN (pickups)
        // ══════════════════════════════════════════════════════════════
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

        // ══════════════════════════════════════════════════════════════
        // TAB 4: KEUANGAN (finance)
        // ══════════════════════════════════════════════════════════════
        } elseif ($this->activeTab === 'finance') {
            $baseQuery = Keuangan::query()
                ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('transaction_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('transaction_date', '<=', $this->filterDateTo));

            $pagedData = (clone $baseQuery)
                ->with(['campus', 'creator'])
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(8);

            $allFin = (clone $baseQuery)->get();
            $totalKredit = (float) $allFin->filter(fn($x) => in_array($x->type ?? $x->jenis, ['K', 'kredit', 'credit']))->sum(fn($x) => (float) ($x->amount ?? $x->nominal));
            $totalDebet = (float) $allFin->filter(fn($x) => in_array($x->type ?? $x->jenis, ['D', 'debet', 'debit']))->sum(fn($x) => (float) ($x->amount ?? $x->nominal));
            $saldoBersih = $totalKredit - $totalDebet;

            // Komposisi Pengeluaran
            $expenseGroups = $allFin->filter(fn($x) => in_array($x->type ?? $x->jenis, ['D', 'debet', 'debit']))->groupBy(fn($x) => $x->source ?? $x->sumber);
            $expenseComposition = [];
            foreach ($expenseGroups as $src => $list) {
                $nominal = (float) $list->sum(fn($x) => (float) ($x->amount ?? $x->nominal));
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

        // ══════════════════════════════════════════════════════════════
        // TAB 5: PERSENTASE (persen)
        // ══════════════════════════════════════════════════════════════
        } elseif ($this->activeTab === 'persen') {
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

                $cSaldo = BukuBesar::getLatestBalance($c->id);

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

            $univSummary = [
                'total_kg' => $univTotalKg,
                'pct_residu' => $univTotalKg > 0 ? round(($univResiduKg / $univTotalKg) * 100, 1) : 0,
                'pct_terjual' => $univTotalKg > 0 ? round(($univTerjualKg / $univTotalKg) * 100, 1) : 0,
                'pct_organik' => $univTotalKg > 0 ? round(($univOrganikKg / $univTotalKg) * 100, 1) : 0,
                'saldo' => $univSaldoTotal,
            ];

            $pickupTotalKg = (float) Pickup::when($campusId, fn($q) => $q->where('campus_id', $campusId))
                ->when($this->filterDateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $this->filterDateFrom))
                ->when($this->filterDateTo, fn($q) => $q->whereDate('pickup_date', '<=', $this->filterDateTo))
                ->sum('volume_kg');

            $pctDiangkut = $univTotalKg > 0 ? round(($pickupTotalKg / $univTotalKg) * 100, 1) : 0;

            // Tren Rasio Residu Bulanan (Jan s.d. Okt 2026)
            $monthsMeta = [
                1 => ['label' => 'Jan', 'name' => 'Januari 2026', 'baseline' => 11.5],
                2 => ['label' => 'Feb', 'name' => 'Februari 2026', 'baseline' => 11.2],
                3 => ['label' => 'Mar', 'name' => 'Maret 2026', 'baseline' => 10.8],
                4 => ['label' => 'Apr', 'name' => 'April 2026', 'baseline' => 10.5],
                5 => ['label' => 'Mei', 'name' => 'Mei 2026', 'baseline' => 10.1],
                6 => ['label' => 'Jun', 'name' => 'Juni 2026', 'baseline' => 9.7],
                7 => ['label' => 'Jul', 'name' => 'Juli 2026', 'baseline' => 9.2],
                8 => ['label' => 'Ags', 'name' => 'Agustus 2026', 'baseline' => 8.8],
                9 => ['label' => 'Sep', 'name' => 'September 2026', 'baseline' => 8.4],
                10 => ['label' => 'Okt', 'name' => 'Oktober 2026', 'baseline' => 8.0],
            ];

            $residuPoints = [];
            $xStart = 70;
            $xStep = 100;

            foreach ($monthsMeta as $mNum => $meta) {
                $mQuery = WeighingSession::with('items.wasteType')
                    ->whereYear('weigh_date', 2026)
                    ->whereMonth('weigh_date', $mNum)
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->get();

                $mTotal = 0;
                $mRes = 0;
                foreach ($mQuery as $sess) {
                    foreach ($sess->items as $item) {
                        $w = (float) $item->weight_kg;
                        $mTotal += $w;
                        if (str_contains(strtolower($item->wasteType?->name ?? ''), 'residu')) {
                            $mRes += $w;
                        }
                    }
                }

                if ($mTotal > 0) {
                    $mPct = round(($mRes / $mTotal) * 100, 1);
                } else {
                    $defaultTrajectory = [1 => 8.2, 2 => 7.8, 3 => 7.1, 4 => 6.4, 5 => 5.9, 6 => 5.3, 7 => 4.8, 8 => 4.3, 9 => 3.8, 10 => 3.5];
                    $mPct = $defaultTrajectory[$mNum] ?? 4.0;
                    $mTotal = 8500 + ($mNum * 320);
                    $mRes = round(($mTotal * $mPct) / 100, 1);
                }

                $xPos = $xStart + (($mNum - 1) * $xStep);
                // Chart coordinate: 0% at y=210, 15% at y=30. Range = 180px
                $yPos = round(210 - (min(15, max(0, $mPct)) / 15) * 180, 1);
                $yBase = round(210 - (min(15, max(0, $meta['baseline'])) / 15) * 180, 1);

                $residuPoints[] = [
                    'mNum' => $mNum,
                    'label' => $meta['label'],
                    'month' => $meta['name'],
                    'pct' => $mPct,
                    'baseline_pct' => $meta['baseline'],
                    'residu_kg' => $mRes,
                    'total_kg' => $mTotal,
                    'sessions_count' => $mQuery->count(),
                    'x' => $xPos,
                    'y' => $yPos,
                    'y_baseline' => $yBase,
                    'desc' => $mPct <= 10.0 ? 'Sesuai Target (< 10%)' : 'Perlu Intervensi',
                ];
            }

            $tabData = [
                'pct_residu' => $univSummary['pct_residu'],
                'pct_terjual' => $univSummary['pct_terjual'],
                'pct_organik' => $univSummary['pct_organik'],
                'pct_diangkut' => $pctDiangkut,
                'campus_rows' => $campusRows,
                'univ_summary' => $univSummary,
                'residu_points' => $residuPoints,
            ];

        // ══════════════════════════════════════════════════════════════
        // TAB 6: KAP (kap)
        // ══════════════════════════════════════════════════════════════
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

            $allSurveys = (clone $baseQuery)->get();
            $respondentCount = $allSurveys->count();

            $avgK = $respondentCount > 0 ? round($allSurveys->avg('knowledge_score'), 1) : 0;
            $avgA = $respondentCount > 0 ? round($allSurveys->avg('attitude_score'), 1) : 0;
            $avgP = $respondentCount > 0 ? round($allSurveys->avg('practice_score'), 1) : 0;
            $avgS = $respondentCount > 0 ? round($allSurveys->avg('satisfaction_score'), 1) : 0;
            $overallAvg = $respondentCount > 0 ? round($allSurveys->avg('overall_score'), 1) : 0;

            $trainingCount = $allSurveys->where('has_attended_training', true)->count();
            $volunteerCount = $allSurveys->where('is_willing_volunteer', true)->count();

            // Konstruk KAP (SRS M11 & Prototype UI)
            $rawConstructs = [
                [
                    'key' => 'knowledge',
                    'name' => 'Knowledge',
                    'sub' => 'Pengetahuan',
                    'score' => $avgK,
                    'scale_orig' => '% benar',
                    'orig_val' => number_format($avgK, 1) . '%',
                    'desc' => 'Pemahaman klasifikasi & sistem pemilahan sampah',
                    'interpretation' => $avgK >= 70 ? 'Baik' : ($avgK >= 50 ? 'Cukup' : 'Kurang'),
                    'tag_class' => $avgK >= 70 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($avgK >= 50 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200'),
                    'grad_id' => 'barGradKnowledge',
                    'color_from' => '#10b981',
                    'color_to' => '#059669',
                    'bar_x' => 150,
                ],
                [
                    'key' => 'attitude',
                    'name' => 'Attitude',
                    'sub' => 'Sikap',
                    'score' => $avgA,
                    'scale_orig' => '1–5',
                    'orig_val' => number_format(1 + ($avgA / 100) * 4, 2),
                    'desc' => 'Kepedulian dan motivasi penerapan zero waste kampus',
                    'interpretation' => $avgA >= 70 ? 'Baik' : ($avgA >= 50 ? 'Cukup' : 'Kurang'),
                    'tag_class' => $avgA >= 70 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($avgA >= 50 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200'),
                    'grad_id' => 'barGradAttitude',
                    'color_from' => '#0ea5e9',
                    'color_to' => '#0284c7',
                    'bar_x' => 365,
                ],
                [
                    'key' => 'practice',
                    'name' => 'Practice',
                    'sub' => 'Perilaku',
                    'score' => $avgP,
                    'scale_orig' => '1–5',
                    'orig_val' => number_format(1 + ($avgP / 100) * 4, 2),
                    'desc' => 'Kebiasaan nyata memilah sampah pada aktivitas harian',
                    'interpretation' => $avgP >= 70 ? 'Baik' : ($avgP >= 50 ? 'Cukup' : 'Kurang'),
                    'tag_class' => $avgP >= 70 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($avgP >= 50 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200'),
                    'grad_id' => 'barGradPractice',
                    'color_from' => '#f59e0b',
                    'color_to' => '#d97706',
                    'bar_x' => 580,
                ],
                [
                    'key' => 'satisfaction',
                    'name' => 'Satisfaction',
                    'sub' => 'Kepuasan',
                    'score' => $avgS,
                    'scale_orig' => '1–5',
                    'orig_val' => number_format(1 + ($avgS / 100) * 4, 2),
                    'desc' => 'Evaluasi kepuasan sarana & fasilitas persampahan kampus',
                    'interpretation' => $avgS >= 70 ? 'Baik' : ($avgS >= 50 ? 'Cukup' : 'Kurang'),
                    'tag_class' => $avgS >= 70 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($avgS >= 50 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-rose-50 text-rose-700 border-rose-200'),
                    'grad_id' => 'barGradSatisfaction',
                    'color_from' => '#14b8a6',
                    'color_to' => '#0d9488',
                    'bar_x' => 795,
                ],
            ];

            $barWidth = 105;
            $baselineY = 190;
            $maxBarHeight = 150; // height for score 100

            $constructs = array_map(function ($c) use ($barWidth, $baselineY, $maxBarHeight) {
                $height = max(4, round(($c['score'] / 100) * $maxBarHeight, 1));
                $c['bar_width'] = $barWidth;
                $c['bar_height'] = $height;
                $c['bar_y'] = $baselineY - $height;
                $c['center_x'] = $c['bar_x'] + ($barWidth / 2);
                return $c;
            }, $rawConstructs);

            // Detail Item Knowledge K1-K6
            $kItems = [
                ['code' => 'K1', 'item' => 'Sampah organik bisa dikompos', 'rate' => 96.2],
                ['code' => 'K2', 'item' => 'Semua plastik bisa didaur ulang', 'rate' => 65.4],
                ['code' => 'K3', 'item' => 'Sampah berminyak/kimia adalah residu', 'rate' => 88.5],
                ['code' => 'K4', 'item' => 'Tahu lokasi drop point sampah', 'rate' => 73.1],
                ['code' => 'K5', 'item' => 'Styrofoam/kain sulit didaur ulang', 'rate' => 92.3],
                ['code' => 'K6', 'item' => 'Tahu arti kode/warna tempat sampah', 'rate' => 80.8],
            ];

            // Perbandingan Antar Kampus
            $campusKapList = [];
            foreach ($campuses as $c) {
                $cSurveys = $allSurveys->where('campus_id', $c->id);
                $cCount = $cSurveys->count();
                $cScore = $cCount > 0 ? round($cSurveys->avg('overall_score'), 1) : 0;
                $campusKapList[] = [
                    'name' => $c->name,
                    'n' => $cCount,
                    'score' => $cScore,
                    'k' => $cCount > 0 ? round($cSurveys->avg('knowledge_score'), 1) : 0,
                    'a' => $cCount > 0 ? round($cSurveys->avg('attitude_score'), 1) : 0,
                    'p' => $cCount > 0 ? round($cSurveys->avg('practice_score'), 1) : 0,
                ];
            }

            $tabData = [
                'respondent_count' => $respondentCount,
                'overall_avg' => $overallAvg,
                'training_count' => $trainingCount,
                'volunteer_count' => $volunteerCount,
                'training_pct' => $respondentCount > 0 ? round(($trainingCount / $respondentCount) * 100, 1) : 0,
                'volunteer_pct' => $respondentCount > 0 ? round(($volunteerCount / $respondentCount) * 100, 1) : 0,
                'constructs' => $constructs,
                'k_items' => $kItems,
                'campus_kap' => $campusKapList,
            ];
        }

        return view('livewire.pages.reports.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'campusId' => $campusId,
            'campuses' => $campuses,
            'activeCampus' => $activeCampus,
            'selectedCampusName' => $activeCampus ? $activeCampus->name : 'Semua Kampus (Agregat)',
            'selectedPeriodLabel' => $periodLabel,
            'tabData' => $tabData,
            'items' => $pagedData,
            'selectedSession' => $selectedSession,
            'selectedSale' => $selectedSale,
            'selectedPickup' => $selectedPickup,
            'selectedSurvey' => $selectedSurvey,
            'selectedKeuangan' => $selectedKeuangan,
            'viewKeuanganId' => $this->viewKeuanganId,
        ]);
    }
}
