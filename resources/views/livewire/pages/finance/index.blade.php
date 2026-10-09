<?php

use App\Models\BukuBesar;
use App\Models\Campus;
use App\Models\Keuangan;
use App\Services\LedgerService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $activeTab = 'cashbook'; // cashbook (Buku Kas), general_ledger (Buku Besar)

    // Filters
    public $selectedCampusId = null;
    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public string $filterJenis = ''; // '', 'K', 'D'
    public string $filterSumber = ''; // '', 'penjualan', 'pengangkutan', 'operasional', 'saldo_awal'

    // Modal Saldo Awal
    public bool $showBalanceModal = false;
    public $initialCampusId = null;
    public string $initialDate = '';
    public string $initialAmount = '';
    public string $initialNotes = '';

    // Modal Detail Transaksi Jurnal
    public ?int $viewKeuanganId = null;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $activeCampus = session('active_campus_id');
            $this->selectedCampusId = !empty($activeCampus) ? (int) $activeCampus : null;
        } else {
            $this->selectedCampusId = (int) $user->campus_id;
        }

        $this->filterDateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->filterDateTo = Carbon::today()->format('Y-m-d');
        $this->initialDate = Carbon::today()->format('Y-m-d');

        $this->resetBalanceForm();
    }

    public function resetBalanceForm(): void
    {
        $user = auth()->user();
        $defaultCampusId = !empty($this->selectedCampusId)
            ? (int) $this->selectedCampusId
            : ($user->campus_id ? (int) $user->campus_id : Campus::first()?->id);

        $this->initialCampusId = $defaultCampusId;
        $this->initialDate = Carbon::today()->format('Y-m-d');
        $this->initialAmount = '';
        $this->initialNotes = 'Saldo kas awal pembukuan TPS';
        $this->resetValidation();
    }

    public function openBalanceModal(): void
    {
        $this->resetBalanceForm();
        $this->showBalanceModal = true;
    }

    public function closeBalanceModal(): void
    {
        $this->showBalanceModal = false;
        $this->resetBalanceForm();
    }

    public function saveInitialBalance(LedgerService $ledgerService): void
    {
        $this->validate([
            'initialCampusId' => ['required', 'exists:campuses,id'],
            'initialDate' => ['required', 'date'],
            'initialAmount' => ['required', 'numeric', 'min:0'],
            'initialNotes' => ['nullable', 'string', 'max:255'],
        ], [
            'initialCampusId.required' => 'Unit kampus wajib dipilih.',
            'initialDate.required' => 'Tanggal saldo awal wajib diisi.',
            'initialAmount.required' => 'Nominal saldo awal wajib diisi.',
        ]);

        $ledgerService->recordTransaction(
            campusId: (int) $this->initialCampusId,
            date: $this->initialDate,
            jenis: 'K',
            sumber: 'saldo_awal',
            nominal: (float) $this->initialAmount,
            keterangan: strip_tags(trim($this->initialNotes)) ?: 'Saldo kas awal pembukuan TPS',
            refId: null,
            createdBy: auth()->id()
        );

        $this->closeBalanceModal();
        $this->dispatch('close-modal');
        $this->dispatch('toast', message: 'Saldo kas awal berhasil ditetapkan dan disinkronkan ke Buku Besar.', type: 'success');
        session()->flash('message', 'Saldo kas awal berhasil ditetapkan dan disinkronkan ke Buku Besar.');
    }

    public function viewTransaction(int $id): void
    {
        $this->viewKeuanganId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewKeuanganId = null;
    }

    public function with(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        // KPI Ringkasan
        $kpiQuery = Keuangan::query()
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('tanggal', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('tanggal', '<=', $this->filterDateTo));

        $totalKredit = (float) (clone $kpiQuery)->where('jenis', 'K')->sum('nominal');
        $totalDebet = (float) (clone $kpiQuery)->where('jenis', 'D')->sum('nominal');
        $totalPickupCost = (float) (clone $kpiQuery)->where('sumber', 'pengangkutan')->sum('nominal');
        $totalExpenseCost = (float) (clone $kpiQuery)->where('sumber', 'operasional')->sum('nominal');
        $totalSalesIncome = (float) (clone $kpiQuery)->where('sumber', 'penjualan')->sum('nominal');

        // Saldo Kas Berjalan Terakhir (dari Buku Besar terkini)
        $latestLedger = BukuBesar::query()
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->orderBy('tanggal', 'desc')
            ->first();

        $currentSaldoAkhir = $latestLedger ? (float) $latestLedger->saldo_akhir : ($totalKredit - $totalDebet);

        // 1. Data Jurnal Buku Kas (Tabel keuangan)
        $cashbookQuery = Keuangan::with(['campus', 'creator'])
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('tanggal', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('tanggal', '<=', $this->filterDateTo))
            ->when($this->filterJenis, fn($q) => $q->where('jenis', $this->filterJenis))
            ->when($this->filterSumber, fn($q) => $q->where('sumber', $this->filterSumber))
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc');

        $cashbookEntries = $cashbookQuery->paginate(8, ['*'], 'cashbook_page');

        // 2. Data Saldo Harian Buku Besar (Tabel buku_besar)
        $ledgerQuery = BukuBesar::with('campus')
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('tanggal', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('tanggal', '<=', $this->filterDateTo))
            ->orderBy('tanggal', 'desc');

        $ledgerEntries = $ledgerQuery->paginate(8, ['*'], 'ledger_page');

        $detailedTransaction = $this->viewKeuanganId
            ? Keuangan::with(['campus', 'creator'])->find($this->viewKeuanganId)
            : null;

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => Campus::where('is_active', true)->orderBy('id')->get(),
            'totalKredit' => $totalKredit,
            'totalDebet' => $totalDebet,
            'totalSalesIncome' => $totalSalesIncome,
            'totalPickupCost' => $totalPickupCost,
            'totalExpenseCost' => $totalExpenseCost,
            'currentSaldoAkhir' => $currentSaldoAkhir,
            'cashbookEntries' => $cashbookEntries,
            'ledgerEntries' => $ledgerEntries,
            'detailedTransaction' => $detailedTransaction,
        ];
    }
}; ?>

<div x-data="{ 
    balanceModal: @entangle('showBalanceModal'), 
    detailModal: false 
}" 
@close-modal.window="balanceModal = false; detailModal = false"
class="space-y-6">

    <!-- Top Header -->
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Buku Kas & Buku Besar Keuangan</h2>
                <p class="text-xs text-slate-500 mt-0.5">Sistem pembukuan ganda mutasi kas TPS & neraca saldo harian kampus (SRS M6)</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- KPI Metric Cards Keuangan (Dribbble Clean) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Saldo Kas Sirkular Bersih -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Saldo Kas Sirkular</span>
                        <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-xs {{ $currentSaldoAkhir >= 0 ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold' }}">Rp</span>
                        <span class="font-mono text-2xl font-bold {{ $currentSaldoAkhir >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ number_format($currentSaldoAkhir, 0, ',', '.') }}
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Saldo akhir kas tersedia saat ini</p>
                </div>

                <!-- Total Pemasukan (Kredit Kas) -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Kredit (Masuk)</span>
                        <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-xs text-sky-600 font-bold">Rp</span>
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalKredit, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Penjualan anorganik & saldo awal</p>
                </div>

                <!-- Biaya Angkut Residu (Debet) -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Biaya Angkut Residu</span>
                        <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-xs text-slate-500 font-bold">Rp</span>
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalPickupCost, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Pembayaran vendor ritase angkut</p>
                </div>

                <!-- Biaya Operasional TPS (Debet) -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Biaya Operasional TPS</span>
                        <div class="w-7 h-7 rounded-md bg-rose-50 text-rose-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-xs text-slate-500 font-bold">Rp</span>
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalExpenseCost, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Upah, konsumsi, karung, & alat TPS</p>
                </div>
            </div>

            <!-- Tab Navigation & Actions Bar (Dribbble Clean) -->
            <div class="bg-white rounded-xl border border-slate-200 p-2 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-lg">
                    <button wire:click="$set('activeTab', 'cashbook')" 
                            class="px-4 py-1.5 rounded-md text-xs font-bold transition cursor-pointer {{ $activeTab === 'cashbook' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        Buku Kas (Jurnal Mutasi)
                    </button>
                    <button wire:click="$set('activeTab', 'general_ledger')" 
                            class="px-4 py-1.5 rounded-md text-xs font-bold transition cursor-pointer {{ $activeTab === 'general_ledger' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        Buku Besar (Saldo Harian)
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2 px-2">
                    @if ($isSuperAdmin)
                        <div class="flex items-center gap-1.5">
                            <label class="text-xs font-medium text-slate-500">Kampus:</label>
                            <select wire:model.live="selectedCampusId" class="text-xs font-medium border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Semua Kampus</option>
                                @foreach ($campuses as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button @click="balanceModal = true; $wire.openBalanceModal()" 
                                type="button" 
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-xs transition active:scale-95 cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span>Atur Saldo Awal</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- TAB 1: BUKU KAS (JURNAL MUTASI) -->
            @if ($activeTab === 'cashbook')
                <!-- Filter Bar Jurnal -->
                <div class="bg-white rounded-xl border border-slate-200 p-3.5 shadow-sm flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-slate-500">Jenis:</label>
                            <select wire:model.live="filterJenis" class="text-xs font-medium border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Semua (K & D)</option>
                                <option value="K">Kredit (Pemasukan)</option>
                                <option value="D">Debet (Pengeluaran)</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-slate-500">Sumber:</label>
                            <select wire:model.live="filterSumber" class="text-xs font-medium border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Semua Sumber</option>
                                <option value="penjualan">Penjualan Anorganik</option>
                                <option value="pengangkutan">Biaya Angkut Residu</option>
                                <option value="operasional">Operasional TPS</option>
                                <option value="saldo_awal">Saldo Kas Awal</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-slate-500">Rentang:</label>
                            <input type="date" wire:model.live="filterDateFrom" class="text-xs border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                            <span class="text-xs text-slate-400">s/d</span>
                            <input type="date" wire:model.live="filterDateTo" class="text-xs border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                </div>

                <!-- Table Jurnal Buku Kas -->
                <div class="relative bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" id="cashbook-table-container">
                    <!-- Independent Loading Overlay -->
                    <div wire:loading wire:target="previousPage, nextPage, gotoPage, setPage, filterDateFrom, filterDateTo, filterJenis, filterSumber, selectedCampusId" 
                         class="absolute inset-0 bg-white/70 backdrop-blur-[1px] z-20 flex items-center justify-center transition-all duration-150">
                        <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900/90 text-white text-xs font-semibold shadow-lg border border-slate-800">
                            <svg class="animate-spin w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Memperbarui jurnal kas...</span>
                        </div>
                    </div>

                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">Jurnal Transaksi Kas (Buku Kas)</h3>
                            <p class="text-xs text-slate-500 mt-0.5">({{ $cashbookEntries->total() }} Mutasi Kas Terdata)</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 table-fixed">
                            <thead class="bg-slate-50/75 border-b border-slate-100 uppercase text-[10px] font-bold text-slate-500 tracking-wider">
                                <tr>
                                    <th class="py-3 px-3 w-[14%]">Tanggal</th>
                                    <th class="py-3 px-3 w-[18%]">Kampus & Sumber</th>
                                    <th class="py-3 px-3 w-[13%]">Jenis Mutasi</th>
                                    <th class="py-3 px-3 w-[27%]">Uraian / Keterangan</th>
                                    <th class="py-3 px-3 text-right w-[14%]">Nominal (Rp)</th>
                                    <th class="py-3 px-3 w-[10%]">Petugas</th>
                                    <th class="py-3 px-3 text-center w-[4%]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                @forelse ($cashbookEntries as $entry)
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="py-2.5 px-3">
                                            <div class="font-semibold text-slate-800">{{ $entry->tanggal->format('d M Y') }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $entry->created_at->format('H:i') }} WIB</div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 truncate max-w-full">
                                                {{ $entry->campus->name }}
                                            </span>
                                            <div class="text-[10px] text-slate-500 capitalize mt-0.5 font-medium truncate">
                                                {{ str_replace('_', ' ', $entry->sumber) }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            @if ($entry->jenis === 'K')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    <span>Kredit (Masuk)</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                    <span>Debet (Keluar)</span>
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-700 truncate" title="{{ $entry->keterangan }}">
                                            {{ $entry->keterangan ?: '-' }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-sm {{ $entry->jenis === 'K' ? 'text-emerald-700' : 'text-rose-600' }}">
                                            {{ $entry->jenis === 'K' ? '+' : '-' }} Rp {{ number_format($entry->nominal, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-500 text-[11px] truncate" title="{{ $entry->creator?->name ?? 'Sistem' }}">
                                            {{ $entry->creator?->name ?? 'Sistem' }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <button @click="detailModal = true; $wire.viewTransaction({{ $entry->id }})" 
                                                    type="button" 
                                                    title="Lihat Rincian Jurnal"
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
                                        <td colspan="7" class="py-8 text-center text-slate-400">
                                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                            <span>Belum ada transaksi di Buku Kas untuk filter yang dipilih.</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/30">
                        {{ $cashbookEntries->links(data: ['scrollTo' => false]) }}
                    </div>
                </div>
            @endif

            <!-- TAB 2: BUKU BESAR (SALDO HARIAN PER KAMPUS) -->
            @if ($activeTab === 'general_ledger')
                <div class="bg-white rounded-xl border border-slate-200 p-3.5 shadow-sm flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-slate-500">Rentang Saldo:</label>
                        <input type="date" wire:model.live="filterDateFrom" class="text-xs border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        <span class="text-xs text-slate-400">s/d</span>
                        <input type="date" wire:model.live="filterDateTo" class="text-xs border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div class="text-[11px] text-slate-500">
                        * Rumus Akuntansi: <span class="font-mono font-bold text-slate-700">Saldo Akhir = Saldo Awal + Total Kredit - Total Debet</span>
                    </div>
                </div>

                <div class="relative bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" id="ledger-table-container">
                    <!-- Independent Loading Overlay -->
                    <div wire:loading wire:target="previousPage, nextPage, gotoPage, setPage, filterDateFrom, filterDateTo, selectedCampusId" 
                         class="absolute inset-0 bg-white/70 backdrop-blur-[1px] z-20 flex items-center justify-center transition-all duration-150">
                        <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900/90 text-white text-xs font-semibold shadow-lg border border-slate-800">
                            <svg class="animate-spin w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Memperbarui buku besar...</span>
                        </div>
                    </div>

                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">Neraca Saldo Harian (Buku Besar)</h3>
                            <p class="text-xs text-slate-500 mt-0.5">({{ $ledgerEntries->total() }} Catatan Saldo Harian Terdata)</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 table-fixed">
                            <thead class="bg-slate-50/75 border-b border-slate-100 uppercase text-[10px] font-bold text-slate-500 tracking-wider">
                                <tr>
                                    <th class="py-3 px-3 w-[15%]">Tanggal</th>
                                    <th class="py-3 px-3 w-[19%]">Kampus</th>
                                    <th class="py-3 px-3 text-right w-[16%]">Saldo Awal (Rp)</th>
                                    <th class="py-3 px-3 text-right w-[15%]">Total Kredit (+)</th>
                                    <th class="py-3 px-3 text-right w-[15%]">Total Debet (-)</th>
                                    <th class="py-3 px-3 text-right w-[15%]">Saldo Akhir (Rp)</th>
                                    <th class="py-3 px-3 text-center w-[5%]">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                @forelse ($ledgerEntries as $ledger)
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="py-2.5 px-3 whitespace-nowrap font-semibold text-slate-800">
                                            {{ $ledger->tanggal->format('d M Y') }}
                                        </td>
                                        <td class="py-2.5 px-3 truncate">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 truncate max-w-full">
                                                {{ $ledger->campus->name }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-700 whitespace-nowrap">
                                            Rp {{ number_format($ledger->saldo_awal, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700 whitespace-nowrap">
                                            + Rp {{ number_format($ledger->total_kredit, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-rose-600 whitespace-nowrap">
                                            - Rp {{ number_format($ledger->total_debet, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-sm whitespace-nowrap {{ $ledger->saldo_akhir >= 0 ? 'text-slate-900' : 'text-rose-600' }}">
                                            Rp {{ number_format($ledger->saldo_akhir, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-slate-100 text-slate-700">
                                                Valid
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8 text-center text-slate-400">
                                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <span>Belum ada data buku besar harian untuk filter yang dipilih.</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/30">
                        {{ $ledgerEntries->links(data: ['scrollTo' => false]) }}
                    </div>
                </div>
            @endif

            <!-- Modal Input Saldo Awal Kas (Teleported to Body for 100% Full Viewport Backdrop) -->
            <template x-teleport="body">
                <div x-show="balanceModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Atur Saldo Kas Awal</h3>
                                <p class="text-xs text-slate-500">Inisialisasi kas awal periode pembukuan kampus</p>
                            </div>
                            <button @click="balanceModal = false; $wire.closeBalanceModal()" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form wire:submit="saveInitialBalance" class="p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Unit Kampus <span class="text-rose-500">*</span></label>
                                <select wire:model="initialCampusId" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                    @foreach ($campuses as $campus)
                                        <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                    @endforeach
                                </select>
                                @error('initialCampusId') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Saldo Awal <span class="text-rose-500">*</span></label>
                                <input type="date" wire:model="initialDate" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                @error('initialDate') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Nominal Kas Awal (Rp) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-slate-400">Rp</span>
                                    <input type="number" step="100" min="0" wire:model="initialAmount" placeholder="Contoh: 500000" class="w-full text-xs font-mono font-bold border-slate-200 rounded-lg pl-9 pr-3 py-2 bg-slate-50 text-slate-900 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                </div>
                                @error('initialAmount') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Catatan / Keterangan</label>
                                <input type="text" wire:model="initialNotes" placeholder="Misal: Saldo sisa kas semester lalu" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                @error('initialNotes') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
                                <button @click="balanceModal = false; $wire.closeBalanceModal()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                    Batal
                                </button>
                                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="saveInitialBalance">Simpan Saldo Awal</span>
                                    <span wire:loading wire:target="saveInitialBalance">Menyimpan...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <!-- Modal Detail Jurnal Kas (Teleported to Body for 100% Full Viewport Backdrop) -->
            <template x-teleport="body">
                <div x-show="detailModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                <h3 class="text-sm font-bold text-slate-900">Rincian Transaksi Jurnal Buku Kas</h3>
                            </div>
                            <button @click="detailModal = false; $wire.closeViewModal()" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-4">
                            @if ($detailedTransaction)
                                <div class="grid grid-cols-2 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                                    <div>
                                        <span class="text-[11px] text-slate-400 block">Unit Kampus</span>
                                        <span class="font-bold text-slate-900">{{ $detailedTransaction->campus->name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[11px] text-slate-400 block">Tanggal Jurnal</span>
                                        <span class="font-bold text-slate-900">{{ $detailedTransaction->tanggal->format('d F Y') }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[11px] text-slate-400 block">Jenis Mutasi</span>
                                        <span class="font-bold {{ $detailedTransaction->jenis === 'K' ? 'text-emerald-700' : 'text-rose-600' }}">
                                            {{ $detailedTransaction->jenis === 'K' ? 'Kredit (Pemasukan)' : 'Debet (Pengeluaran)' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-[11px] text-slate-400 block">Sumber Transaksi</span>
                                        <span class="font-bold text-slate-800 capitalize">{{ str_replace('_', ' ', $detailedTransaction->sumber) }}</span>
                                    </div>
                                </div>

                                <div class="p-3.5 rounded-xl border {{ $detailedTransaction->jenis === 'K' ? 'border-emerald-200 bg-emerald-50/40' : 'border-rose-200 bg-rose-50/40' }}">
                                    <span class="text-[11px] uppercase font-semibold tracking-wider block mb-1 {{ $detailedTransaction->jenis === 'K' ? 'text-emerald-700' : 'text-rose-600' }}">
                                        Nominal Mutasi
                                    </span>
                                    <div class="text-2xl font-mono font-bold {{ $detailedTransaction->jenis === 'K' ? 'text-emerald-700' : 'text-rose-600' }}">
                                        {{ $detailedTransaction->jenis === 'K' ? '+' : '-' }} Rp {{ number_format($detailedTransaction->nominal, 0, ',', '.') }}
                                    </div>
                                    @if ($detailedTransaction->ref_id)
                                        <span class="text-[10px] text-slate-400 mt-1 block">ID Referensi: #{{ $detailedTransaction->ref_id }} (Terkait modul {{ $detailedTransaction->sumber }})</span>
                                    @endif
                                </div>

                                <div>
                                    <span class="text-xs font-bold text-slate-700 block mb-1">Keterangan Transaksi</span>
                                    <p class="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-200/80 leading-relaxed">
                                        {{ $detailedTransaction->keterangan ?: 'Tidak ada keterangan tambahan.' }}
                                    </p>
                                </div>

                                <div class="flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-100">
                                    <span>Petugas: <strong class="text-slate-600">{{ $detailedTransaction->creator?->name ?? 'Sistem' }}</strong></span>
                                    <span>Tercatat: {{ $detailedTransaction->created_at->format('d/m/Y H:i') }} WIB</span>
                                </div>
                            @else
                                <div class="py-8 text-center text-slate-400 text-xs">
                                    Memuat detail jurnal kas...
                                </div>
                            @endif

                            <div class="pt-3 border-t border-slate-100 flex justify-end">
                                <button @click="detailModal = false; $wire.closeViewModal()" type="button" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition cursor-pointer">
                                    Tutup Rincian
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

