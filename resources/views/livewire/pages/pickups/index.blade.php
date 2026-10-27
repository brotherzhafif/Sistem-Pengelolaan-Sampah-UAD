<div x-data="{ 
    showModal: false, 
    showEditModal: @entangle('showEditModal'),
    deleteModal: false 
}"
@close-modal.window="showModal = false; showEditModal = false; deleteModal = false"
@open-pickup-modal.window="showModal = true">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Pengangkutan Residu Sampah</h2>
                <p class="text-xs text-slate-500 mt-0.5">Pencatatan pengangkutan sisa residu ke TPA oleh vendor & debet biaya kas operasional</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
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
                        <div>
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Sisa Residu di TPS</span>
                            <p class="text-[10px] text-slate-400 mt-0.5">Semua sampah yang tidak bisa dijual</p>
                        </div>
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
                    <p class="text-[11px] text-slate-400 mt-1">Antrean penjemputan pengangkutan vendor</p>
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

                @if ($canCreate)
                <div class="flex items-center gap-2">
                    <button wire:click="openCreateModal"
                            type="button"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-sm shadow-amber-600/20 transition active:scale-95 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Catat Pengangkutan</span>
                    </button>
                </div>
                @endif
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
                    <table class="w-full text-left text-xs text-slate-600 table-fixed">
                        <thead class="bg-slate-50/75 border-b border-slate-100 uppercase text-[10px] font-bold text-slate-500 tracking-wider">
                            <tr>
                                <th class="py-3 px-3 w-[13%]">Tanggal</th>
                                <th class="py-3 px-3 w-[23%]">Kampus & Vendor</th>
                                <th class="py-3 px-3 w-[17%]">Armada / Driver</th>
                                <th class="py-3 px-3 text-right w-[14%]">Volume (kg)</th>
                                <th class="py-3 px-3 text-right w-[17%]">Total Biaya (Rp)</th>
                                <th class="py-3 px-3 w-[10%]">Petugas</th>
                                <th class="py-3 px-2 text-center w-[6%]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @forelse ($pickups as $p)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="py-2.5 px-3">
                                        <div class="font-semibold text-slate-800">{{ $p->pickup_date->format('d M Y') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $p->created_at->format('H:i') }} WIB</div>
                                    </td>
                                    <td class="py-2.5 px-3 truncate">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 mb-0.5 truncate" title="{{ $p->campus->name }}">
                                            {{ $p->campus->name }}
                                        </span>
                                         <div class="font-semibold text-slate-800 truncate" title="{{ $p->vendor->name }}">{{ $p->vendor->name }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 truncate">
                                        <div class="text-slate-800 truncate" title="{{ $p->driver_name ?: '-' }}">{{ $p->driver_name ?: '-' }}</div>
                                        @if ($p->vehicle_plate)
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-mono bg-slate-100 text-slate-600">
                                                {{ $p->vehicle_plate }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                        {{ number_format($p->volume_kg, 1, ',', '.') }} kg
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <div class="font-mono font-bold text-rose-600">Rp {{ number_format($p->total_cost, 0, ',', '.') }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">@ Rp {{ number_format($p->cost_per_kg, 0, ',', '.') }}/kg</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-700 truncate" title="{{ $p->creator?->name ?? 'Sistem' }}">
                                        {{ $p->creator?->name ?? 'Sistem' }}
                                    </td>
                                    <td class="py-2.5 px-2 text-center">
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
                                            @if ($canCreate)
                                            <button wire:click="openEditModal({{ $p->id }})" 
                                                    type="button" 
                                                    title="Edit Catatan Pengangkutan"
                                                    class="p-1.5 text-slate-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
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
                                            @endif
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
                                    <input type="number" 
                                           step="any" 
                                           min="0" 
                                           wire:model.live.debounce.300ms="formVolumeKg" 
                                           x-on:input="
                                               let maxVal = {{ (float) $this->availableResidualStock }};
                                               let val = parseFloat($el.value);
                                               if (!isNaN(val) && maxVal > 0 && val > maxVal) {
                                                   $el.value = maxVal;
                                                   $wire.set('formVolumeKg', maxVal);
                                                   $wire.calculateTotalCost();
                                               }
                                           "
                                           placeholder="0.0" 
                                           class="w-full font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                    <div class="text-[10px] text-slate-500 font-medium mt-0.5">
                                        Sisa Residu di TPS: <strong class="text-amber-700 font-mono">{{ number_format($this->availableResidualStock, 1, ',', '.') }} kg</strong>
                                    </div>
                                    @error('formVolumeKg') <span class="text-rose-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tarif Vendor / kg (Rp) <span class="text-rose-500">*</span></label>
                                    <input type="number" 
                                           step="any" 
                                           min="0" 
                                           wire:model.live.debounce.250ms="formCostPerKg" 
                                           placeholder="Contoh: 100" 
                                           class="w-full font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                    <div class="text-[10px] text-slate-400 mt-0.5">Otomatis terisi dari data vendor (dapat disesuaikan)</div>
                                    @error('formCostPerKg') <span class="text-rose-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
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
                                <button type="submit" 
                                        wire:loading.attr="disabled"
                                        class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="savePickup">Simpan & Catat Debet Kas</span>
                                    <span wire:loading wire:target="savePickup">Menyimpan...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <!-- Modal Edit Pengangkutan (Teleported to Body) -->
            <template x-teleport="body">
                <div x-show="showEditModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
                    <div @click.away="showEditModal = false; $wire.closeEditModal()" 
                         class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-xl overflow-hidden animate-scaleUp">
                        <!-- Header Modal Edit -->
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <div>
                                <h3 class="font-bold text-base text-slate-800">Edit Catatan Pengangkutan Residu</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Penyesuaian data muatan residu dan sinkronisasi buku kas otomatis</p>
                            </div>
                            <button @click="showEditModal = false; $wire.closeEditModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <!-- Form Modal Edit -->
                        <form wire:submit="updatePickup" class="p-6 space-y-4">
                            @if (!empty($editBindingInfo['is_bound']))
                                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start gap-3">
                                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <div class="text-xs">
                                        <span class="font-bold">Transaksi pengangkutan ini terikat dengan Jurnal Buku Kas Keuangan.</span>
                                        <p class="mt-0.5 text-amber-800 leading-relaxed">
                                             Unit kampus dikunci. Perubahan volume muatan atau tarif akan otomatis memutakhirkan nominal biaya debet di buku kas.
                                        </p>
                                    </div>
                                </div>
                            @endif

                            <!-- Kampus & Vendor -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Unit Kampus <span class="text-rose-500">*</span></label>
                                    @if ($isSuperAdmin && empty($editBindingInfo['is_bound']))
                                        <select wire:model="editCampusId" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                            @foreach ($campuses as $c)
                                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="text-xs font-semibold text-slate-800 bg-slate-50 border border-slate-200 px-3 py-2 rounded-lg flex items-center justify-between">
                                            <span>{{ $campuses->firstWhere('id', $editCampusId)?->name ?? auth()->user()->campus?->name }}</span>
                                            @if (!empty($editBindingInfo['is_bound']))
                                                <span class="text-[10px] text-amber-700 font-bold bg-amber-100 px-1.5 py-0.5 rounded border border-amber-200">Terkunci</span>
                                            @endif
                                        </div>
                                    @endif
                                    @error('editCampusId') <span class="text-rose-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mitra Vendor Pengangkut <span class="text-rose-500">*</span></label>
                                    <select wire:model.live="editVendorId" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                        @foreach ($vendors as $v)
                                            <option value="{{ $v->id }}">{{ $v->name }} (Rp {{ number_format($v->cost_per_kg, 0, ',', '.') }}/kg)</option>
                                        @endforeach
                                    </select>
                                    @error('editVendorId') <span class="text-rose-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Tanggal Pengangkutan -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Pengangkutan <span class="text-rose-500">*</span></label>
                                <input type="date" wire:model="editDate" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                @error('editDate') <span class="text-rose-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                            </div>

                            <!-- Muatan & Tarif Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Bobot Muatan Residu (kg) <span class="text-rose-500">*</span></label>
                                    <input type="number" step="any" min="0" wire:model.live.debounce.300ms="editVolumeKg" placeholder="Contoh: 125.5" class="w-full font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    <div class="text-[10px] text-slate-500 mt-1 flex items-center justify-between">
                                        <span>Maks: <strong class="text-slate-800 font-mono">{{ number_format($editOldVolumeKg + $editAvailableResidual, 1, ',', '.') }} kg</strong></span>
                                        <span>(Sisa TPS: {{ number_format($editAvailableResidual, 1, ',', '.') }} kg)</span>
                                    </div>
                                    @error('editVolumeKg') <span class="text-rose-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tarif Vendor / kg (Rp) <span class="text-rose-500">*</span></label>
                                    <input type="number" 
                                           step="any" 
                                           min="0" 
                                           wire:model.live.debounce.250ms="editCostPerKg" 
                                           placeholder="Contoh: 100" 
                                           class="w-full font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                    @error('editCostPerKg') <span class="text-rose-500 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Total Biaya Debet Preview Card -->
                            <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200/60 flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-bold text-amber-900">Total Biaya Operasional Pengangkutan:</span>
                                    <p class="text-[11px] text-amber-700 mt-0.5">Otomatis disinkronkan ke Buku Kas saat disimpan</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono text-lg font-bold text-rose-600">Rp {{ number_format(floatval($editTotalCost), 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <!-- Armada & Driver Info -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Supir / Driver</label>
                                    <input type="text" wire:model="editDriverName" placeholder="Contoh: Pak Joko" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    @error('editDriverName') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Plat Kendaraan</label>
                                    <input type="text" wire:model="editVehiclePlate" placeholder="Contoh: AB 1234 CD" class="w-full uppercase font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    @error('editVehiclePlate') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Catatan -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan</label>
                                <textarea wire:model="editNotes" rows="2" placeholder="Keterangan kondisi residu atau ritase truk..." class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500"></textarea>
                                @error('editNotes') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                            </div>

                            <!-- Footer Form Modal -->
                            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                                <button @click="showEditModal = false; $wire.closeEditModal()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                    Batal
                                </button>
                                <button type="submit" 
                                        wire:loading.attr="disabled"
                                        class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition active:scale-95 cursor-pointer disabled:opacity-50"
                                        style="background-color: #059669; color: #ffffff;">
                                    <span wire:loading.remove wire:target="updatePickup">Simpan Perubahan</span>
                                    <span wire:loading wire:target="updatePickup">Menyimpan...</span>
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
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md p-6 text-center">
                        <div class="w-12 h-12 rounded-full bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Hapus Pengangkutan Residu?</h3>
                        <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">
                            Apakah Anda yakin ingin menghapus data pengangkutan <span class="font-bold text-slate-800">#{{ $confirmDeletePickupId }}</span> oleh <span class="font-bold text-slate-800">{{ $deletePickupVendor }}</span> seberat <span class="font-bold font-mono text-slate-900">{{ number_format($deletePickupVolume, 1, ',', '.') }} kg</span> (<span class="font-bold text-rose-600 font-mono">Rp {{ number_format($deletePickupCost, 0, ',', '.') }}</span>)?
                        </p>
                        
                        <div class="mt-3.5 p-3.5 bg-amber-50/90 border border-amber-200 rounded-xl text-left space-y-1.5">
                            <div class="flex items-center gap-1.5 text-amber-900 text-xs font-bold">
                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Konsekuensi Penghapusan:</span>
                            </div>
                            <ul class="text-[11px] text-amber-800 space-y-1 list-disc list-inside">
                                <li>Catatan beban biaya pada <span class="font-semibold">Buku Kas & Buku Besar</span> akan otomatis ikut terhapus.</li>
                                <li>Stok residu sebesar <span class="font-bold font-mono text-amber-800">{{ number_format($deletePickupVolume, 1, ',', '.') }} kg</span> akan otomatis <span class="font-semibold text-amber-800">kembali bertambah</span> di sistem penimbangan.</li>
                            </ul>
                        </div>

                        <div class="flex items-center justify-center gap-3 mt-6">
                            <button type="button" @click="deleteModal = false; $wire.cancelDelete()" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-300 text-xs font-semibold text-slate-800 transition text-center cursor-pointer">
                                Batal
                            </button>
                            <button type="button" wire:click="deletePickup" class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm shadow-rose-600/20 transition active:scale-95 cursor-pointer" style="background-color: #e11d48; color: #ffffff;">
                                Ya, Hapus Pengangkutan
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
