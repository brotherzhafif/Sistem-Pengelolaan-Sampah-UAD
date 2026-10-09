<?php

use App\Models\Campus;
use App\Models\KapSurvey;
use App\Models\Keuangan;
use App\Models\Pickup;
use App\Models\Sale;
use App\Models\WeighingSession;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $activeTab = 'weighing'; // 'weighing', 'sales', 'pickups', 'finance', 'kap'
    public ?string $filterCampusId = '';
    public ?string $filterDateFrom = '';
    public ?string $filterDateTo = '';
    public string $presetPeriod = 'this_month'; // 'this_month', 'last_month', 'this_year', 'all', 'custom'

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

    public function applyPreset(string $preset): void
    {
        $this->presetPeriod = $preset;

        switch ($preset) {
            case 'this_month':
                $this->filterDateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
                $this->filterDateTo = Carbon::now()->format('Y-m-d');
                break;
            case 'last_month':
                $this->filterDateFrom = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->filterDateTo = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_year':
                $this->filterDateFrom = Carbon::now()->startOfYear()->format('Y-m-d');
                $this->filterDateTo = Carbon::now()->format('Y-m-d');
                break;
            case 'all':
                $this->filterDateFrom = '';
                $this->filterDateTo = '';
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

    public function with(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        // Sanitasi aman campus ID untuk mencegah TypeError
        $campusId = !empty($this->filterCampusId) ? (int) $this->filterCampusId : null;

        $campuses = Campus::where('is_active', true)->orderBy('id')->get();

        // 1. DATA SESUAI TAB AKTIF (PAGINATE 8 - INVARIANT 4)
        $data = null;
        $metrics = [];

        switch ($this->activeTab) {
            case 'weighing':
                $query = WeighingSession::with(['campus', 'wasteSource', 'items.wasteType', 'creator'])
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($this->filterDateFrom, fn($q) => $q->whereDate('weigh_date', '>=', $this->filterDateFrom))
                    ->when($this->filterDateTo, fn($q) => $q->whereDate('weigh_date', '<=', $this->filterDateTo))
                    ->orderBy('weigh_date', 'desc')
                    ->orderBy('id', 'desc');

                $data = $query->paginate(8);

                // Agregasi Metrics
                $allSessions = (clone $query)->get();
                $totalKg = $allSessions->sum(fn($s) => $s->items->sum('weight_kg'));
                $totalSessionsCount = $allSessions->count();

                $metrics = [
                    'total_kg' => $totalKg,
                    'total_ton' => $totalKg / 1000,
                    'total_count' => $totalSessionsCount,
                    'avg_kg' => $totalSessionsCount > 0 ? $totalKg / $totalSessionsCount : 0,
                ];
                break;

            case 'sales':
                $query = Sale::with(['campus', 'buyer', 'creator', 'items.wasteType'])
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($this->filterDateFrom, fn($q) => $q->whereDate('sale_date', '>=', $this->filterDateFrom))
                    ->when($this->filterDateTo, fn($q) => $q->whereDate('sale_date', '<=', $this->filterDateTo))
                    ->orderBy('sale_date', 'desc')
                    ->orderBy('id', 'desc');

                $data = $query->paginate(8);

                $allSales = (clone $query)->get();
                $totalRevenue = $allSales->sum('total_amount');
                $totalWeight = $allSales->sum(fn($s) => $s->items->sum('weight_kg'));

                $metrics = [
                    'total_revenue' => $totalRevenue,
                    'total_weight' => $totalWeight,
                    'avg_price' => $totalWeight > 0 ? $totalRevenue / $totalWeight : 0,
                    'total_count' => $allSales->count(),
                ];
                break;

            case 'pickups':
                $query = Pickup::with(['campus', 'vendor', 'creator'])
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($this->filterDateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $this->filterDateFrom))
                    ->when($this->filterDateTo, fn($q) => $q->whereDate('pickup_date', '<=', $this->filterDateTo))
                    ->orderBy('pickup_date', 'desc')
                    ->orderBy('id', 'desc');

                $data = $query->paginate(8);

                $allPickups = (clone $query)->get();
                $totalResiduKg = $allPickups->sum('volume_kg');
                $totalCost = $allPickups->sum('total_cost');

                $metrics = [
                    'total_kg' => $totalResiduKg,
                    'total_ton' => $totalResiduKg / 1000,
                    'total_cost' => $totalCost,
                    'total_trips' => $allPickups->count(),
                ];
                break;

            case 'finance':
                $query = Keuangan::with('campus')
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($this->filterDateFrom, fn($q) => $q->whereDate('tanggal', '>=', $this->filterDateFrom))
                    ->when($this->filterDateTo, fn($q) => $q->whereDate('tanggal', '<=', $this->filterDateTo))
                    ->orderBy('tanggal', 'desc')
                    ->orderBy('id', 'desc');

                $data = $query->paginate(8);

                $allFinance = (clone $query)->get();
                $totalKredit = $allFinance->where('jenis', 'K')->sum('nominal');
                $totalDebet = $allFinance->where('jenis', 'D')->sum('nominal');

                $metrics = [
                    'total_kredit' => $totalKredit,
                    'total_debet' => $totalDebet,
                    'saldo_bersih' => $totalKredit - $totalDebet,
                    'total_count' => $allFinance->count(),
                ];
                break;

            case 'kap':
                $query = KapSurvey::with('campus')
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($this->filterDateFrom, fn($q) => $q->whereDate('survey_date', '>=', $this->filterDateFrom))
                    ->when($this->filterDateTo, fn($q) => $q->whereDate('survey_date', '<=', $this->filterDateTo))
                    ->orderBy('survey_date', 'desc')
                    ->orderBy('id', 'desc');

                $data = $query->paginate(8);

                $allKap = (clone $query)->get();
                $totalResp = $allKap->count();

                $metrics = [
                    'total_resp' => $totalResp,
                    'avg_knowledge' => $totalResp > 0 ? $allKap->avg('knowledge_score') : 0,
                    'avg_attitude' => $totalResp > 0 ? $allKap->avg('attitude_score') : 0,
                    'avg_practice' => $totalResp > 0 ? $allKap->avg('practice_score') : 0,
                    'avg_overall' => $totalResp > 0 ? $allKap->avg('overall_score') : 0,
                ];
                break;
        }

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => $campuses,
            'items' => $data,
            'metrics' => $metrics,
        ];
    }
}; ?>

<div class="py-6">
    <!-- Header Page: Clean, Tanpa Badge Redundan (Invariant 6) -->
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Laporan & Ekspor Data PS2</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Rekapitulasi periodik penimbangan, penjualan sampah, pengangkutan residu, dan arus kas TPS3R UAD
                </p>
            </div>

            <!-- Tombol Aksi Ekspor Header (Cetak PDF & Download CSV) -->
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.export.pdf', [
                        'type' => $activeTab,
                        'campus_id' => $filterCampusId,
                        'date_from' => $filterDateFrom,
                        'date_to' => $filterDateTo,
                    ]) }}" 
                   target="_blank"
                   class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold shadow-2xs transition flex items-center gap-1.5 cursor-pointer active:scale-95"
                   title="Buka / Cetak PDF Resmi">
                    <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span>Cetak PDF</span>
                </a>

                <a href="{{ route('reports.export.excel', [
                        'type' => $activeTab,
                        'campus_id' => $filterCampusId,
                        'date_from' => $filterDateFrom,
                        'date_to' => $filterDateTo,
                    ]) }}" 
                   class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition flex items-center gap-1.5 cursor-pointer active:scale-95"
                   title="Unduh Spreadsheet CSV / Excel">
                    <svg class="w-4 h-4 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Unduh Excel / CSV</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

        <!-- Navigasi Tabs Modul Laporan (Pill Switcher) -->
        <div class="bg-white rounded-2xl p-2 border border-slate-200 shadow-2xs flex flex-wrap gap-1.5">
            <button type="button" 
                    wire:click="setTab('weighing')"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $activeTab === 'weighing' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                </svg>
                <span>Penimbangan Masuk</span>
            </button>

            <button type="button" 
                    wire:click="setTab('sales')"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $activeTab === 'sales' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Penjualan Sampah</span>
            </button>

            <button type="button" 
                    wire:click="setTab('pickups')"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $activeTab === 'pickups' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                </svg>
                <span>Pengangkutan Residu</span>
            </button>

            <button type="button" 
                    wire:click="setTab('finance')"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $activeTab === 'finance' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <span>Buku Kas & Keuangan</span>
            </button>

            <button type="button" 
                    wire:click="setTab('kap')"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer {{ $activeTab === 'kap' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Survei Perilaku (KAP)</span>
            </button>
        </div>

        <!-- Filter Bar Interaktif: Unit Kampus & Rentang Periode -->
        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs space-y-3">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <!-- Dropdown Kampus & Presets -->
                <div class="flex flex-wrap items-center gap-2.5">
                    @if ($isSuperAdmin)
                        <div class="min-w-[180px]">
                            <select wire:model.live="filterCampusId" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-800 bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                <option value="">Semua Kampus (Pusat)</option>
                                @foreach($campuses as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            <span>{{ auth()->user()->campus?->name ?? 'Kampus Saya' }}</span>
                        </div>
                    @endif

                    <!-- Preset Periode Cepat -->
                    <div class="inline-flex rounded-xl bg-slate-100 p-1 text-[11px] font-bold text-slate-600">
                        <button type="button" wire:click="applyPreset('this_month')" class="px-2.5 py-1 rounded-lg transition cursor-pointer {{ $presetPeriod === 'this_month' ? 'bg-white text-emerald-700 shadow-2xs' : 'hover:text-slate-900' }}">Bulan Ini</button>
                        <button type="button" wire:click="applyPreset('last_month')" class="px-2.5 py-1 rounded-lg transition cursor-pointer {{ $presetPeriod === 'last_month' ? 'bg-white text-emerald-700 shadow-2xs' : 'hover:text-slate-900' }}">Bulan Lalu</button>
                        <button type="button" wire:click="applyPreset('this_year')" class="px-2.5 py-1 rounded-lg transition cursor-pointer {{ $presetPeriod === 'this_year' ? 'bg-white text-emerald-700 shadow-2xs' : 'hover:text-slate-900' }}">Tahun Ini</button>
                        <button type="button" wire:click="applyPreset('all')" class="px-2.5 py-1 rounded-lg transition cursor-pointer {{ $presetPeriod === 'all' ? 'bg-white text-emerald-700 shadow-2xs' : 'hover:text-slate-900' }}">Semua</button>
                    </div>
                </div>

                <!-- Date Range Inputs -->
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1.5 text-xs text-slate-500">
                        <span>Periode:</span>
                        <input type="date" wire:model.live="filterDateFrom" class="px-2.5 py-1.5 rounded-xl border border-slate-200 text-xs text-slate-800 bg-white focus:ring-1 focus:ring-emerald-500">
                        <span>s/d</span>
                        <input type="date" wire:model.live="filterDateTo" class="px-2.5 py-1.5 rounded-xl border border-slate-200 text-xs text-slate-800 bg-white focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric Cards Khusus Sesuai Tab Aktif -->
        @if ($activeTab === 'weighing')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Sampah Masuk</span>
                    <div class="mt-2 text-2xl font-black text-slate-900 font-mono">{{ number_format($metrics['total_kg'] ?? 0, 1, ',', '.') }} <span class="text-xs font-semibold text-slate-500 font-sans">kg</span></div>
                    <div class="text-[11px] text-emerald-700 font-bold mt-1">Setara {{ number_format($metrics['total_ton'] ?? 0, 2, ',', '.') }} Ton</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Sesi Timbang</span>
                    <div class="mt-2 text-2xl font-black text-slate-900 font-mono">{{ number_format($metrics['total_count'] ?? 0, 0, ',', '.') }} <span class="text-xs font-semibold text-slate-500 font-sans">kali</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Catatan penimbangan aktif</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Rerata per Sesi</span>
                    <div class="mt-2 text-2xl font-black text-slate-900 font-mono">{{ number_format($metrics['avg_kg'] ?? 0, 1, ',', '.') }} <span class="text-xs font-semibold text-slate-500 font-sans">kg</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Bobot muatan rata-rata</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status Validasi</span>
                    <div class="mt-2 text-2xl font-black text-emerald-700 font-mono">100%</div>
                    <div class="text-[11px] text-slate-400 mt-1">Terdata dalam sistem TPS3R</div>
                </div>
            </div>

        @elseif ($activeTab === 'sales')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Omzet Penjualan</span>
                    <div class="mt-2 text-2xl font-black text-emerald-700 font-mono">Rp {{ number_format($metrics['total_revenue'] ?? 0, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">Penerimaan kas kredit kampus</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Berat Terjual</span>
                    <div class="mt-2 text-2xl font-black text-slate-900 font-mono">{{ number_format($metrics['total_weight'] ?? 0, 1, ',', '.') }} <span class="text-xs font-semibold text-slate-500 font-sans">kg</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Sampah anorganik bernilai</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Rerata Nilai / kg</span>
                    <div class="mt-2 text-2xl font-black text-slate-900 font-mono">Rp {{ number_format($metrics['avg_price'] ?? 0, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">Indeks harga jual material</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Transaksi Penjualan</span>
                    <div class="mt-2 text-2xl font-black text-slate-900 font-mono">{{ number_format($metrics['total_count'] ?? 0, 0, ',', '.') }} <span class="text-xs font-semibold text-slate-500 font-sans">faktur</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Ke pengepul terdaftar</div>
                </div>
            </div>

        @elseif ($activeTab === 'pickups')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Residu ke TPA</span>
                    <div class="mt-2 text-2xl font-black text-rose-600 font-mono">{{ number_format($metrics['total_ton'] ?? 0, 2, ',', '.') }} <span class="text-xs font-semibold text-slate-500 font-sans">Ton</span></div>
                    <div class="text-[11px] text-slate-500 mt-1 font-mono">{{ number_format($metrics['total_kg'] ?? 0, 1, ',', '.') }} kg</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Trip Pengangkutan</span>
                    <div class="mt-2 text-2xl font-black text-slate-900 font-mono">{{ number_format($metrics['total_trips'] ?? 0, 0, ',', '.') }} <span class="text-xs font-semibold text-slate-500 font-sans">rit</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Frekuensi pengangkutan truk</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Biaya Angkut</span>
                    <div class="mt-2 text-2xl font-black text-rose-600 font-mono">Rp {{ number_format($metrics['total_cost'] ?? 0, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">Debet kas pengangkutan</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Tujuan Akhir</span>
                    <div class="mt-2 text-lg font-black text-slate-800">TPA Piyungan</div>
                    <div class="text-[11px] text-slate-400 mt-1">Vendor resmi DLH / Mitra</div>
                </div>
            </div>

        @elseif ($activeTab === 'finance')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Penerimaan (Kredit)</span>
                    <div class="mt-2 text-2xl font-black text-emerald-700 font-mono">Rp {{ number_format($metrics['total_kredit'] ?? 0, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">Hasil penjualan sampah</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pengeluaran (Debet)</span>
                    <div class="mt-2 text-2xl font-black text-rose-600 font-mono">Rp {{ number_format($metrics['total_debet'] ?? 0, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-slate-400 mt-1">Operasional & angkut residu</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Surplus / Saldo Bersih</span>
                    <div class="mt-2 text-2xl font-black font-mono {{ ($metrics['saldo_bersih'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                        Rp {{ number_format($metrics['saldo_bersih'] ?? 0, 0, ',', '.') }}
                    </div>
                    <div class="text-[11px] text-slate-400 mt-1">Kas operasional unit TPS</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Transaksi</span>
                    <div class="mt-2 text-2xl font-black text-slate-900 font-mono">{{ number_format($metrics['total_count'] ?? 0, 0, ',', '.') }} <span class="text-xs font-semibold text-slate-500 font-sans">catatan</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Mutasi kas periode aktif</div>
                </div>
            </div>

        @elseif ($activeTab === 'kap')
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Responden</span>
                    <div class="mt-2 text-2xl font-black text-slate-900 font-mono">{{ number_format($metrics['total_resp'] ?? 0, 0, ',', '.') }} <span class="text-xs font-semibold text-slate-500 font-sans">orang</span></div>
                    <div class="text-[11px] text-slate-400 mt-1">Civitas akademika UAD</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Rerata Pengetahuan</span>
                    <div class="mt-2 text-2xl font-black text-emerald-700 font-mono">{{ number_format($metrics['avg_knowledge'] ?? 0, 1) }}%</div>
                    <div class="text-[11px] text-slate-400 mt-1">Dimensi Knowledge</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Rerata Sikap & Perilaku</span>
                    <div class="mt-2 text-2xl font-black text-teal-600 font-mono">{{ number_format($metrics['avg_practice'] ?? 0, 1) }}%</div>
                    <div class="text-[11px] text-slate-400 mt-1">Penerapan pemilahan aktif</div>
                </div>
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Indeks KAP Kampus</span>
                    <div class="mt-2 text-2xl font-black text-emerald-800 font-mono">{{ number_format($metrics['avg_overall'] ?? 0, 1) }}%</div>
                    <div class="text-[11px] text-slate-400 mt-1">Skor komposit zero waste</div>
                </div>
            </div>
        @endif

        <!-- Data Table Container (8 Baris Per Halaman - Invariant 4 & Independent Pagination Invariant 5) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden relative" wire:loading.class="opacity-60">
            
            <!-- Loading Indicator Overlay -->
            <div wire:loading class="absolute inset-0 z-10 bg-white/40 backdrop-blur-2xs flex items-center justify-center">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-900/80 text-white text-xs font-bold shadow-lg">
                    <svg class="animate-spin w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Memperbarui data laporan...</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    
                    @if ($activeTab === 'weighing')
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Unit Kampus</th>
                                <th class="py-3 px-4">Sumber Sampah</th>
                                <th class="py-3 px-4">Rincian Komposisi Sampah</th>
                                <th class="py-3 px-4 text-right">Total Berat</th>
                                <th class="py-3 px-4">Petugas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($items as $session)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 font-mono font-medium text-slate-700 whitespace-nowrap">
                                        {{ $session->weigh_date ? $session->weigh_date->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        {{ $session->campus?->name ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-medium">
                                            {{ $session->wasteSource?->name ?? 'Kampus' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($session->items as $it)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px]">
                                                    <span class="font-semibold">{{ $it->wasteType?->name ?? 'Sampah' }}:</span>
                                                    <span class="font-mono">{{ number_format($it->weight_kg, 1, ',', '.') }} kg</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900">
                                        {{ number_format($session->items->sum('weight_kg'), 1, ',', '.') }} kg
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                        {{ $session->creator?->name ?? 'Petugas TPS' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-slate-400">
                                        Belum ada data penimbangan untuk filter dan periode yang dipilih.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    @elseif ($activeTab === 'sales')
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4">No Faktur</th>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Unit Kampus</th>
                                <th class="py-3 px-4">Pengepul / Pembeli</th>
                                <th class="py-3 px-4">Rincian Barang Terjual</th>
                                <th class="py-3 px-4 text-right">Total Berat</th>
                                <th class="py-3 px-4 text-right">Nilai Transaksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($items as $sale)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 font-mono font-bold text-slate-800">
                                        SL-{{ str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-slate-600">
                                        {{ $sale->sale_date ? $sale->sale_date->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        {{ $sale->campus?->name ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-700">
                                        {{ $sale->buyer?->name ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($sale->items as $it)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-teal-50 text-teal-800 border border-teal-200 text-[11px]">
                                                    <span>{{ $it->wasteType?->name ?? 'Material' }}:</span>
                                                    <span class="font-mono font-bold">{{ number_format($it->weight_kg, 1, ',', '.') }}kg</span>
                                                    <span class="text-teal-600 font-mono">@Rp{{ number_format($it->price_per_kg, 0, ',', '.') }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900">
                                        {{ number_format($sale->items->sum('weight_kg'), 1, ',', '.') }} kg
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-black text-emerald-700">
                                        Rp {{ number_format($sale->total_amount, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        Belum ada transaksi penjualan sampah terpilah pada periode ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    @elseif ($activeTab === 'pickups')
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Unit Kampus</th>
                                <th class="py-3 px-4">Vendor Pengangkut</th>
                                <th class="py-3 px-4">Driver & Armada</th>
                                <th class="py-3 px-4 text-right">Residu Diangkut</th>
                                <th class="py-3 px-4 text-right">Biaya Angkut</th>
                                <th class="py-3 px-4">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($items as $pickup)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 font-mono font-medium text-slate-700">
                                        {{ $pickup->pickup_date ? $pickup->pickup_date->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        {{ $pickup->campus?->name ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-700 font-medium">
                                        {{ $pickup->vendor?->name ?? 'DLH / Swasta' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600">
                                        <div class="font-medium text-slate-800">{{ $pickup->driver_name ?: '-' }}</div>
                                        <div class="font-mono text-[11px] text-slate-400">{{ $pickup->vehicle_plate ?: '-' }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-rose-600">
                                        {{ number_format($pickup->volume_kg, 1, ',', '.') }} kg
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-black text-rose-700">
                                        Rp {{ number_format($pickup->total_cost, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 text-[11px] max-w-xs truncate">
                                        {{ $pickup->notes ?: '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        Belum ada pengangkutan residu ke TPA pada periode ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    @elseif ($activeTab === 'finance')
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Unit Kampus</th>
                                <th class="py-3 px-4">Jenis Aliran</th>
                                <th class="py-3 px-4">Kategori Sumber</th>
                                <th class="py-3 px-4">Keterangan Transaksi</th>
                                <th class="py-3 px-4 text-right">Debet (Keluar)</th>
                                <th class="py-3 px-4 text-right">Kredit (Masuk)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($items as $trx)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 font-mono font-medium text-slate-700">
                                        {{ $trx->tanggal ? $trx->tanggal->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        {{ $trx->campus?->name ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if($trx->jenis === 'K')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 text-[11px] font-bold border border-emerald-200">
                                                Kredit (Masuk)
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-rose-50 text-rose-800 text-[11px] font-bold border border-rose-200">
                                                Debet (Keluar)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-700 font-medium">
                                        {{ ucfirst(str_replace('_', ' ', $trx->sumber)) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600">
                                        {{ $trx->keterangan ?: '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-rose-600">
                                        {{ $trx->jenis === 'D' ? 'Rp ' . number_format($trx->nominal, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-700">
                                        {{ $trx->jenis === 'K' ? 'Rp ' . number_format($trx->nominal, 0, ',', '.') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        Belum ada catatan mutasi buku kas pada periode ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    @elseif ($activeTab === 'kap')
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Unit Kampus</th>
                                <th class="py-3 px-4">Civitas Responden</th>
                                <th class="py-3 px-4">Fakultas / Unit Kerja</th>
                                <th class="py-3 px-4 text-center">Pengetahuan</th>
                                <th class="py-3 px-4 text-center">Sikap</th>
                                <th class="py-3 px-4 text-center">Perilaku</th>
                                <th class="py-3 px-4 text-center">Indeks KAP</th>
                                <th class="py-3 px-4 text-center">Kategori</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($items as $surv)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4 font-mono font-medium text-slate-700">
                                        {{ $surv->survey_date ? $surv->survey_date->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        {{ $surv->campus?->name ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-700 font-medium">
                                        {{ $surv->respondent_role }}
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 text-[11px]">
                                        {{ $surv->faculty_unit }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-800">
                                        {{ number_format($surv->knowledge_score, 1) }}%
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-800">
                                        {{ number_format($surv->attitude_score, 1) }}%
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-800">
                                        {{ number_format($surv->practice_score, 1) }}%
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-mono font-black text-emerald-700">
                                        {{ number_format($surv->overall_score, 1) }}%
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $surv->category === 'Baik' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($surv->category === 'Cukup' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-rose-50 text-rose-800 border border-rose-200') }}">
                                            {{ $surv->category }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-12 text-center text-slate-400">
                                        Belum ada pengisian kuesioner KAP pada periode ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    @endif

                </table>
            </div>

            <!-- In-place Pagination Bar (Maksimal 8 Baris Per Halaman - Invariant 4 & 5) -->
            <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/50">
                <div class="text-xs text-slate-500">
                    Menampilkan <span class="font-bold text-slate-800">{{ $items->firstItem() ?? 0 }}</span> - <span class="font-bold text-slate-800">{{ $items->lastItem() ?? 0 }}</span> dari total <span class="font-bold text-slate-800">{{ $items->total() }}</span> catatan
                </div>
                <div>
                    {{ $items->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        </div>

    </div>
</div>

