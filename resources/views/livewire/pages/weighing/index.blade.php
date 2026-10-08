<?php

use App\Models\Campus;
use App\Models\WasteSource;
use App\Models\WasteType;
use App\Models\WeighingItem;
use App\Models\WeighingSession;
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

    // Modal state
    public bool $showCreateModal = false;
    public ?int $viewSessionId = null;

    // Form Session
    public ?int $formCampusId = null;
    public ?int $formSourceId = null;
    public string $formDate = '';
    public string $formNotes = '';

    // Dynamic Multi-row Items: [ ['waste_type_id' => x, 'weight_kg' => y, 'volume_m3' => z] ]
    public array $formItems = [];

    public function mount(): void
    {
        $user = auth()->user();
        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $this->selectedCampusId = session('active_campus_id') ?? $user->campus_id ?? null;
        } else {
            $this->selectedCampusId = $user->campus_id;
        }
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->filterDateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->filterDateTo = Carbon::today()->format('Y-m-d');
        
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $user = auth()->user();
        $this->formCampusId = $this->selectedCampusId ?? $user->campus_id ?? Campus::first()?->id;
        $this->formSourceId = WasteSource::where('campus_id', $this->formCampusId)->first()?->id;
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->formNotes = '';
        
        // Inisialisasi baris penimbangan otomatis untuk semua tipe sampah aktif
        $activeTypes = WasteType::where('is_active', true)->orderBy('category')->orderBy('name')->get();
        $this->formItems = [];
        foreach ($activeTypes as $type) {
            $this->formItems[] = [
                'waste_type_id' => $type->id,
                'waste_name' => $type->name,
                'category' => $type->category,
                'is_sellable' => $type->is_sellable,
                'weight_kg' => '',
                'volume_m3' => '',
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

    public function updatedFormCampusId(): void
    {
        $this->formSourceId = WasteSource::where('campus_id', $this->formCampusId)->first()?->id;
    }

    public function saveWeighing(): void
    {
        $this->validate([
            'formCampusId' => ['required', 'exists:campuses,id'],
            'formSourceId' => ['nullable', 'exists:waste_sources,id'],
            'formDate' => ['required', 'date'],
            'formNotes' => ['nullable', 'string', 'max:500'],
            'formItems' => ['required', 'array', 'min:1'],
            'formItems.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'formItems.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'formItems.*.volume_m3' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        // Cek minimal ada satu jenis sampah yang diisi bobot > 0
        $hasFilledItem = false;
        foreach ($this->formItems as $item) {
            if (!empty($item['weight_kg']) && floatval($item['weight_kg']) > 0) {
                $hasFilledItem = true;
                break;
            }
        }

        if (!$hasFilledItem) {
            $this->addError('formItems', 'Harap isi bobot timbangan minimal untuk salah satu jenis sampah.');
            return;
        }

        $session = WeighingSession::create([
            'campus_id' => $this->formCampusId,
            'waste_source_id' => $this->formSourceId,
            'weigh_date' => $this->formDate,
            'created_by' => auth()->id(),
            'notes' => $this->formNotes ? strip_tags(trim($this->formNotes)) : null,
        ]);

        foreach ($this->formItems as $item) {
            $weight = !empty($item['weight_kg']) ? floatval($item['weight_kg']) : 0;
            $volume = !empty($item['volume_m3']) ? floatval($item['volume_m3']) : null;

            if ($weight > 0) {
                WeighingItem::create([
                    'weighing_session_id' => $session->id,
                    'waste_type_id' => $item['waste_type_id'],
                    'weight_kg' => $weight,
                    'volume_m3' => $volume,
                ]);
            }
        }

        $this->showCreateModal = false;
        $this->resetForm();
        session()->flash('status', 'Data penimbangan harian berhasil disimpan dengan rapi.');
    }

    // Modal Deletion state
    public ?int $confirmDeleteSessionId = null;

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteSessionId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteSessionId = null;
    }

    public function deleteSession(): void
    {
        if (!$this->confirmDeleteSessionId) {
            return;
        }

        $session = WeighingSession::findOrFail($this->confirmDeleteSessionId);
        
        // Otorisasi: hanya Super Admin atau user dari kampus bersangkutan
        if (auth()->user()->campus_id && auth()->user()->campus_id !== $session->campus_id && !auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            session()->flash('error', 'Anda tidak memiliki otoritas untuk menghapus data kampus ini.');
            $this->confirmDeleteSessionId = null;
            return;
        }

        $session->delete();
        $this->confirmDeleteSessionId = null;
        session()->flash('status', 'Data sesi penimbangan berhasil dihapus.');
    }

    public function with(StockService $stockService): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        $campusQueryId = $isSuperAdmin ? $this->selectedCampusId : $user->campus_id;

        $sessionsQuery = WeighingSession::with(['campus', 'wasteSource', 'creator', 'items.wasteType'])
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('weigh_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('weigh_date', '<=', $this->filterDateTo))
            ->orderBy('weigh_date', 'desc')
            ->orderBy('id', 'desc');

        $stockSummary = $stockService->getStockSummary($campusQueryId);

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => Campus::orderBy('id')->get(),
            'wasteSources' => WasteSource::where('campus_id', $this->formCampusId)->get(),
            'sessions' => $sessionsQuery->paginate(15),
            'stockSummary' => $stockSummary,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Penimbangan Sampah Harian</h2>
                <p class="text-xs text-slate-500 mt-0.5">Pencatatan volume dan bobot sampah masuk dari titik sumber kampus UAD</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Penimbangan Aktif
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

    <!-- Stock Summary Metric Cards (Clean Dribbble style matching dashboard) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Masuk -->
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Akumulasi Timbang</span>
                <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($stockSummary['total_weighed_kg'], 1) }}</span>
                <span class="text-xs text-slate-500 font-medium">kg</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Semua jenis sampah terdata</p>
        </div>

        <!-- Terpilah Siap Jual -->
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Stok Terpilah (Siap Jual)</span>
                <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="font-mono text-2xl font-bold text-sky-600">{{ number_format($stockSummary['sellable_stock_kg'], 1) }}</span>
                <span class="text-xs text-slate-500 font-medium">kg</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Potensi pendapatan kas kampus</p>
        </div>

        <!-- Residu -->
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Stok Residu (Perlu Angkut)</span>
                <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="font-mono text-2xl font-bold text-amber-600">{{ number_format($stockSummary['residual_stock_kg'], 1) }}</span>
                <span class="text-xs text-slate-500 font-medium">kg</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Residu & sisa organik ke vendor</p>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
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
                <span>Catat Penimbangan</span>
            </button>
        </div>
    </div>

    <!-- Sessions History Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-bold text-slate-800">Riwayat Sesi Penimbangan</h3>
                <span class="text-xs text-slate-400">({{ $sessions->total() }} Sesi Terdata)</span>
            </div>
            <button wire:click="openCreateModal"
                    type="button"
                    class="sm:hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold">
                + Timbang
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider text-[11px]">
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Kampus & Lokasi Sumber</th>
                        <th class="py-3 px-4">Rincian Komposisi Sampah</th>
                        <th class="py-3 px-4 text-right">Total Berat</th>
                        <th class="py-3 px-4">Petugas</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($sessions as $session)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3 px-4 whitespace-nowrap font-medium text-slate-900">
                                <div>{{ $session->weigh_date->translatedFormat('d M Y') }}</div>
                                <div class="text-[10px] text-slate-400 font-normal">{{ $session->created_at->format('H:i') }} WIB</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-900">{{ $session->campus->name }}</div>
                                <div class="text-[11px] text-slate-500 flex items-center gap-1 mt-0.5">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>{{ $session->wasteSource?->name ?? 'Titik Kampus Umum' }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex flex-wrap gap-1.5 max-w-md">
                                    @foreach ($session->items as $item)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium 
                                            {{ $item->wasteType->category === 'Organik' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : '' }}
                                            {{ $item->wasteType->category === 'Anorganik' ? 'bg-sky-50 text-sky-700 border border-sky-100' : '' }}
                                            {{ $item->wasteType->category === 'Residu' ? 'bg-amber-50 text-amber-700 border border-amber-100' : '' }}">
                                            <span>{{ $item->wasteType->name }}:</span>
                                            <span class="font-bold">{{ number_format($item->weight_kg, 1) }} kg</span>
                                        </span>
                                    @endforeach
                                </div>
                                @if ($session->notes)
                                    <div class="text-[10px] text-slate-400 mt-1 italic line-clamp-1">
                                        Catatan: {{ $session->notes }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap text-sm">
                                {{ number_format($session->total_weight, 1) }} <span class="text-xs font-sans font-normal text-slate-500">kg</span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-slate-500 text-[11px]">
                                {{ $session->creator?->name ?? 'Sistem' }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <button wire:click="confirmDelete({{ $session->id }})" 
                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                        title="Hapus Sesi">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <span>Belum ada data penimbangan untuk filter yang dipilih.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-100">
            {{ $sessions->links() }}
        </div>
    </div>

    <!-- Modal Form Catat Penimbangan -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Catat Penimbangan Sampah</h3>
                        <p class="text-xs text-slate-500">Input hasil timbangan harian per jenis sampah</p>
                    </div>
                    <button wire:click="closeCreateModal" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="saveWeighing" class="p-6 space-y-6">
                    <!-- Sesi Info Grid -->
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
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Titik Sumber</label>
                            <select wire:model="formSourceId" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                                <option value="">-- Pilih Titik Lokasi --</option>
                                @foreach ($wasteSources as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            @error('formSourceId') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Timbang <span class="text-rose-500">*</span></label>
                            <input type="date" wire:model="formDate" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                            @error('formDate') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Items Matrix -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Rincian Jenis Sampah Terpilah</h4>
                                <p class="text-[11px] text-slate-500">Masukkan bobot aktual timbangan (kg). Volume (m³) bersifat opsional.</p>
                            </div>
                        </div>

                        @error('formItems')
                            <div class="p-2.5 mb-3 rounded-lg bg-rose-50 text-rose-700 text-xs border border-rose-200">
                                {{ $message }}
                            </div>
                        @enderror

                        <div class="border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-100">
                            <div class="grid grid-cols-12 gap-2 bg-slate-50 px-3 py-2 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                                <div class="col-span-6">Jenis Sampah</div>
                                <div class="col-span-3 text-right">Berat (kg) <span class="text-rose-500">*</span></div>
                                <div class="col-span-3 text-right">Volume (m³)</div>
                            </div>

                            @foreach ($formItems as $index => $item)
                                <div class="grid grid-cols-12 gap-2 px-3 py-2.5 items-center hover:bg-slate-50/50 transition">
                                    <div class="col-span-6 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full 
                                            {{ $item['category'] === 'Organik' ? 'bg-emerald-500' : '' }}
                                            {{ $item['category'] === 'Anorganik' ? 'bg-sky-500' : '' }}
                                            {{ $item['category'] === 'Residu' ? 'bg-amber-500' : '' }}">
                                        </span>
                                        <div>
                                            <div class="text-xs font-semibold text-slate-800">{{ $item['waste_name'] }}</div>
                                            <div class="text-[10px] text-slate-400">
                                                {{ $item['category'] }} 
                                                @if ($item['is_sellable'])
                                                    <span class="text-sky-600 font-medium">| Siap Jual</span>
                                                @else
                                                    <span class="text-amber-600 font-medium">| Residu/Kompos</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" step="0.01" min="0" 
                                               wire:model="formItems.{{ $index }}.weight_kg" 
                                               placeholder="0.0" 
                                               class="w-full text-right font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 py-1.5">
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" step="0.001" min="0" 
                                               wire:model="formItems.{{ $index }}.volume_m3" 
                                               placeholder="opsional" 
                                               class="w-full text-right font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 py-1.5">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Notes Field -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan (Opsional)</label>
                        <textarea wire:model="formNotes" rows="2" placeholder="Kondisi cuaca, shift pengangkutan, atau catatan khusus penimbangan..." class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                        @error('formNotes') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="closeCreateModal" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition">
                            Batal
                        </button>
                        <button type="submit" class="px-5 py-2.5 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-sm shadow-emerald-600/20 transition active:scale-95">
                            Simpan Data Penimbangan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Konfirmasi Hapus (Dribbble Clean Consistent Modal) -->
    @if ($confirmDeleteSessionId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">Hapus Sesi Penimbangan?</h3>
                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                    Tindakan ini akan menghapus catatan penimbangan dan seluruh rincian bobot sampah di dalamnya. Data yang dihapus tidak dapat dipulihkan.
                </p>
                <div class="flex items-center justify-center gap-3 mt-6">
                    <button type="button" wire:click="cancelDelete" class="w-full py-2.5 px-4 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="button" wire:click="deleteSession" class="w-full py-2.5 px-4 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm shadow-rose-600/20 transition active:scale-95">
                        Ya, Hapus Sesi
                    </button>
                </div>
            </div>
        </div>
    @endif
        </div>
    </div>
</div>

