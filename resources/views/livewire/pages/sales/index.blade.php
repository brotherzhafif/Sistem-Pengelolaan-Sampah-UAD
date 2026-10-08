<?php

use App\Models\Buyer;
use App\Models\Campus;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\WasteType;
use App\Services\StockService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    // Filter & Scope
    public ?int $selectedCampusId = null;
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    // Modal Create state
    public bool $showCreateModal = false;

    // Form Transaksi Penjualan
    public ?int $formCampusId = null;
    public ?int $formBuyerId = null;
    public string $formDate = '';
    public string $formNotes = '';

    // Multi-row items: [ ['waste_type_id' => x, 'weight_kg' => y, 'price_per_kg' => z, 'subtotal' => w] ]
    public array $saleItems = [];

    // Modal Confirmation Deletion state
    public ?int $confirmDeleteSaleId = null;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $this->selectedCampusId = session('active_campus_id') ?? $user->campus_id ?? null;
        } else {
            $this->selectedCampusId = $user->campus_id;
        }
        $this->filterDateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->filterDateTo = Carbon::today()->format('Y-m-d');

        $this->resetForm();
    }

    public function resetForm(): void
    {
        $user = auth()->user();
        $this->formCampusId = $this->selectedCampusId ?? $user->campus_id ?? Campus::first()?->id;
        $this->formBuyerId = Buyer::first()?->id;
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->formNotes = '';

        // Ambil jenis sampah terpilah (is_sellable = true)
        $sellableTypes = WasteType::where('is_sellable', true)->where('is_active', true)->orderBy('name')->get();
        $this->saleItems = [];
        foreach ($sellableTypes as $type) {
            $this->saleItems[] = [
                'waste_type_id' => $type->id,
                'waste_name' => $type->name,
                'weight_kg' => '',
                'price_per_kg' => (float) $type->default_price_per_kg,
                'subtotal' => 0.0,
            ];
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function updateSubtotal(int $index): void
    {
        if (isset($this->saleItems[$index])) {
            $weight = floatval($this->saleItems[$index]['weight_kg'] ?? 0);
            $price = floatval($this->saleItems[$index]['price_per_kg'] ?? 0);
            $this->saleItems[$index]['subtotal'] = round($weight * $price, 2);
        }
    }

    public function getTotalSaleAmountProperty(): float
    {
        $total = 0.0;
        foreach ($this->saleItems as $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            $price = floatval($item['price_per_kg'] ?? 0);
            $total += ($weight * $price);
        }
        return round($total, 2);
    }

    public function saveSale(StockService $stockService): void
    {
        $this->validate([
            'formCampusId' => ['required', 'exists:campuses,id'],
            'formBuyerId' => ['required', 'exists:buyers,id'],
            'formDate' => ['required', 'date'],
            'formNotes' => ['nullable', 'string', 'max:500'],
            'saleItems' => ['required', 'array', 'min:1'],
            'saleItems.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'saleItems.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'saleItems.*.price_per_kg' => ['required', 'numeric', 'min:0'],
        ]);

        // Cek minimal ada 1 jenis sampah yang dijual dengan bobot > 0
        $itemsToSell = [];
        foreach ($this->saleItems as $index => $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            $price = floatval($item['price_per_kg'] ?? 0);

            if ($weight > 0) {
                // Validasi ketersediaan stok aktual di kampus bersangkutan
                $availableStock = $stockService->getAvailableStock($item['waste_type_id'], $this->formCampusId);

                if ($weight > $availableStock) {
                    $this->addError(
                        "saleItems.{$index}.weight_kg",
                        "Stok '{$item['waste_name']}' tidak mencukupi (Tersedia: " . number_format($availableStock, 1) . " kg, Diminta: " . number_format($weight, 1) . " kg)."
                    );
                    return;
                }

                $itemsToSell[] = [
                    'waste_type_id' => $item['waste_type_id'],
                    'weight_kg' => $weight,
                    'price_per_kg' => $price,
                    'subtotal' => round($weight * $price, 2),
                ];
            }
        }

        if (empty($itemsToSell)) {
            $this->addError('saleItems', 'Harap isi bobot penjualan minimal untuk salah satu jenis sampah.');
            return;
        }

        $totalAmount = array_sum(array_column($itemsToSell, 'subtotal'));

        $sale = Sale::create([
            'campus_id' => $this->formCampusId,
            'buyer_id' => $this->formBuyerId,
            'sale_date' => $this->formDate,
            'total_amount' => $totalAmount,
            'created_by' => auth()->id(),
            'notes' => $this->formNotes ? strip_tags(trim($this->formNotes)) : null,
        ]);

        foreach ($itemsToSell as $item) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'waste_type_id' => $item['waste_type_id'],
                'weight_kg' => $item['weight_kg'],
                'price_per_kg' => $item['price_per_kg'],
                'subtotal' => $item['subtotal'],
            ]);
        }

        $this->showCreateModal = false;
        $this->resetForm();
        session()->flash('status', 'Transaksi penjualan sampah berhasil dicatat & jurnal Kredit masuk ke buku kas.');
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteSaleId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteSaleId = null;
    }

    public function deleteSale(): void
    {
        if (!$this->confirmDeleteSaleId) {
            return;
        }

        $sale = Sale::findOrFail($this->confirmDeleteSaleId);

        // Otorisasi kampus
        if (auth()->user()->campus_id && auth()->user()->campus_id !== $sale->campus_id && !auth()->user()->hasRole('Super Admin')) {
            session()->flash('error', 'Anda tidak memiliki otoritas untuk menghapus data kampus ini.');
            $this->confirmDeleteSaleId = null;
            return;
        }

        $sale->delete(); // Observer otomatis hapus jurnal keuangan & sinkronkan buku besar
        $this->confirmDeleteSaleId = null;
        session()->flash('status', 'Transaksi penjualan berhasil dihapus dan jurnal keuangan disesuaikan.');
    }

    public function with(StockService $stockService): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('Super Admin') || $user->hasRole('Auditor / Pimpinan') || !$user->campus_id;

        $campusQueryId = $isSuperAdmin ? $this->selectedCampusId : $user->campus_id;

        $salesQuery = Sale::with(['campus', 'buyer', 'creator', 'items.wasteType'])
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('sale_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('sale_date', '<=', $this->filterDateTo))
            ->orderBy('sale_date', 'desc')
            ->orderBy('id', 'desc');

        $stockSummary = $stockService->getStockSummary($campusQueryId);

        $totalRevenue = Sale::when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('sale_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('sale_date', '<=', $this->filterDateTo))
            ->sum('total_amount');

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => Campus::orderBy('id')->get(),
            'buyers' => Buyer::orderBy('name')->get(),
            'sales' => $salesQuery->paginate(15),
            'stockSummary' => $stockSummary,
            'totalRevenue' => (float) $totalRevenue,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Penjualan Sampah Terpilah</h2>
                <p class="text-xs text-slate-500 mt-0.5">Pencatatan transaksi penjualan ke pengepul dan penerimaan kredit kas kampus</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-50 border border-sky-200 text-xs font-semibold text-sky-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                    Kredit Kas Otomatis
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- Alert Status -->
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

            <!-- Metric Cards (Clean Dribbble style matching dashboard) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Total Pendapatan Penjualan -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Pendapatan Terdata</span>
                        <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-xs text-slate-400 font-medium">Rp</span>
                        <span class="font-mono text-2xl font-bold text-sky-600">{{ number_format($totalRevenue, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Kredit kas masuk per filter aktif</p>
                </div>

                <!-- Total Berat Terjual -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Berat Terjual</span>
                        <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($stockSummary['total_sold_kg'], 1) }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Diserap oleh mitra pembeli/pengepul</p>
                </div>

                <!-- Sisa Stok Terpilah -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Sisa Stok Terpilah</span>
                        <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-amber-600">{{ number_format($stockSummary['sellable_stock_kg'], 1) }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Tersedia di TPS siap untuk dijual</p>
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
                    <button wire:click="openCreateModal"
                            type="button"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm shadow-emerald-600/20 transition active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Catat Penjualan</span>
                    </button>
                </div>
            </div>

            <!-- Sales History Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-800">Riwayat Penjualan Sampah</h3>
                        <span class="text-xs text-slate-400">({{ $sales->total() }} Transaksi Terdata)</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/75 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Kampus & Pembeli / Pengepul</th>
                                <th class="py-3 px-4">Rincian Sampah Terjual</th>
                                <th class="py-3 px-4 text-right">Total Berat</th>
                                <th class="py-3 px-4 text-right">Total Nilai (Rp)</th>
                                <th class="py-3 px-4">Petugas</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse ($sales as $sale)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-3 px-4 whitespace-nowrap font-medium text-slate-900">
                                        <div>{{ $sale->sale_date->translatedFormat('d M Y') }}</div>
                                        <div class="text-[10px] text-slate-400 font-normal">{{ $sale->created_at->format('H:i') }} WIB</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-900">{{ $sale->campus->name }}</div>
                                        <div class="text-[11px] text-sky-700 flex items-center gap-1 mt-0.5 font-medium">
                                            <svg class="w-3 h-3 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            <span>{{ $sale->buyer->name }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex flex-wrap gap-1.5 max-w-md">
                                            @foreach ($sale->items as $item)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-sky-50 text-sky-700 border border-sky-100">
                                                    <span>{{ $item->wasteType->name }}:</span>
                                                    <span class="font-bold">{{ number_format($item->weight_kg, 1) }} kg</span>
                                                    <span class="text-slate-400">@ Rp {{ number_format($item->price_per_kg, 0, ',', '.') }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                        @if ($sale->notes)
                                            <div class="text-[10px] text-slate-400 mt-1 italic line-clamp-1">
                                                Catatan: {{ $sale->notes }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap text-sm">
                                        {{ number_format($sale->total_weight, 1) }} <span class="text-xs font-sans font-normal text-slate-500">kg</span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600 whitespace-nowrap text-sm">
                                        Rp {{ number_format($sale->total_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap text-slate-500 text-[11px]">
                                        {{ $sale->creator?->name ?? 'Sistem' }}
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <button wire:click="confirmDelete({{ $sale->id }})" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                                title="Hapus Transaksi">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-400">
                                        <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Belum ada transaksi penjualan untuk filter yang dipilih.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-100">
                    {{ $sales->links() }}
                </div>
            </div>

            <!-- Modal Form Catat Penjualan -->
            @if ($showCreateModal)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Catat Penjualan Sampah Terpilah</h3>
                                <p class="text-xs text-slate-500">Pencatatan transaksi penjualan ke pembeli/pengepul mitra</p>
                            </div>
                            <button wire:click="closeCreateModal" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form wire:submit="saveSale" class="p-6 space-y-6">
                            <!-- Info Transaksi Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-slate-50/75 p-4 rounded-xl border border-slate-200/60">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kampus <span class="text-rose-500">*</span></label>
                                    @if ($isSuperAdmin)
                                        <select wire:model.live="formCampusId" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
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
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mitra Pembeli / Pengepul <span class="text-rose-500">*</span></label>
                                    <select wire:model="formBuyerId" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                                        @foreach ($buyers as $b)
                                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('formBuyerId') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Transaksi <span class="text-rose-500">*</span></label>
                                    <input type="date" wire:model="formDate" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                                    @error('formDate') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Items Matrix -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Rincian Jenis Sampah Terjual</h4>
                                        <p class="text-[11px] text-slate-500">Masukkan bobot aktual terjual (kg) dan harga kesepakatan per kg.</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-bold text-slate-700">Total Nilai: </span>
                                        <span class="font-mono text-sm font-bold text-emerald-600">Rp {{ number_format($this->totalSaleAmount, 0, ',', '.') }}</span>
                                    </div>
                                </div>

                                @error('saleItems')
                                    <div class="p-2.5 mb-3 rounded-lg bg-rose-50 text-rose-700 text-xs border border-rose-200">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <div class="border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-100">
                                    <div class="grid grid-cols-12 gap-2 bg-slate-50 px-3 py-2 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                                        <div class="col-span-4">Jenis Sampah</div>
                                        <div class="col-span-3 text-right">Berat Terjual (kg)</div>
                                        <div class="col-span-2 text-right">Harga / kg (Rp)</div>
                                        <div class="col-span-3 text-right">Subtotal (Rp)</div>
                                    </div>

                                    @foreach ($saleItems as $index => $item)
                                        <div class="grid grid-cols-12 gap-2 px-3 py-2.5 items-center hover:bg-slate-50/50 transition">
                                            <div class="col-span-4">
                                                <div class="text-xs font-semibold text-slate-800">{{ $item['waste_name'] }}</div>
                                            </div>
                                            <div class="col-span-3">
                                                <input type="number" step="0.01" min="0" 
                                                       wire:model="saleItems.{{ $index }}.weight_kg" 
                                                       wire:input="updateSubtotal({{ $index }})"
                                                       placeholder="0.0" 
                                                       class="w-full text-right font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 py-1.5">
                                                @error("saleItems.{$index}.weight_kg")
                                                    <span class="block text-rose-500 text-[10px] mt-0.5">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="col-span-2">
                                                <input type="number" step="100" min="0" 
                                                       wire:model="saleItems.{{ $index }}.price_per_kg" 
                                                       wire:input="updateSubtotal({{ $index }})"
                                                       class="w-full text-right font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 py-1.5">
                                            </div>
                                            <div class="col-span-3 text-right font-mono font-bold text-xs text-slate-800">
                                                Rp {{ number_format($item['subtotal'] ?? 0, 0, ',', '.') }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Notes Field -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Transaksi (Opsional)</label>
                                <textarea wire:model="formNotes" rows="2" placeholder="Nomor nota, keterangan pembayaran tunai/transfer, catatan armada pengepul..." class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                                @error('formNotes') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                                <button type="button" wire:click="closeCreateModal" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition">
                                    Batal
                                </button>
                                <button type="submit" class="px-5 py-2.5 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-sm shadow-emerald-600/20 transition active:scale-95">
                                    Simpan Transaksi & Kredit Kas
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Modal Konfirmasi Hapus Transaksi (Dribbble Clean) -->
            @if ($confirmDeleteSaleId)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md p-6 text-center">
                        <div class="w-12 h-12 rounded-full bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Hapus Transaksi Penjualan?</h3>
                        <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                            Tindakan ini akan membatalkan penjualan, mengembalikan kuota stok sampah di TPS, dan menghapus pencatatan jurnal Kredit di buku kas kampus.
                        </p>
                        <div class="flex items-center justify-center gap-3 mt-6">
                            <button type="button" wire:click="cancelDelete" class="w-full py-2.5 px-4 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                Batal
                            </button>
                            <button type="button" wire:click="deleteSale" class="w-full py-2.5 px-4 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm shadow-rose-600/20 transition active:scale-95">
                                Ya, Hapus Transaksi
                            </button>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>

