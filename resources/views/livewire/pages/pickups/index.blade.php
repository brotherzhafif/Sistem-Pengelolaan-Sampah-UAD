<?php

use App\Models\Campus;
use App\Models\Pickup;
use App\Models\Vendor;
use App\Services\StockService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Filter & Scope (loose typing to prevent PHP 8 string/null TypeError with select elements)
    public $selectedCampusId = null;
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    // Modal Create state
    public bool $showCreateModal = false;

    // Form Transaksi Pengangkutan
    public $formCampusId = null;
    public $formVendorId = null;
    public string $formDate = '';
    public string $formVolumeKg = '';
    public string $formCostPerKg = '';
    public string $formTotalCost = '0';
    public string $formDriverName = '';
    public string $formVehiclePlate = '';
    public string $formNotes = '';

    // Modal Confirmation Deletion & View Detail state
    public ?int $confirmDeletePickupId = null;
    public ?int $viewPickupId = null;

    public function viewPickup(int $id): void
    {
        $this->viewPickupId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewPickupId = null;
    }

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

        $this->resetForm();
    }

    public function resetForm(): void
    {
        $user = auth()->user();
        $defaultCampusId = !empty($this->selectedCampusId)
            ? (int) $this->selectedCampusId
            : ($user->campus_id ? (int) $user->campus_id : Campus::first()?->id);
        
        $this->formCampusId = $defaultCampusId;
        
        $firstVendor = Vendor::where('is_active', true)->first();
        $this->formVendorId = $firstVendor?->id;
        $this->formCostPerKg = $firstVendor ? (string) (float) $firstVendor->cost_per_kg : '0';
        
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->formVolumeKg = '';
        $this->formTotalCost = '0';
        $this->formDriverName = '';
        $this->formVehiclePlate = '';
        $this->formNotes = '';
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->dispatch('close-modal');
    }

    public function updatedFormVendorId($value = null): void
    {
        $vendorId = !empty($value) ? (int) $value : (!empty($this->formVendorId) ? (int) $this->formVendorId : null);
        if ($vendorId) {
            $vendor = Vendor::find($vendorId);
            if ($vendor) {
                $this->formCostPerKg = (string) (float) $vendor->cost_per_kg;
                $this->calculateTotalCost();
            }
        }
    }

    public function updatedFormVolumeKg(): void
    {
        $this->calculateTotalCost();
    }

    public function updatedFormCostPerKg(): void
    {
        $this->calculateTotalCost();
    }

    public function calculateTotalCost(): void
    {
        $volume = floatval($this->formVolumeKg ?: 0);
        $cost = floatval($this->formCostPerKg ?: 0);
        $this->formTotalCost = (string) round($volume * $cost, 2);
    }

    public function savePickup(StockService $stockService): void
    {
        $this->validate([
            'formCampusId' => ['required', 'exists:campuses,id'],
            'formVendorId' => ['required', 'exists:vendors,id'],
            'formDate' => ['required', 'date'],
            'formVolumeKg' => ['required', 'numeric', 'min:0.1', 'max:50000'],
            'formCostPerKg' => ['required', 'numeric', 'min:0'],
            'formDriverName' => ['nullable', 'string', 'max:100'],
            'formVehiclePlate' => ['nullable', 'string', 'max:30'],
            'formNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'formVolumeKg.required' => 'Bobot muatan residu wajib diisi.',
            'formVolumeKg.min' => 'Bobot muatan minimal 0.1 kg.',
            'formCostPerKg.required' => 'Biaya per kg wajib diisi.',
        ]);

        $volume = floatval($this->formVolumeKg);
        $costPerKg = floatval($this->formCostPerKg);
        $totalCost = round($volume * $costPerKg, 2);
        $campusId = (int) $this->formCampusId;
        $vendorId = (int) $this->formVendorId;

        // Validasi ketersediaan stok residu di kampus bersangkutan
        $stockSummary = $stockService->getStockSummary($campusId);
        $availableResidual = $stockSummary['residual_stock_kg'];

        if ($volume > $availableResidual) {
            $this->addError(
                'formVolumeKg',
                "Muatan melebihi stok residu tersedia di kampus ini (Tersedia: " . number_format($availableResidual, 1, ',', '.') . " kg, Diminta: " . number_format($volume, 1, ',', '.') . " kg)."
            );
            return;
        }

        $pickup = Pickup::create([
            'campus_id' => $campusId,
            'vendor_id' => $vendorId,
            'pickup_date' => $this->formDate,
            'volume_kg' => $volume,
            'cost_per_kg' => $costPerKg,
            'total_cost' => $totalCost,
            'driver_name' => $this->formDriverName ? strip_tags(trim($this->formDriverName)) : null,
            'vehicle_plate' => $this->formVehiclePlate ? strip_tags(trim(strtoupper($this->formVehiclePlate))) : null,
            'created_by' => auth()->id(),
            'notes' => $this->formNotes ? strip_tags(trim($this->formNotes)) : null,
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('close-modal');
        session()->flash('status', 'Pencatatan pengangkutan residu berhasil disimpan & jurnal Debet kas otomatis dicatat.');
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeletePickupId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeletePickupId = null;
        $this->dispatch('close-modal');
    }

    public function deletePickup(): void
    {
        if (!$this->confirmDeletePickupId) {
            return;
        }

        $pickup = Pickup::findOrFail($this->confirmDeletePickupId);

        // Otorisasi kampus
        if (auth()->user()->campus_id && auth()->user()->campus_id !== $pickup->campus_id && !auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            session()->flash('error', 'Anda tidak memiliki otoritas untuk menghapus data pengangkutan kampus ini.');
            $this->confirmDeletePickupId = null;
            $this->dispatch('close-modal');
            return;
        }

        $pickup->delete(); // Observer otomatis hapus jurnal debet keuangan & re-sync buku besar
        $this->confirmDeletePickupId = null;
        $this->dispatch('close-modal');
        session()->flash('status', 'Data pengangkutan residu berhasil dihapus dan jurnal keuangan disesuaikan.');
    }

    public function with(StockService $stockService): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (int) $user->campus_id;

        $pickupsQuery = Pickup::with(['campus', 'vendor', 'creator'])
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('pickup_date', '<=', $this->filterDateTo))
            ->orderBy('pickup_date', 'desc')
            ->orderBy('id', 'desc');

        $stockSummary = $stockService->getStockSummary($campusQueryId);

        $totalCostFiltered = Pickup::when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('pickup_date', '<=', $this->filterDateTo))
            ->sum('total_cost');

        $totalVolumeFiltered = Pickup::when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('pickup_date', '<=', $this->filterDateTo))
            ->sum('volume_kg');

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => Campus::orderBy('id')->get(),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(),
            'pickups' => $pickupsQuery->paginate(8),
            'selectedPickup' => $this->viewPickupId ? Pickup::with(['campus', 'vendor', 'creator'])->find($this->viewPickupId) : null,
            'stockSummary' => $stockSummary,
            'totalCostFiltered' => (float) $totalCostFiltered,
            'totalVolumeFiltered' => (float) $totalVolumeFiltered,
        ];
    }
}; ?>

<div x-data="{ 
    showModal: false, 
    deleteModal: false 
}"
@close-modal.window="showModal = false; deleteModal = false"
@open-pickup-modal.window="showModal = true">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Pengangkutan Residu Sampah</h2>
                <p class="text-xs text-slate-500 mt-0.5">Pencatatan pengangkutan sisa residu ke TPA oleh vendor & debet biaya kas operasional</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-xs font-semibold text-amber-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    Debet Kas Otomatis
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- Alert Status / Feedback Banner -->
            @if (session('status'))
                <div class="flex items-center justify-between p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium animate-fadeIn">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                    <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif

            @if (session('error'))
                <div class="flex items-center justify-between p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium animate-fadeIn">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" @click="$el.parentElement.remove()" class="text-rose-500 hover:text-rose-800">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif

            <!-- Metric Cards (Clean Dribbble style matching design tokens) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Total Biaya Pengangkutan -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-rose-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Biaya Angkut</span>
                        <div class="w-7 h-7 rounded-md bg-rose-50 text-rose-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-xs text-slate-400 font-medium">Rp</span>
                        <span class="font-mono text-2xl font-bold text-rose-600">{{ number_format($totalCostFiltered, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Debet kas pengeluaran operasional</p>
                </div>

                <!-- Total Berat Residu Diangkut -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Volume Terangkut</span>
                        <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalVolumeFiltered, 1, ',', '.') }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Residu disalurkan ke TPA/Vendor</p>
                </div>

                <!-- Sisa Stok Residu di TPS -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-slate-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Sisa Residu di TPS</span>
                        <div class="w-7 h-7 rounded-md bg-slate-50 text-slate-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($stockSummary['residual_stock_kg'], 1, ',', '.') }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Antrean penjemputan pengangkutan</p>
                </div>
            </div>

            <!-- Filter & Action Toolbar -->
            <div class="bg-white rounded-xl border border-slate-200 p-3.5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    @if ($isSuperAdmin)
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-slate-500">Kampus:</label>
                            <select wire:model.live="selectedCampusId" class="text-xs font-medium border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Semua Kampus</option>
                                @foreach ($campuses as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-slate-500">Rentang:</label>
                        <input type="date" wire:model.live="filterDateFrom" class="text-xs border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        <span class="text-xs text-slate-400">s/d</span>
                        <input type="date" wire:model.live="filterDateTo" class="text-xs border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="showModal = true; $wire.openCreateModal()"
                            type="button"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-sm shadow-amber-600/20 transition active:scale-95 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Catat Pengangkutan</span>
                    </button>
                </div>
            </div>

            <!-- Table Riwayat Pengangkutan Residu -->
            <div class="relative bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" id="pickups-table-container">
                <!-- Independent Table Loading State Indicator -->
                <div wire:loading wire:target="previousPage, nextPage, gotoPage, setPage, filterDateFrom, filterDateTo, selectedCampusId" 
                     class="absolute inset-0 bg-white/70 backdrop-blur-[1px] z-20 flex items-center justify-center transition-all duration-150">
                    <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900/90 text-white text-xs font-semibold shadow-lg border border-slate-800">
                        <svg class="animate-spin w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Memperbarui log pengangkutan...</span>
                    </div>
                </div>

                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Riwayat Pengangkutan Residu</h3>
                        <p class="text-xs text-slate-500 mt-0.5">({{ $pickups->total() }} Log Pengangkutan Terdata)</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50/75 border-b border-slate-100 uppercase text-[10px] font-bold text-slate-500 tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Kampus & Vendor</th>
                                <th class="py-3 px-4">Armada / Driver</th>
                                <th class="py-3 px-4 text-right">Volume (kg)</th>
                                <th class="py-3 px-4 text-right">Tarif / kg</th>
                                <th class="py-3 px-4 text-right">Total Biaya (Rp)</th>
                                <th class="py-3 px-4">Petugas</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @forelse ($pickups as $p)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="py-2.5 px-4 whitespace-nowrap">
                                        <div class="font-semibold text-slate-800">{{ $p->pickup_date->format('d M Y') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $p->created_at->format('H:i') }} WIB</div>
                                    </td>
                                    <td class="py-2.5 px-4 whitespace-nowrap">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 mb-0.5">
                                            {{ $p->campus->name }}
                                        </span>
                                        <div class="font-semibold text-slate-800">{{ $p->vendor->name }}</div>
                                    </td>
                                    <td class="py-2.5 px-4 whitespace-nowrap">
                                        <div class="text-slate-800">{{ $p->driver_name ?: '-' }}</div>
                                        @if ($p->vehicle_plate)
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-mono bg-slate-100 text-slate-600">
                                                {{ $p->vehicle_plate }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                        {{ number_format($p->volume_kg, 1, ',', '.') }} kg
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono text-slate-600 whitespace-nowrap">
                                        Rp {{ number_format($p->cost_per_kg, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold text-rose-600 whitespace-nowrap">
                                        Rp {{ number_format($p->total_cost, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-4 whitespace-nowrap text-slate-700">
                                        {{ $p->creator?->name ?? 'Sistem' }}
                                    </td>
                                    <td class="py-2.5 px-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1">
                                            <button wire:click="viewPickup({{ $p->id }})" 
                                                    type="button" 
                                                    title="Lihat Rincian Pengangkutan"
                                                    class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <button @click="deleteModal = true; $wire.confirmDelete({{ $p->id }})" 
                                                    type="button" 
                                                    title="Hapus Catatan Pengangkutan"
                                                    class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-slate-400">
                                        <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                                        </svg>
                                        <span>Belum ada log pengangkutan residu untuk filter yang dipilih.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/30">
                    {{ $pickups->links(data: ['scrollTo' => false]) }}
                </div>
            </div>

            <!-- Modal Form Catat Pengangkutan (Teleported to Body for 100% Full Viewport Backdrop) -->
            <template x-teleport="body">
                <div x-show="showModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Catat Pengangkutan Residu Sampah</h3>
                                <p class="text-xs text-slate-500">Pencatatan volume residu yang diangkut keluar oleh vendor mitra</p>
                            </div>
                            <button @click="showModal = false; $wire.closeCreateModal()" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form wire:submit="savePickup" class="p-6 space-y-5">
                            <!-- Info Lokasi & Vendor -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50/75 p-4 rounded-xl border border-slate-200/60">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kampus <span class="text-rose-500">*</span></label>
                                    @if ($isSuperAdmin)
                                        <select wire:model.live="formCampusId" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                            @foreach ($campuses as $c)
                                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="text-xs font-semibold text-slate-800 bg-white border border-slate-200 px-3 py-2 rounded-lg">
                                            {{ auth()->user()->campus?->name }}
                                        </div>
                                    @endif
                                    @error('formCampusId') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Vendor Pengangkut <span class="text-rose-500">*</span></label>
                                    <select wire:model.live="formVendorId" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                        @foreach ($vendors as $v)
                                            <option value="{{ $v->id }}">{{ $v->name }} (Rp {{ number_format($v->cost_per_kg, 0, ',', '.') }}/kg)</option>
                                        @endforeach
                                    </select>
                                    @error('formVendorId') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Tanggal & Volume Angkut -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Angkut <span class="text-rose-500">*</span></label>
                                    <input type="date" wire:model="formDate" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                    @error('formDate') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Volume Muatan (kg) <span class="text-rose-500">*</span></label>
                                    <input type="number" step="0.1" min="0" wire:model.live.debounce.300ms="formVolumeKg" placeholder="0.0" class="w-full font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                    @error('formVolumeKg') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tarif Vendor / kg (Rp)</label>
                                    <input type="number" step="50" min="0" wire:model.live.debounce.300ms="formCostPerKg" class="w-full font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                    @error('formCostPerKg') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Total Biaya Debet Preview Card -->
                            <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200/60 flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-bold text-amber-900">Total Biaya Operasional Pengangkutan:</span>
                                    <p class="text-[11px] text-amber-700 mt-0.5">Otomatis didebetkan ke Buku Kas saat disimpan</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono text-lg font-bold text-rose-600">Rp {{ number_format(floatval($formTotalCost), 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <!-- Armada & Driver Info -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Supir / Driver</label>
                                    <input type="text" wire:model="formDriverName" placeholder="Contoh: Pak Joko" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    @error('formDriverName') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Plat Kendaraan</label>
                                    <input type="text" wire:model="formVehiclePlate" placeholder="Contoh: AB 1234 CD" class="w-full uppercase font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    @error('formVehiclePlate') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Catatan -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan</label>
                                <textarea wire:model="formNotes" rows="2" placeholder="Keterangan kondisi residu atau ritase truk..." class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500"></textarea>
                                @error('formNotes') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                            </div>

                            <!-- Footer Form Modal -->
                            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                                <button @click="showModal = false; $wire.closeCreateModal()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                    Batal
                                </button>
                                <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 cursor-pointer">
                                    Simpan & Catat Debet Kas
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <!-- Modal Konfirmasi Hapus Kustom (Teleported to Body) -->
            <template x-teleport="body">
                <div x-show="deleteModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md p-6 space-y-4">
                        <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="text-center">
                            <h3 class="text-sm font-bold text-slate-900">Konfirmasi Penghapusan Log Pengangkutan</h3>
                            <p class="text-xs text-slate-500 mt-1">Apakah Anda yakin ingin menghapus catatan pengangkutan ini? Entri debet di buku kas akan otomatis disesuaikan.</p>
                        </div>
                        <div class="flex items-center justify-center gap-3 pt-2">
                            <button @click="deleteModal = false; $wire.cancelDelete()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                Batal
                            </button>
                            <button @click="deleteModal = false" wire:click="deletePickup" type="button" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold transition active:scale-95 cursor-pointer">
                                Ya, Hapus Data
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Modal Detail Pengangkutan Residu (Teleported to Body) -->
            @if ($viewPickupId && $selectedPickup)
                <template x-teleport="body">
                    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn"
                         x-data
                         @keydown.escape.window="$wire.closeViewModal()">
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
                            <!-- Header Modal Detail -->
                            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                            LOG #{{ $selectedPickup->id }}
                                        </span>
                                        <span class="text-xs text-slate-400 font-medium">
                                            {{ $selectedPickup->pickup_date->translatedFormat('d F Y') }}
                                        </span>
                                    </div>
                                    <h3 class="text-base font-bold text-slate-900 mt-1">Rincian Pengangkutan Residu</h3>
                                </div>
                                <button wire:click="closeViewModal" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <!-- Body Modal Detail -->
                            <div class="p-6 space-y-5">
                                <!-- Kartu Highlight -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/60">
                                        <span class="text-[11px] font-medium text-slate-500">Volume Muatan Terangkut</span>
                                        <div class="font-mono text-xl font-bold text-slate-900 mt-0.5">
                                            {{ number_format($selectedPickup->volume_kg, 1, ',', '.') }} <span class="text-xs font-sans font-normal text-slate-500">kg</span>
                                        </div>
                                    </div>
                                    <div class="p-3.5 rounded-xl bg-rose-50/60 border border-rose-200/60">
                                        <span class="text-[11px] font-medium text-rose-600">Total Biaya Operasional</span>
                                        <div class="font-mono text-xl font-bold text-rose-700 mt-0.5">
                                            Rp {{ number_format($selectedPickup->total_cost, 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Rincian Granular -->
                                <div class="rounded-xl border border-slate-200 divide-y divide-slate-100 overflow-hidden text-xs">
                                    <div class="px-4 py-3 flex justify-between bg-slate-50/50">
                                        <span class="text-slate-500 font-medium">Kampus Asal</span>
                                        <span class="font-semibold text-slate-800">{{ $selectedPickup->campus->name }}</span>
                                    </div>
                                    <div class="px-4 py-3 flex justify-between">
                                        <span class="text-slate-500 font-medium">Vendor Mitra</span>
                                        <span class="font-semibold text-slate-800">{{ $selectedPickup->vendor->name }}</span>
                                    </div>
                                    <div class="px-4 py-3 flex justify-between bg-slate-50/50">
                                        <span class="text-slate-500 font-medium">Tarif Satuan Vendor</span>
                                        <span class="font-mono text-slate-700">Rp {{ number_format($selectedPickup->cost_per_kg, 0, ',', '.') }} / kg</span>
                                    </div>
                                    <div class="px-4 py-3 flex justify-between">
                                        <span class="text-slate-500 font-medium">Supir / Driver</span>
                                        <span class="font-semibold text-slate-800">{{ $selectedPickup->driver_name ?: '-' }}</span>
                                    </div>
                                    <div class="px-4 py-3 flex justify-between bg-slate-50/50">
                                        <span class="text-slate-500 font-medium">Nomor Plat Armada</span>
                                        <span class="font-mono text-slate-700 font-semibold">{{ $selectedPickup->vehicle_plate ?: '-' }}</span>
                                    </div>
                                    <div class="px-4 py-3 flex justify-between">
                                        <span class="text-slate-500 font-medium">Petugas Pencatat</span>
                                        <span class="text-slate-700">{{ $selectedPickup->creator?->name ?? 'Sistem' }} ({{ $selectedPickup->created_at->format('d/m/Y H:i') }} WIB)</span>
                                    </div>
                                    <div class="px-4 py-3 flex justify-between bg-slate-50/50">
                                        <span class="text-slate-500 font-medium">Status Buku Kas</span>
                                        <span class="inline-flex items-center gap-1 font-semibold text-rose-700 bg-rose-100 px-2 py-0.5 rounded text-[10px]">
                                            Terdebet Otomatis (Pengeluaran Operasional)
                                        </span>
                                    </div>
                                </div>

                                <!-- Catatan -->
                                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/60">
                                    <span class="text-[11px] font-semibold text-slate-600 block mb-1">Catatan Pengangkutan:</span>
                                    <p class="text-xs text-slate-700 italic">
                                        {{ $selectedPickup->notes ?: 'Tidak ada catatan tambahan.' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Footer Modal Detail -->
                            <div class="px-6 py-3 border-t border-slate-100 flex items-center justify-end bg-slate-50/50">
                                <button wire:click="closeViewModal" type="button" class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                                    Tutup
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            @endif

        </div>
    </div>
</div>
