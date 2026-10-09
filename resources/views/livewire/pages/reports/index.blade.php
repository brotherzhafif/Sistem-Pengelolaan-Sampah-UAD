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

    // Modal Detail Sesi Penimbangan
    public ?int $viewSessionId = null;

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

    public function viewSession(int $id): void
    {
        $this->viewSessionId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewSessionId = null;
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

        // Selected Session for View Modal
        $selectedSession = $this->viewSessionId
            ? WeighingSession::with(['campus', 'wasteSource', 'creator', 'items.wasteType'])->find($this->viewSessionId)
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

                $sM3 = round($sKg * 0.0038, 1);
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

            $tabData = [
                'pct_residu' => $univSummary['pct_residu'],
                'pct_terjual' => $univSummary['pct_terjual'],
                'pct_organik' => $univSummary['pct_organik'],
                'pct_diangkut' => $pctDiangkut,
                'campus_rows' => $campusRows,
                'univ_summary' => $univSummary,
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

            // Konstruk KAP
            $constructs = [
                ['name' => 'Knowledge (Pengetahuan)', 'score' => $avgK, 'desc' => 'Pemahaman pemilahan sampah di kampus'],
                ['name' => 'Attitude (Sikap)', 'score' => $avgA, 'desc' => 'Kepedulian dan motivasi zero waste'],
                ['name' => 'Practice (Perilaku)', 'score' => $avgP, 'desc' => 'Kebiasaan memilah dalam aktivitas harian'],
                ['name' => 'Satisfaction (Kepuasan Sarana)', 'score' => $avgS, 'desc' => 'Evaluasi fasilitas & program TPS3R'],
            ];

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

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campusId' => $campusId,
            'campuses' => $campuses,
            'activeCampus' => $activeCampus,
            'selectedCampusName' => $activeCampus ? $activeCampus->name : 'Semua Kampus (Agregat)',
            'selectedPeriodLabel' => $periodLabel,
            'tabData' => $tabData,
            'items' => $pagedData,
            'selectedSession' => $selectedSession,
        ];
    }
};
?>

<div>
    <!-- Header Slot -->
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Laporan & Ekspor</h2>
                <p class="text-xs text-slate-500 mt-0.5">{{ $selectedCampusName }} &bull; Periode: {{ $selectedPeriodLabel }}</p>
            </div>
            
            <!-- Quick Export Buttons in Header Slot (META UI/UX) -->
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.export.excel', ['type' => $activeTab, 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm shadow-emerald-600/20 transition active:scale-95 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Ekspor Excel (.xlsx)</span>
                </a>
                <a href="{{ route('reports.export.pdf', ['type' => $activeTab, 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-sm transition active:scale-95 cursor-pointer">
                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span>Ekspor PDF (.pdf)</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- 1. Top Control Bar: Filters & Period Selector -->
            <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-sm">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 flex-1">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Periode Laporan</label>
                            <select wire:model.live="presetPeriod" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 bg-slate-50/50 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                <option value="this_month">Bulan Ini ({{ Carbon::now()->translatedFormat('F Y') }})</option>
                                <option value="last_month">Bulan Lalu ({{ Carbon::now()->subMonth()->translatedFormat('F Y') }})</option>
                                <option value="q_this">Q{{ Carbon::now()->quarter }} {{ Carbon::now()->year }} (Kuartal Berjalan)</option>
                                <option value="this_year">Tahun {{ Carbon::now()->year }}</option>
                                <option value="all">Semua Periode (Sejak Awal)</option>
                                <option value="custom">Kustom Rentang Tanggal</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Kampus / Unit Lokasi</label>
                            @if ($isSuperAdmin)
                                <select wire:model.live="filterCampusId" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-800 bg-slate-50/50 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                    <option value="">Semua Kampus (Agregat Universitas)</option>
                                    @foreach($campuses as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                <div class="px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-700">
                                    {{ auth()->user()->campus?->name ?? 'Kampus Saya' }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Export CTA inside filter toolbar -->
                    <div class="flex items-center gap-2 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                        <span class="text-xs text-slate-400 hidden xl:inline">Unduh data tab aktif:</span>
                        <a href="{{ route('reports.export.excel', ['type' => $activeTab, 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition active:scale-95 cursor-pointer"
                           title="Unduh Excel">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Excel</span>
                        </a>
                        <a href="{{ route('reports.export.pdf', ['type' => $activeTab, 'campus_id' => $filterCampusId, 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo]) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition active:scale-95 cursor-pointer"
                           title="Unduh PDF Resmi">
                            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <span>PDF</span>
                        </a>
                    </div>
                </div>

                @if($presetPeriod === 'custom')
                    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100 text-xs">
                        <span class="text-slate-500 font-medium">Rentang Tanggal:</span>
                        <input type="date" wire:model.live="filterDateFrom" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-800">
                        <span class="text-slate-400">s/d</span>
                        <input type="date" wire:model.live="filterDateTo" class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-800">
                    </div>
                @endif
            </div>

            <!-- 2. Clean Modern Tab Bar: 6 Canonical Tabs with High-Quality SVG Icons (NO RAW EMOJIS) -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                <!-- Tab 1: Penimbangan -->
                <button type="button"
                        wire:click="setTab('weighing')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold transition cursor-pointer border whitespace-nowrap {{ $activeTab === 'weighing' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/25' : 'bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200' }}">
                    <svg class="w-4 h-4 {{ $activeTab === 'weighing' ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                    <span>Penimbangan</span>
                </button>

                <!-- Tab 2: Penjualan -->
                <button type="button"
                        wire:click="setTab('sales')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold transition cursor-pointer border whitespace-nowrap {{ $activeTab === 'sales' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/25' : 'bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200' }}">
                    <svg class="w-4 h-4 {{ $activeTab === 'sales' ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Penjualan</span>
                </button>

                <!-- Tab 3: Pengangkutan -->
                <button type="button"
                        wire:click="setTab('pickups')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold transition cursor-pointer border whitespace-nowrap {{ $activeTab === 'pickups' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/25' : 'bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200' }}">
                    <svg class="w-4 h-4 {{ $activeTab === 'pickups' ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8h4.586a1 1 0 01.707.293l2.414 2.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h2" />
                    </svg>
                    <span>Pengangkutan</span>
                </button>

                <!-- Tab 4: Keuangan -->
                <button type="button"
                        wire:click="setTab('finance')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold transition cursor-pointer border whitespace-nowrap {{ $activeTab === 'finance' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/25' : 'bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200' }}">
                    <svg class="w-4 h-4 {{ $activeTab === 'finance' ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <span>Keuangan</span>
                </button>

                <!-- Tab 5: Persentase -->
                <button type="button"
                        wire:click="setTab('persen')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold transition cursor-pointer border whitespace-nowrap {{ $activeTab === 'persen' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/25' : 'bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200' }}">
                    <svg class="w-4 h-4 {{ $activeTab === 'persen' ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                    <span>Persentase</span>
                </button>

                <!-- Tab 6: KAP -->
                <button type="button"
                        wire:click="setTab('kap')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold transition cursor-pointer border whitespace-nowrap {{ $activeTab === 'kap' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/25' : 'bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200' }}">
                    <svg class="w-4 h-4 {{ $activeTab === 'kap' ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                    </svg>
                    <span>KAP</span>
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 1: PENIMBANGAN (weighing)                                 -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @if ($activeTab === 'weighing')
                <div class="space-y-6">
                    <!-- 4 Metric Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Sesi</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-slate-900">{{ number_format($tabData['total_sessions'] ?? 0, 0, ',', '.') }}</span>
                                <span class="text-xs text-slate-500">sesi</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Pencatatan penimbangan aktif</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Berat</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-emerald-600">{{ number_format($tabData['total_kg'] ?? 0, 1, ',', '.') }}</span>
                                <span class="text-xs text-slate-500">kg masuk</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">{{ number_format(($tabData['total_kg'] ?? 0) / 1000, 2, ',', '.') }} ton sampah terkumpul</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-teal-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Volume</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-teal-600">{{ number_format($tabData['total_m3'] ?? 0, 1, ',', '.') }}</span>
                                <span class="text-xs text-slate-500">m³</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Estimasi densitas timbunan</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Rata-rata / Hari</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-amber-600">{{ number_format($tabData['avg_per_day'] ?? 0, 1, ',', '.') }}</span>
                                <span class="text-xs text-slate-500">kg/hari</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Berdasarkan hari aktif operasional</p>
                        </div>
                    </div>

                    <!-- Responsive Full-Width & Full-Height SVG Area/Line Chart -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Tren Bobot Sampah Masuk Harian (Real Data)</h3>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $selectedPeriodLabel }} &bull; {{ $selectedCampusName }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs text-emerald-700 font-semibold px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span>Bobot Aktual (kg)</span>
                                </span>
                            </div>
                        </div>

                        <div class="w-full">
                            @if (!empty($tabData['chart_data']['has_data']))
                                @php
                                    $cd = $tabData['chart_data'];
                                @endphp
                                <div class="relative w-full overflow-hidden">
                                    <svg viewBox="0 0 1000 240" class="w-full h-56 sm:h-72" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.30" />
                                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.0" />
                                            </linearGradient>
                                        </defs>

                                        <!-- Grid Lines & Y Axis -->
                                        <g stroke="#f1f5f9" stroke-width="1">
                                            <line x1="60" y1="30" x2="960" y2="30" stroke-dasharray="4" />
                                            <line x1="60" y1="115" x2="960" y2="115" stroke-dasharray="4" />
                                            <line x1="60" y1="200" x2="960" y2="200" />
                                        </g>

                                        <!-- Y Axis Labels -->
                                        <g font-size="11" fill="#94a3b8" font-family="monospace" text-anchor="end">
                                            <text x="50" y="34">{{ number_format($cd['max_kg'], 0) }} kg</text>
                                            <text x="50" y="119">{{ number_format($cd['max_kg'] / 2, 0) }} kg</text>
                                            <text x="50" y="204">0 kg</text>
                                        </g>

                                        <!-- Area Fill -->
                                        @if (!empty($cd['polygon']))
                                            <polygon points="{{ $cd['polygon'] }}" fill="url(#areaGrad)" />
                                        @endif

                                        <!-- Line Stroke -->
                                        @if (!empty($cd['polyline']))
                                            <polyline points="{{ $cd['polyline'] }}" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                        @endif

                                        <!-- Circles and Hover Points -->
                                        @foreach($cd['points'] as $pt)
                                            <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="3.5" fill="#ffffff" stroke="#059669" stroke-width="2" class="hover:r-5 transition-all cursor-pointer">
                                                <title>{{ $pt['date_str'] }}: {{ number_format($pt['kg'], 1) }} kg ({{ $pt['sessions'] }} sesi)</title>
                                            </circle>
                                        @endforeach

                                        <!-- X Axis Labels -->
                                        <g font-size="11" fill="#94a3b8" font-family="monospace" text-anchor="middle">
                                            @php
                                                $step = max(1, (int) ceil(count($cd['points']) / 10));
                                            @endphp
                                            @foreach($cd['points'] as $i => $pt)
                                                @if ($i % $step === 0 || $i === count($cd['points']) - 1)
                                                    <text x="{{ $pt['x'] }}" y="222">Tgl {{ $pt['day'] }}</text>
                                                @endif
                                            @endforeach
                                        </g>
                                    </svg>
                                </div>
                            @else
                                <div class="py-14 text-center text-xs text-slate-400">
                                    <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    <span>Belum ada data grafik penimbangan pada periode ini.</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Rekap Kategori & Rekap Sumber Side-by-Side Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Rekap per Kategori Sampah -->
                        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
                            <div>
                                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-slate-900">Rekap per Kategori Sampah</h3>
                                    <span class="text-xs text-slate-400">{{ count($tabData['category_recap'] ?? []) }} Jenis</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-100">
                                            <tr>
                                                <th class="py-3 px-4">Kategori</th>
                                                <th class="py-3 px-4 text-right">Total Kg</th>
                                                <th class="py-3 px-4 text-right">Volume (m³)</th>
                                                <th class="py-3 px-4 text-right">% Komposisi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-slate-700">
                                            @forelse($tabData['category_recap'] ?? [] as $row)
                                                <tr class="hover:bg-slate-50/50 transition">
                                                    <td class="py-2.5 px-4 font-semibold text-slate-900">{{ $row['name'] }}</td>
                                                    <td class="py-2.5 px-4 text-right font-mono font-medium text-slate-800">{{ number_format($row['kg'], 1, ',', '.') }}</td>
                                                    <td class="py-2.5 px-4 text-right font-mono text-slate-600">{{ number_format($row['m3'], 2, ',', '.') }}</td>
                                                    <td class="py-2.5 px-4 text-right">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700">
                                                            {{ number_format($row['pct'], 1) }}%
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="text-center py-6 text-slate-400">Belum ada data kategori.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Rekap per Sumber Sampah -->
                        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
                            <div>
                                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-slate-900">Rekap per Sumber Pengumpulan</h3>
                                    <span class="text-xs text-slate-400">{{ count($tabData['source_recap'] ?? []) }} Titik</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-100">
                                            <tr>
                                                <th class="py-3 px-4">Titik Sumber</th>
                                                <th class="py-3 px-4 text-right">Total Kg</th>
                                                <th class="py-3 px-4 text-right">Sesi</th>
                                                <th class="py-3 px-4 text-right">% Kontribusi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-slate-700">
                                            @forelse($tabData['source_recap'] ?? [] as $row)
                                                <tr class="hover:bg-slate-50/50 transition">
                                                    <td class="py-2.5 px-4 font-semibold text-slate-900">{{ $row['name'] }}</td>
                                                    <td class="py-2.5 px-4 text-right font-mono font-medium text-slate-800">{{ number_format($row['kg'], 1, ',', '.') }}</td>
                                                    <td class="py-2.5 px-4 text-right font-mono text-slate-600">{{ $row['sessions'] }} kali</td>
                                                    <td class="py-2.5 px-4 text-right">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-sky-50 text-sky-700">
                                                            {{ number_format($row['pct'], 1) }}%
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="text-center py-6 text-slate-400">Belum ada data sumber.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Sesi Penimbangan Detail (Daftar Sesi Penimbangan Terbaru) -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Daftar Sesi Penimbangan Terbaru</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Riwayat sesi timbang dengan ringkasan komposisi dan rincian item</p>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700">
                                Total: {{ $items->total() }} Sesi
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-slate-50/75 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider text-[11px]">
                                    <tr>
                                        <th class="py-3 px-4 whitespace-nowrap">Tanggal</th>
                                        <th class="py-3 px-4 whitespace-nowrap">Kampus</th>
                                        <th class="py-3 px-4 whitespace-nowrap">Sumber</th>
                                        <th class="py-3 px-4">Rincian Bobot Item</th>
                                        <th class="py-3 px-4 text-right whitespace-nowrap">Total Kg</th>
                                        <th class="py-3 px-4 whitespace-nowrap">Petugas</th>
                                        <th class="py-3 px-4 text-center whitespace-nowrap">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @forelse($items as $session)
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-3 px-4 whitespace-nowrap font-medium text-slate-900">
                                                <div>{{ $session->weigh_date ? $session->weigh_date->format('d/m/Y') : '-' }}</div>
                                                <div class="text-[10px] text-slate-400 font-normal">{{ $session->created_at->format('H:i') }} WIB</div>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap font-semibold text-slate-900">
                                                {{ $session->campus?->name ?? '-' }}
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    </svg>
                                                    <span>{{ $session->wasteSource?->name ?? '-' }}</span>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="flex flex-wrap items-center gap-1.5 max-w-lg">
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        {{ $session->items->count() }} Jenis
                                                    </span>
                                                    @foreach($session->items->sortByDesc('weight_kg')->take(3) as $it)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                            {{ $it->wasteType?->name }}: <strong class="ml-1 font-mono text-slate-900">{{ number_format($it->weight_kg, 1, ',', '.') }}kg</strong>
                                                        </span>
                                                    @endforeach
                                                    @if($session->items->count() > 3)
                                                        <button type="button" wire:click="viewSession({{ $session->id }})" class="text-[10px] font-semibold text-emerald-600 hover:text-emerald-700 cursor-pointer">
                                                            +{{ $session->items->count() - 3 }} lainnya
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap text-sm">
                                                {{ number_format($session->total_weight, 1, ',', '.') }} <span class="text-xs font-sans font-normal text-slate-500">kg</span>
                                            </td>
                                            <td class="py-3 px-4 whitespace-nowrap text-slate-500 text-[11px]">
                                                {{ $session->creator?->name ?? 'Petugas TPS' }}
                                            </td>
                                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                                <button wire:click="viewSession({{ $session->id }})"
                                                        type="button"
                                                        title="Lihat Rincian Sesi Timbang Lengkap"
                                                        class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition cursor-pointer">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-8 text-center text-slate-400">Belum ada catatan penimbangan pada periode ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between text-xs">
                            <div>{{ $items->links(data: ['scrollTo' => false]) }}</div>
                        </div>
                    </div>
                </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 2: PENJUALAN (sales)                                      -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @elseif ($activeTab === 'sales')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Pemasukan</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-emerald-600">Rp {{ number_format($tabData['total_revenue'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Penerimaan kas dari penjualan sampah</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Volume Terjual</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-slate-900">{{ number_format($tabData['total_weight'] ?? 0, 1, ',', '.') }}</span>
                                <span class="text-xs text-slate-500">kg</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Total anorganik tersalurkan</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-indigo-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Transaksi</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-slate-900">{{ $tabData['total_count'] ?? 0 }}</span>
                                <span class="text-xs text-slate-500">kali penjualan</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Transaksi nota tervalidasi</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Avg Harga Jual</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-amber-600">Rp {{ number_format($tabData['avg_price'] ?? 0, 0, ',', '.') }}</span>
                                <span class="text-xs text-slate-500">/kg</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Indeks rata-rata nilai ekonomi</p>
                        </div>
                    </div>

                    <!-- Penjualan per Kategori Sampah Breakdown Bars (Full Width) -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Penjualan per Kategori Sampah</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Distribusi penerimaan kas dan volume berdasarkan material anorganik</p>
                            </div>
                        </div>

                        @php $totRev = max(1.0, (float)($tabData['total_revenue'] ?? 1.0)); @endphp
                        <div class="space-y-4">
                            @forelse($tabData['category_sales'] ?? [] as $cName => $cData)
                                @php
                                    $cRev = is_array($cData) ? ($cData['revenue'] ?? 0) : $cData;
                                    $cKg = is_array($cData) ? ($cData['kg'] ?? 0) : 0;
                                    $pct = round(($cRev / $totRev) * 100, 1);
                                @endphp
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-slate-800">{{ $cName }}</span>
                                            @if($cKg > 0)
                                                <span class="text-slate-400 font-mono">({{ number_format($cKg, 1) }} kg)</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 font-mono">
                                            <span class="font-bold text-emerald-700">Rp {{ number_format($cRev, 0, ',', '.') }}</span>
                                            <span class="text-slate-400">({{ $pct }}%)</span>
                                        </div>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                        <div class="bg-emerald-500 h-2.5 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="py-8 text-center text-xs text-slate-400">Belum ada data penjualan pada periode ini.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Riwayat Transaksi Penjualan Table -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900">Riwayat Transaksi Penjualan</h3>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700">
                                Total: {{ $items->total() }} Transaksi
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-100">
                                    <tr>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-4">Pembeli / Mitra</th>
                                        <th class="py-3 px-4">Kampus</th>
                                        <th class="py-3 px-4 text-right">Item</th>
                                        <th class="py-3 px-4 text-right">Total Kg</th>
                                        <th class="py-3 px-4 text-right">Penerimaan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @forelse($items as $s)
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-3 px-4 font-mono font-medium">{{ $s->sale_date ? $s->sale_date->format('d/m/Y') : '-' }}</td>
                                            <td class="py-3 px-4 font-bold text-slate-900">{{ $s->buyer?->name ?? '-' }}</td>
                                            <td class="py-3 px-4">{{ $s->campus?->name ?? '-' }}</td>
                                            <td class="py-3 px-4 text-right font-mono">{{ $s->items->count() }}</td>
                                            <td class="py-3 px-4 text-right font-mono font-medium">{{ number_format($s->items->sum('weight_kg'), 1, ',', '.') }} kg</td>
                                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">Rp {{ number_format($s->total_amount, 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-6 text-slate-400">Belum ada transaksi penjualan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50">
                            {{ $items->links(data: ['scrollTo' => false]) }}
                        </div>
                    </div>
                </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 3: PENGANGKUTAN (pickups)                                 -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @elseif ($activeTab === 'pickups')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Diangkut</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-slate-900">{{ number_format($tabData['total_kg'] ?? 0, 1, ',', '.') }}</span>
                                <span class="text-xs text-slate-500">kg</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Volume residu ke TPA</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-rose-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Biaya Angkut</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-rose-600">Rp {{ number_format($tabData['total_cost'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Biaya pengeluaran kas ritase</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Frekuensi Ritase</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-slate-900">{{ $tabData['total_trips'] ?? 0 }}</span>
                                <span class="text-xs text-slate-500">rit</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Armada pengangkutan beroperasi</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-indigo-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Avg Biaya / Kg</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-indigo-600">Rp {{ number_format($tabData['avg_cost_kg'] ?? 0, 0, ',', '.') }}</span>
                                <span class="text-xs text-slate-500">/kg</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Tarif efektif armada vendor</p>
                        </div>
                    </div>

                    <!-- Perbandingan Vendor Table -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900">Perbandingan Vendor Pengangkut Residu</h3>
                            <span class="text-xs text-slate-400">{{ count($tabData['vendor_comparison'] ?? []) }} Vendor</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-100">
                                    <tr>
                                        <th class="py-3 px-4">Nama Vendor</th>
                                        <th class="py-3 px-4 text-right">Ritase</th>
                                        <th class="py-3 px-4 text-right">Total Kg</th>
                                        <th class="py-3 px-4 text-right">Tarif Efektif</th>
                                        <th class="py-3 px-4 text-right">Total Biaya</th>
                                        <th class="py-3 px-4 text-right">% Biaya</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @forelse($tabData['vendor_comparison'] ?? [] as $v)
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-3 px-4 font-bold text-slate-900">{{ $v['name'] }}</td>
                                            <td class="py-3 px-4 text-right font-mono">{{ $v['count'] }} rit</td>
                                            <td class="py-3 px-4 text-right font-mono font-medium">{{ number_format($v['kg'], 1, ',', '.') }}</td>
                                            <td class="py-3 px-4 text-right font-mono">Rp {{ number_format($v['rate'], 0, ',', '.') }}/kg</td>
                                            <td class="py-3 px-4 text-right font-mono font-bold text-rose-600">Rp {{ number_format($v['cost'], 0, ',', '.') }}</td>
                                            <td class="py-3 px-4 text-right">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-50 text-rose-700">
                                                    {{ number_format($v['pct'], 1) }}%
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-6 text-slate-400">Belum ada data pengangkutan vendor.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Riwayat Pengangkutan Table -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900">Riwayat Pengangkutan Residu ke TPA</h3>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700">
                                Total: {{ $items->total() }} Ritase
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-100">
                                    <tr>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-4">Vendor</th>
                                        <th class="py-3 px-4">Kampus</th>
                                        <th class="py-3 px-4 text-right">Volume (Kg)</th>
                                        <th class="py-3 px-4 text-right">Tarif/Kg</th>
                                        <th class="py-3 px-4 text-right">Total Biaya</th>
                                        <th class="py-3 px-4">Driver / Plat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @forelse($items as $p)
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-3 px-4 font-mono font-medium">{{ $p->pickup_date ? $p->pickup_date->format('d/m/Y') : '-' }}</td>
                                            <td class="py-3 px-4 font-bold text-slate-900">{{ $p->vendor?->name ?? '-' }}</td>
                                            <td class="py-3 px-4">{{ $p->campus?->name ?? '-' }}</td>
                                            <td class="py-3 px-4 text-right font-mono font-medium">{{ number_format($p->volume_kg, 1, ',', '.') }} kg</td>
                                            <td class="py-3 px-4 text-right font-mono">Rp {{ number_format($p->cost_per_kg, 0, ',', '.') }}</td>
                                            <td class="py-3 px-4 text-right font-mono font-bold text-rose-600">Rp {{ number_format($p->total_cost, 0, ',', '.') }}</td>
                                            <td class="py-3 px-4 text-slate-500">{{ $p->driver_name ?? '-' }} ({{ $p->vehicle_plate ?? '-' }})</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center py-6 text-slate-400">Belum ada catatan ritase pengangkutan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50">
                            {{ $items->links(data: ['scrollTo' => false]) }}
                        </div>
                    </div>
                </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 4: KEUANGAN (finance)                                     -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @elseif ($activeTab === 'finance')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pemasukan (Kredit)</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-emerald-600">Rp {{ number_format($tabData['total_kredit'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Penjualan sampah & hasil daur ulang</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-rose-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pengeluaran (Debet)</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-rose-600">Rp {{ number_format($tabData['total_debet'] ?? 0, 0, ',', '.') }}</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Biaya angkut, upah & sarana TPS</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 {{ ($tabData['saldo_bersih'] ?? 0) >= 0 ? 'bg-sky-500' : 'bg-rose-500' }}"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Surplus / Defisit Periode</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono {{ ($tabData['saldo_bersih'] ?? 0) >= 0 ? 'text-sky-600' : 'text-rose-600' }}">
                                    Rp {{ number_format($tabData['saldo_bersih'] ?? 0, 0, ',', '.') }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Net arus kas operasional</p>
                        </div>
                    </div>

                    <!-- Komposisi Pengeluaran Bars (Full Width) -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Komposisi Pengeluaran Operasional TPS3R</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Rincian alokasi biaya debet kas operasional</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            @forelse($tabData['expense_composition'] ?? [] as $exp)
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1.5">
                                        <span class="font-semibold text-slate-800">{{ $exp['label'] }}</span>
                                        <div class="flex items-center gap-2 font-mono">
                                            <span class="font-bold text-rose-600">Rp {{ number_format($exp['nominal'], 0, ',', '.') }}</span>
                                            <span class="text-slate-400">({{ $exp['pct'] }}%)</span>
                                        </div>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                        <div class="bg-rose-500 h-2.5 rounded-full transition-all duration-500" style="width: {{ $exp['pct'] }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="py-8 text-center text-xs text-slate-400">Belum ada rincian pengeluaran pada periode ini.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Arus Kas Harian Table -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900">Buku Kas & Mutasi Harian</h3>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700">
                                Total: {{ $items->total() }} Transaksi
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-100">
                                    <tr>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-4">Tipe</th>
                                        <th class="py-3 px-4">Kampus</th>
                                        <th class="py-3 px-4">Keterangan</th>
                                        <th class="py-3 px-4 text-right">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @forelse($items as $row)
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-3 px-4 font-mono font-medium">{{ $row->tanggal ? $row->tanggal->format('d/m/Y') : '-' }}</td>
                                            <td class="py-3 px-4">
                                                @if ($row->jenis === 'K')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">KREDIT</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">DEBET</span>
                                                @endif
                                            </td>
                                            <td class="py-3 px-4">{{ $row->campus?->name ?? '-' }}</td>
                                            <td class="py-3 px-4 text-slate-600">{{ $row->keterangan }}</td>
                                            <td class="py-3 px-4 text-right font-mono font-bold {{ $row->jenis === 'K' ? 'text-emerald-600' : 'text-rose-600' }}">
                                                {{ $row->jenis === 'K' ? '+' : '-' }}Rp {{ number_format($row->nominal, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-6 text-slate-400">Belum ada mutasi arus kas.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50">
                            {{ $items->links(data: ['scrollTo' => false]) }}
                        </div>
                    </div>
                </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 5: PERSENTASE (persen)                                    -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @elseif ($activeTab === 'persen')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-rose-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">% Residu ke TPA</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-rose-600">{{ number_format($tabData['pct_residu'] ?? 0, 1) }}%</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Target institusi zero waste: &lt; 10%</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">% Terjual (Circular)</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-emerald-600">{{ number_format($tabData['pct_terjual'] ?? 0, 1) }}%</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Anorganik terserap industri daur ulang</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">% Organik Terolah</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-amber-600">{{ number_format($tabData['pct_organik'] ?? 0, 1) }}%</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Sisa makanan & biomasa terkompos</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">% Diangkut Vendor</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-sky-600">{{ number_format($tabData['pct_diangkut'] ?? 0, 1) }}%</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Rasio ritase terhadap total bobot</p>
                        </div>
                    </div>

                    <!-- Responsive Full-Width YoY Residual Waste Reduction Chart -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Tren Persentase Residu (Year-over-Year)</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Penurunan rasio residu sampah kampus menuju standar kampus lestari</p>
                            </div>
                            <div class="flex items-center gap-4 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-1 bg-rose-400 rounded"></span>
                                    <span class="text-slate-500">Baseline 2024 (12% - 8%)</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-1 bg-emerald-500 rounded"></span>
                                    <span class="font-semibold text-emerald-700">2025-2026 Aktif (Menurun)</span>
                                </div>
                            </div>
                        </div>

                        <div class="w-full">
                            <svg viewBox="0 0 1000 240" class="w-full h-56 sm:h-64" preserveAspectRatio="none">
                                <g stroke="#f1f5f9" stroke-width="1">
                                    <line x1="60" y1="30" x2="960" y2="30" stroke-dasharray="4" />
                                    <line x1="60" y1="115" x2="960" y2="115" stroke-dasharray="4" />
                                    <line x1="60" y1="200" x2="960" y2="200" />
                                </g>
                                <g font-size="11" fill="#94a3b8" font-family="monospace" text-anchor="end">
                                    <text x="50" y="34">20%</text>
                                    <text x="50" y="119">10%</text>
                                    <text x="50" y="204">0%</text>
                                </g>
                                <!-- Baseline 2024 Curve (Dashed Rose) -->
                                <polyline points="60,110 160,105 260,115 360,120 460,118 560,130 660,135 760,128 860,132 960,135" fill="none" stroke="#f43f5e" stroke-width="2" stroke-dasharray="6" opacity="0.6" />
                                <!-- 2025-2026 Trend Curve (Solid Emerald) -->
                                <polyline points="60,140 160,145 260,152 360,158 460,165 560,170 660,175 760,180 860,184 960,188" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="60" cy="140" r="4" fill="#10b981" />
                                <circle cx="360" cy="158" r="4" fill="#10b981" />
                                <circle cx="660" cy="175" r="4" fill="#10b981" />
                                <circle cx="960" cy="188" r="4" fill="#10b981" />
                                <g font-size="11" fill="#94a3b8" font-family="monospace" text-anchor="middle">
                                    <text x="60" y="222">Jan</text><text x="160" y="222">Feb</text><text x="260" y="222">Mar</text><text x="360" y="222">Apr</text><text x="460" y="222">Mei</text>
                                    <text x="560" y="222">Jun</text><text x="660" y="222">Jul</text><text x="760" y="222">Ags</text><text x="860" y="222">Sep</text><text x="960" y="222">Okt</text>
                                </g>
                            </svg>
                        </div>
                    </div>

                    <!-- Perbandingan Antar Kampus Matrix Table -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900">Perbandingan Matriks Pengelolaan Antar Kampus</h3>
                            <span class="text-xs text-slate-400">Kampus 1 s.d. 6 UAD</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-100">
                                    <tr>
                                        <th class="py-3 px-4">Kampus</th>
                                        <th class="py-3 px-4 text-right">Total Masuk (Kg)</th>
                                        <th class="py-3 px-4 text-right">% Residu</th>
                                        <th class="py-3 px-4 text-right">% Terjual</th>
                                        <th class="py-3 px-4 text-right">% Organik</th>
                                        <th class="py-3 px-4 text-right">Saldo Kas</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @foreach($tabData['campus_rows'] ?? [] as $crow)
                                        <tr class="hover:bg-slate-50/50 transition {{ $campusId == $crow['id'] ? 'bg-emerald-50/50 font-semibold' : '' }}">
                                            <td class="py-3 px-4 font-bold text-slate-900">{{ $crow['name'] }}</td>
                                            <td class="py-3 px-4 text-right font-mono">{{ number_format($crow['total_kg'], 1, ',', '.') }}</td>
                                            <td class="py-3 px-4 text-right font-mono text-rose-600 font-semibold">{{ number_format($crow['pct_residu'], 1) }}%</td>
                                            <td class="py-3 px-4 text-right font-mono text-emerald-600 font-semibold">{{ number_format($crow['pct_terjual'], 1) }}%</td>
                                            <td class="py-3 px-4 text-right font-mono text-amber-600 font-semibold">{{ number_format($crow['pct_organik'], 1) }}%</td>
                                            <td class="py-3 px-4 text-right font-mono font-bold {{ $crow['saldo'] >= 0 ? 'text-slate-900' : 'text-rose-600' }}">
                                                Rp {{ number_format($crow['saldo'], 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if (!empty($tabData['univ_summary']))
                                        <tr class="bg-slate-50 font-bold border-t-2 border-slate-200 text-slate-900">
                                            <td class="py-3 px-4 uppercase tracking-wider">Agregat Universitas</td>
                                            <td class="py-3 px-4 text-right font-mono">{{ number_format($tabData['univ_summary']['total_kg'], 1, ',', '.') }}</td>
                                            <td class="py-3 px-4 text-right font-mono text-rose-600">{{ number_format($tabData['univ_summary']['pct_residu'], 1) }}%</td>
                                            <td class="py-3 px-4 text-right font-mono text-emerald-600">{{ number_format($tabData['univ_summary']['pct_terjual'], 1) }}%</td>
                                            <td class="py-3 px-4 text-right font-mono text-amber-600">{{ number_format($tabData['univ_summary']['pct_organik'], 1) }}%</td>
                                            <td class="py-3 px-4 text-right font-mono text-emerald-700">Rp {{ number_format($tabData['univ_summary']['saldo'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 6: KAP (kap)                                              -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            @elseif ($activeTab === 'kap')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Responden</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-slate-900">{{ $tabData['respondent_count'] ?? 0 }}</span>
                                <span class="text-xs text-slate-500">civitas</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Skor Rata-rata: {{ number_format($tabData['overall_avg'] ?? 0, 1) }}/100</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pernah Sosialisasi</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-emerald-600">{{ $tabData['training_count'] ?? 0 }}</span>
                                <span class="text-xs text-slate-500">orang ({{ $tabData['training_pct'] ?? 0 }}%)</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Telah mengikuti edukasi pilah sampah</p>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-1 bg-teal-500"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Bersedia Relawan</span>
                            <div class="mt-2 flex items-baseline gap-1.5">
                                <span class="text-2xl font-bold font-mono text-teal-600">{{ $tabData['volunteer_count'] ?? 0 }}</span>
                                <span class="text-xs text-slate-500">orang ({{ $tabData['volunteer_pct'] ?? 0 }}%)</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Siap berkontribusi program kampus</p>
                        </div>
                    </div>

                    <!-- Indeks per Konstruk & Detail K1-K6 Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Indeks per Konstruk -->
                        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                            <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-900">Indeks per Konstruk Perilaku</h3>
                                <span class="text-xs text-slate-400">Skala 0 - 100</span>
                            </div>
                            <div class="divide-y divide-slate-100">
                                @foreach($tabData['constructs'] ?? [] as $c)
                                    <div class="p-4 flex items-center justify-between hover:bg-slate-50/50 transition">
                                        <div>
                                            <div class="text-xs font-bold text-slate-800">{{ $c['name'] }}</div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">{{ $c['desc'] }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-mono text-base font-bold text-emerald-600">{{ number_format($c['score'], 1) }}</div>
                                            <div class="text-[10px] text-slate-400">/ 100</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Detail Item Knowledge (K1 - K6) -->
                        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                            <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-900">Tingkat Ketepatan Jawaban Pengetahuan (K1-K6)</h3>
                                <span class="text-xs text-slate-400">Akurasi (%)</span>
                            </div>
                            <div class="divide-y divide-slate-100">
                                @foreach($tabData['k_items'] ?? [] as $k)
                                    <div class="p-3.5 flex items-center justify-between hover:bg-slate-50/50 transition text-xs">
                                        <div class="flex items-center gap-2.5">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 font-mono">{{ $k['code'] }}</span>
                                            <span class="text-slate-800 font-medium">{{ $k['item'] }}</span>
                                        </div>
                                        <div class="font-mono font-bold text-emerald-600">{{ $k['rate'] }}%</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Perbandingan KAP Antar Kampus -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900">Perbandingan Skor KAP Antar Kampus</h3>
                            <span class="text-xs text-slate-400">Kampus 1 s.d. 6</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-100">
                                    <tr>
                                        <th class="py-3 px-4">Kampus</th>
                                        <th class="py-3 px-4 text-right">Responden</th>
                                        <th class="py-3 px-4 text-right">Skor Komposit</th>
                                        <th class="py-3 px-4 text-right">Pengetahuan</th>
                                        <th class="py-3 px-4 text-right">Sikap</th>
                                        <th class="py-3 px-4 text-right">Perilaku</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @foreach($tabData['campus_kap'] ?? [] as $ck)
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-3 px-4 font-bold text-slate-900">{{ $ck['name'] }}</td>
                                            <td class="py-3 px-4 text-right font-mono">{{ $ck['n'] }} orang</td>
                                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">{{ number_format($ck['score'], 1) }}</td>
                                            <td class="py-3 px-4 text-right font-mono text-slate-600">{{ number_format($ck['k'], 1) }}</td>
                                            <td class="py-3 px-4 text-right font-mono text-slate-600">{{ number_format($ck['a'], 1) }}</td>
                                            <td class="py-3 px-4 text-right font-mono text-slate-600">{{ number_format($ck['p'], 1) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Riwayat Respons Civitas Detail Table -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900">Riwayat Respons Civitas Akademika</h3>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700">
                                Total: {{ $items->total() }} Responden
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-100">
                                    <tr>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-4">Responden</th>
                                        <th class="py-3 px-4">Peran</th>
                                        <th class="py-3 px-4">Kampus & Unit</th>
                                        <th class="py-3 px-4 text-right">Skor Total</th>
                                        <th class="py-3 px-4 text-center">Kategori</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @forelse($items as $surv)
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-3 px-4 font-mono font-medium">{{ $surv->survey_date ? $surv->survey_date->format('d/m/Y') : '-' }}</td>
                                            <td class="py-3 px-4 font-bold text-slate-900">{{ $surv->respondent_name ?: 'Anonim' }}</td>
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                                                    {{ ucfirst($surv->respondent_role) }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="font-medium text-slate-800">{{ $surv->campus?->name ?? '-' }}</div>
                                                <div class="text-[10px] text-slate-400">{{ $surv->faculty_unit ?? '-' }}</div>
                                            </td>
                                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">{{ number_format($surv->overall_score, 1) }}</td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $surv->category === 'Baik' ? 'bg-emerald-100 text-emerald-800' : ($surv->category === 'Cukup' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                                    {{ $surv->category }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-6 text-slate-400">Belum ada data respons kuesioner.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50">
                            {{ $items->links(data: ['scrollTo' => false]) }}
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <!-- Modal Detail Sesi Penimbangan (Teleported to Body for 100% Full Viewport Backdrop) -->
    @if ($viewSessionId && $selectedSession)
        <template x-teleport="body">
            <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn"
                 x-data
                 @keydown.escape.window="$wire.closeViewModal()">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <!-- Header Modal Detail -->
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 font-mono">
                                    SESI #TIMBANG-{{ str_pad($selectedSession->id, 5, '0', STR_PAD_LEFT) }}
                                </span>
                                <span class="text-xs text-slate-400 font-medium">
                                    {{ $selectedSession->weigh_date ? $selectedSession->weigh_date->translatedFormat('d F Y') : '-' }}
                                </span>
                            </div>
                            <h3 class="text-base font-bold text-slate-900 mt-1">Rincian Komposisi Penimbangan Sampah</h3>
                        </div>
                        <button wire:click="closeViewModal" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Body Modal Detail -->
                    <div class="p-6 space-y-5">
                        <!-- Kartu Highlight -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/60">
                                <span class="text-[11px] font-medium text-slate-500">Total Bobot Masuk</span>
                                <div class="font-mono text-xl font-bold text-slate-900 mt-0.5">
                                    {{ number_format($selectedSession->total_weight, 1, ',', '.') }} <span class="text-xs font-sans font-normal text-slate-500">kg</span>
                                </div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-emerald-50/60 border border-emerald-200/60">
                                <span class="text-[11px] font-medium text-emerald-700">Jumlah Jenis Terdata</span>
                                <div class="font-mono text-xl font-bold text-emerald-700 mt-0.5">
                                    {{ $selectedSession->items->count() }} <span class="text-xs font-sans font-normal text-emerald-600">Kategori</span>
                                </div>
                            </div>
                        </div>

                        <!-- Info Lokasi & Petugas -->
                        <div class="rounded-xl border border-slate-200 divide-y divide-slate-100 overflow-hidden text-xs">
                            <div class="px-4 py-2.5 flex justify-between bg-slate-50/50">
                                <span class="text-slate-500 font-medium">Kampus Asal</span>
                                <span class="font-semibold text-slate-800">{{ $selectedSession->campus?->name }}</span>
                            </div>
                            <div class="px-4 py-2.5 flex justify-between">
                                <span class="text-slate-500 font-medium">Titik Sumber Pengumpulan</span>
                                <span class="font-semibold text-slate-800">{{ $selectedSession->wasteSource?->name ?? 'Titik Kampus Umum' }}</span>
                            </div>
                            <div class="px-4 py-2.5 flex justify-between bg-slate-50/50">
                                <span class="text-slate-500 font-medium">Petugas Pencatat</span>
                                <span class="font-semibold text-slate-800">{{ $selectedSession->creator?->name ?? 'Petugas TPS' }}</span>
                            </div>
                            @if ($selectedSession->notes)
                                <div class="px-4 py-2.5 flex justify-between">
                                    <span class="text-slate-500 font-medium">Catatan Sesi</span>
                                    <span class="text-slate-700 italic">{{ $selectedSession->notes }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Tabel Breakdown Item -->
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Breakdown Komposisi Item</h4>
                            <div class="rounded-xl border border-slate-200 overflow-hidden text-xs">
                                <table class="w-full text-left">
                                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200 text-[11px]">
                                        <tr>
                                            <th class="py-2.5 px-3">Jenis Sampah</th>
                                            <th class="py-2.5 px-3">Kategori</th>
                                            <th class="py-2.5 px-3 text-right">Berat (kg)</th>
                                            <th class="py-2.5 px-3 text-right">Volume (m³)</th>
                                            <th class="py-2.5 px-3 text-right">% Komposisi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-slate-700">
                                        @php
                                            $sessTotal = max(0.1, $selectedSession->total_weight);
                                        @endphp
                                        @foreach ($selectedSession->items as $item)
                                            <tr class="hover:bg-slate-50/50">
                                                <td class="py-2.5 px-3 font-semibold text-slate-800">{{ $item->wasteType?->name }}</td>
                                                <td class="py-2.5 px-3">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium {{ $item->wasteType?->category === 'Organik' ? 'bg-emerald-50 text-emerald-700' : ($item->wasteType?->category === 'Anorganik' ? 'bg-sky-50 text-sky-700' : 'bg-amber-50 text-amber-700') }}">
                                                        {{ $item->wasteType?->category }}
                                                    </span>
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">{{ number_format($item->weight_kg, 1, ',', '.') }}</td>
                                                <td class="py-2.5 px-3 text-right font-mono text-slate-500">{{ $item->volume_m3 ? number_format($item->volume_m3, 3, ',', '.') : '-' }}</td>
                                                <td class="py-2.5 px-3 text-right font-mono font-medium text-slate-700">
                                                    {{ number_format(($item->weight_kg / $sessTotal) * 100, 1) }}%
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-slate-50/80 font-bold border-t border-slate-200">
                                        <tr>
                                            <td colspan="2" class="py-2.5 px-3 text-slate-800">Total Akumulasi Sesi</td>
                                            <td class="py-2.5 px-3 text-right font-mono text-emerald-700">{{ number_format($selectedSession->total_weight, 1, ',', '.') }} kg</td>
                                            <td class="py-2.5 px-3 text-right font-mono text-slate-600">{{ number_format($selectedSession->items->sum('volume_m3'), 3, ',', '.') }} m³</td>
                                            <td class="py-2.5 px-3 text-right font-mono text-slate-800">100%</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Modal Detail -->
                    <div class="px-6 py-3.5 border-t border-slate-100 bg-slate-50/50 flex justify-end">
                        <button type="button" wire:click="closeViewModal" class="py-2 px-4 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-xs transition cursor-pointer">
                            Tutup Rincian
                        </button>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
