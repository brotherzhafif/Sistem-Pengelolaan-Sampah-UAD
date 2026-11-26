<div x-data="{
    showModal: false,
    showEditModal: @entangle('showEditModal'),
    deleteModal: false
}"
@close-modal.window="showModal = false; showEditModal = false; deleteModal = false"
@open-processing-modal.window="showModal = true">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Pengolahan Sampah Organik</h2>
                <p class="text-xs text-slate-500 mt-0.5">Pencatatan sampah organik yang diolah mandiri menjadi pupuk kompos dan pakan</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- 4 Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Organik Diolah</span>
                        <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalsFiltered['total_input_kg'] ?? 0, 1, ',', '.') }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Akumulasi bahan organik masuk pengolahan</p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-teal-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Hasil Olah: Pupuk</span>
                        <div class="w-7 h-7 rounded-md bg-teal-50 text-teal-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-teal-700">{{ number_format($totalsFiltered['total_compost_kg'] ?? 0, 1, ',', '.') }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Output kompos / pupuk organik</p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Hasil Olah: Pakan</span>
                        <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-amber-700">{{ number_format($totalsFiltered['total_feed_kg'] ?? 0, 1, ',', '.') }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Output pakan ternak / maggot</p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-slate-500"></div>
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Stok Organik TPS</span>
                            <p class="text-[10px] text-slate-400 mt-0.5">Siap diolah menjadi pupuk/pakan</p>
                        </div>
                        <div class="w-7 h-7 rounded-md bg-slate-100 text-slate-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($stockSummary['organic_stock_kg'] ?? 0, 1, ',', '.') }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Sisa sampah organik belum diolah</p>
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

                    <div class="flex flex-wrap items-center gap-2">
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
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 min-h-[40px] rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm shadow-emerald-600/20 transition active:scale-95 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Catat Pengolahan Organik</span>
                        </button>
                    </div>
                @endif
            </div>

            <!-- Table Riwayat Pengolahan Organik -->
            <div class="relative bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" id="processing-table-container">
                <div wire:loading wire:target="previousPage, nextPage, gotoPage, setPage, filterDateFrom, filterDateTo, selectedCampusId"
                     class="absolute inset-0 bg-white/70 backdrop-blur-[1px] z-20 flex items-center justify-center transition-all duration-150">
                    <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900/90 text-white text-xs font-semibold shadow-lg border border-slate-800">
                        <svg class="animate-spin w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Memperbarui riwayat pengolahan...</span>
                    </div>
                </div>

                <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Riwayat Pengolahan Organik Mandiri</h3>
                        <p class="text-xs text-slate-500 mt-0.5">({{ $processings->total() }} Log Pengolahan Terdata)</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-[11px] font-medium text-slate-500">Tampilkan:</label>
                        <select wire:model.live="perPage" class="text-xs font-semibold border-slate-200 rounded-lg px-2 py-1 bg-slate-50 text-slate-700 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 cursor-pointer">
                            <option value="10">10 data</option>
                            <option value="25">25 data</option>
                            <option value="50">50 data</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto overflow-y-auto max-h-[540px] sidebar-scroll">
                    <table class="w-full text-left text-xs text-slate-600 table-fixed border-collapse min-w-[760px]">
                        <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur-[2px] shadow-xs border-b border-slate-200 uppercase text-[10px] font-bold text-slate-500 tracking-wider">
                            <tr>
                                <th class="py-3 px-3 w-[13%]">Tanggal</th>
                                <th class="py-3 px-3 w-[17%]">Kampus &amp; Metode</th>
                                <th class="py-3 px-3 w-[25%]">Sampah Organik Diolah</th>
                                <th class="py-3 px-3 text-right w-[12%]">Berat Input</th>
                                <th class="py-3 px-3 w-[23%]">Hasil Olah (Pupuk / Pakan)</th>
                                <th class="py-3 px-2 text-center w-[10%]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @forelse ($processings as $proc)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="py-2.5 px-3">
                                        <div class="font-semibold text-slate-800 truncate">{{ $proc->process_date->format('d M Y') }}</div>
                                        <div class="text-[10px] text-slate-400 truncate">{{ $proc->creator?->name ?? 'Sistem' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 truncate">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 mb-0.5 truncate max-w-full" title="{{ $proc->campus->name }}">
                                            {{ $proc->campus->name }}
                                        </span>
                                        <div class="text-[11px] text-slate-600 truncate">{{ $proc->method ?: 'Kompos / Mandiri' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($proc->items as $item)
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-800 border border-emerald-200/70">
                                                    <span class="truncate max-w-[115px]" title="{{ $item->wasteType?->name }}">{{ $item->wasteType?->name ?? 'Organik' }}</span>
                                                    <span class="font-mono font-bold">{{ number_format($item->weight_kg, 1, ',', '.') }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                        {{ number_format($proc->input_weight_kg, 1, ',', '.') }} <span class="text-xs font-sans font-normal text-slate-500">kg</span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            @if ((float) $proc->compost_output_kg > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-teal-50 text-teal-800 border border-teal-200/70 text-[11px] font-semibold">
                                                    <span>Pupuk:</span>
                                                    <span class="font-mono font-bold">{{ number_format($proc->compost_output_kg, 1, ',', '.') }} kg</span>
                                                </span>
                                            @endif
                                            @if ((float) $proc->feed_output_kg > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200/70 text-[11px] font-semibold">
                                                    <span>Pakan:</span>
                                                    <span class="font-mono font-bold">{{ number_format($proc->feed_output_kg, 1, ',', '.') }} kg</span>
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button wire:click="viewProcessing({{ $proc->id }})"
                                                    type="button"
                                                    title="Lihat Rincian Pengolahan"
                                                    aria-label="Lihat Rincian Pengolahan"
                                                    class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            @if ($canCreate)
                                                <button wire:click="openEditModal({{ $proc->id }})"
                                                        type="button"
                                                        title="Edit Pengolahan Organik"
                                                        aria-label="Edit Pengolahan Organik"
                                                        class="p-1.5 text-slate-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition cursor-pointer">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button @click="deleteModal = true; $wire.confirmDelete({{ $proc->id }})"
                                                        type="button"
                                                        title="Hapus Catatan Pengolahan"
                                                        aria-label="Hapus Catatan Pengolahan"
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
                                    <td colspan="6" class="py-8 text-center text-slate-400">
                                        <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <span>Belum ada pencatatan pengolahan sampah organik untuk filter yang dipilih.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/30">
                    {{ $processings->links(data: ['scrollTo' => false]) }}
                </div>
            </div>

            <!-- Modal Create Pengolahan Organik -->
            <template x-teleport="body">
                <div x-show="showModal"
                     x-cloak
                     style="display: none;"
                     @keydown.escape.window="if (showModal) { showModal = false; $wire.closeCreateModal(); }"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Catat Pengolahan Sampah Organik</h3>
                                <p class="text-xs text-slate-500">Catat bahan sampah organik dan hasil olahannya berupa pupuk atau pakan</p>
                            </div>
                            <button @click="showModal = false; $wire.closeCreateModal()" type="button" aria-label="Tutup modal" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form wire:submit="saveProcessing" class="p-6 space-y-5">
                            <datalist id="organic-method-presets">
                                <option value="Kompos Aerobik">
                                <option value="Budidaya Maggot BSF">
                                <option value="Kompos & Pakan Campuran">
                                <option value="Eco-Enzyme">
                            </datalist>

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
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Olah <span class="text-rose-500">*</span></label>
                                    <input type="date" wire:model="formDate" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                                    @error('formDate') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Metode Pengolahan</label>
                                    <input type="text" list="organic-method-presets" wire:model="formMethod" placeholder="Kompos / Maggot BSF" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                                    @error('formMethod') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Input Bahan Organik -->
                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                    <div>
                                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Bahan Sampah Organik <span class="text-rose-500">*</span></h4>
                                        <p class="text-[11px] text-slate-500">Pilih jenis sampah organik dan masukkan bobot yang diolah.</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-bold text-slate-700">Total Input: </span>
                                        <span class="font-mono text-sm font-bold text-emerald-700">{{ number_format($this->totalInputWeight, 1, ',', '.') }} kg</span>
                                    </div>
                                </div>

                                @error('processingItems')
                                    <div class="p-2.5 mb-3 rounded-lg bg-rose-50 text-rose-700 text-xs border border-rose-200">{{ $message }}</div>
                                @enderror

                                @if (empty($processingItems))
                                    <div class="p-5 text-center bg-slate-50 rounded-xl border border-dashed border-slate-200">
                                        <h5 class="text-xs font-bold text-slate-800">Belum Ada Stok Sampah Organik di TPS</h5>
                                        <p class="text-[11px] text-slate-500 mt-1 max-w-sm mx-auto leading-relaxed">
                                            Silakan catat penimbangan sampah kategori Organik (Sisa Makanan atau Sampah Taman) terlebih dahulu pada menu Penimbangan.
                                        </p>
                                    </div>
                                @else
                                    <div class="border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-100">
                                        @foreach ($processingItems as $index => $item)
                                            <div class="p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/50 transition">
                                                <div>
                                                    <div class="text-xs font-semibold text-slate-800">{{ $item['waste_name'] }}</div>
                                                    <div class="inline-flex items-center gap-1 px-1.5 py-0.5 mt-1 rounded bg-emerald-50 text-[10px] font-semibold text-emerald-800 border border-emerald-200/60">
                                                        <span>Stok TPS:</span>
                                                        <span class="font-mono">{{ number_format($item['available_stock_kg'], 1, ',', '.') }} kg</span>
                                                    </div>
                                                </div>
                                                <div class="w-full sm:w-44">
                                                    <input type="number" step="0.01" min="0" max="{{ $item['available_stock_kg'] }}"
                                                           wire:model="processingItems.{{ $index }}.weight_kg"
                                                           wire:input="updateItemWeight({{ $index }})"
                                                           x-on:input="
                                                               let maxVal = {{ (float) $item['available_stock_kg'] }};
                                                               let val = parseFloat($el.value);
                                                               if (!isNaN(val) && val > maxVal) {
                                                                   $el.value = maxVal;
                                                                   $wire.set('processingItems.{{ $index }}.weight_kg', maxVal);
                                                                   $wire.updateItemWeight({{ $index }});
                                                                   window.dispatchEvent(new CustomEvent('top-alert', {
                                                                       detail: { message: 'Bobot ' + @js($item['waste_name']) + ' disesuaikan ke sisa stok (' + maxVal.toLocaleString('id-ID') + ' kg)', type: 'warning', title: 'Batas Stok Organik' }
                                                                   }));
                                                               }
                                                           "
                                                           placeholder="0.0 kg"
                                                           class="w-full text-right font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 py-2">
                                                    @error("processingItems.{$index}.weight_kg")
                                                        <span class="block text-rose-500 text-[10px] mt-0.5">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <!-- Hasil Olah: Pupuk & Pakan -->
                            <div class="p-4 rounded-xl bg-teal-50/60 border border-teal-200/70 space-y-3">
                                <div>
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-teal-900">Hasil Pengolahan (Pupuk &amp; Pakan) <span class="text-rose-500">*</span></h4>
                                    <p class="text-[11px] text-teal-700">Isi jumlah hasil pupuk kompos, hasil pakan, atau keduanya.</p>
                                </div>
                                @error('formOutputs')
                                    <div class="p-2 rounded-lg bg-rose-50 text-rose-700 text-xs border border-rose-200">{{ $message }}</div>
                                @enderror
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Hasil Pupuk / Kompos (kg)</label>
                                        <input type="number" step="0.01" min="0" wire:model="formCompostOutputKg" placeholder="0.0" class="w-full font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-teal-500 focus:border-teal-500 bg-white">
                                        @error('compost_output_kg') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Hasil Pakan (kg)</label>
                                        <input type="number" step="0.01" min="0" wire:model="formFeedOutputKg" placeholder="0.0" class="w-full font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 bg-white">
                                        @error('feed_output_kg') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan (Opsional)</label>
                                <textarea wire:model="formNotes" rows="2" placeholder="Lokasi bak kompos, kondisi bahan, atau keterangan pemanfaatan..." class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"></textarea>
                                @error('formNotes') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                            </div>

                            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5 sticky bottom-0 bg-white">
                                <button @click="showModal = false; $wire.closeCreateModal()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                    Batal
                                </button>
                                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition active:scale-95 cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="saveProcessing">Simpan Pengolahan Organik</span>
                                    <span wire:loading wire:target="saveProcessing">Menyimpan...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <!-- Modal Edit Pengolahan Organik -->
            <template x-teleport="body">
                <div x-show="showEditModal"
                     x-cloak
                     style="display: none;"
                     @keydown.escape.window="if (showEditModal) { showEditModal = false; $wire.closeEditModal(); }"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
                    <div @click.away="showEditModal = false; $wire.closeEditModal()"
                         class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto animate-scaleUp">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                            <div>
                                <h3 class="font-bold text-base text-slate-800">Edit Pengolahan Sampah Organik</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Sesuaikan bobot bahan organik dan hasil olah pupuk / pakan</p>
                            </div>
                            <button @click="showEditModal = false; $wire.closeEditModal()" type="button" aria-label="Tutup modal edit" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form wire:submit="updateProcessing" class="p-6 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Pengolahan <span class="text-rose-500">*</span></label>
                                    <input type="date" wire:model="editDate" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                    @error('process_date') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Metode Pengolahan</label>
                                    <input type="text" list="organic-method-presets" wire:model="editMethod" placeholder="Kompos / Maggot BSF" class="w-full text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                </div>
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Bahan Sampah Organik</h4>
                                    <span class="font-mono text-xs font-bold text-emerald-700">Total Input: {{ number_format($this->editTotalInputWeight, 1, ',', '.') }} kg</span>
                                </div>
                                @error('editItems')
                                    <div class="p-2 mb-2 rounded-lg bg-rose-50 text-rose-700 text-xs border border-rose-200">{{ $message }}</div>
                                @enderror
                                <div class="border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-100">
                                    @foreach ($editItems as $index => $item)
                                        <div class="p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <div>
                                                <div class="text-xs font-semibold text-slate-800">{{ $item['waste_name'] }}</div>
                                                <div class="text-[10px] text-slate-500 mt-0.5">
                                                    Maks: <strong class="font-mono text-slate-700">{{ number_format($item['max_allowable_kg'], 1, ',', '.') }} kg</strong>
                                                </div>
                                            </div>
                                            <div class="w-full sm:w-44">
                                                <input type="number" step="0.01" min="0" max="{{ $item['max_allowable_kg'] }}"
                                                       wire:model.live.debounce.250ms="editItems.{{ $index }}.weight_kg"
                                                       class="w-full text-right font-mono text-xs rounded-lg border-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 py-2">
                                                @error("editItems.{$index}.weight_kg")
                                                    <span class="block text-rose-500 text-[10px] mt-0.5">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="p-4 rounded-xl bg-teal-50/60 border border-teal-200/70 space-y-3">
                                @error('editOutputs')
                                    <div class="p-2 rounded-lg bg-rose-50 text-rose-700 text-xs border border-rose-200">{{ $message }}</div>
                                @enderror
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Hasil Pupuk (kg)</label>
                                        <input type="number" step="0.01" min="0" wire:model="editCompostOutputKg" class="w-full font-mono text-xs rounded-lg border-slate-300 bg-white">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Hasil Pakan (kg)</label>
                                        <input type="number" step="0.01" min="0" wire:model="editFeedOutputKg" class="w-full font-mono text-xs rounded-lg border-slate-300 bg-white">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Tambahan</label>
                                <textarea wire:model="editNotes" rows="2" class="w-full text-xs rounded-lg border-slate-300"></textarea>
                            </div>

                            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5 sticky bottom-0 bg-white">
                                <button @click="showEditModal = false; $wire.closeEditModal()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                    Batal
                                </button>
                                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition cursor-pointer">
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <!-- Modal Konfirmasi Hapus -->
            <template x-teleport="body">
                <div x-show="deleteModal"
                     x-cloak
                     style="display: none;"
                     @keydown.escape.window="if (deleteModal) { deleteModal = false; $wire.cancelDelete(); }"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md p-6 text-center">
                        <div class="w-12 h-12 rounded-full bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Hapus Catatan Pengolahan?</h3>
                        <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">
                            Data pengolahan #{{ $confirmDeleteId }} tanggal {{ $deleteDateLabel }} dengan bahan <span class="font-mono font-bold text-slate-900">{{ number_format($deleteInputWeight, 1, ',', '.') }} kg</span> akan dihapus dan stok organik dikembalikan ke TPS.
                        </p>
                        <div class="flex items-center justify-center gap-3 mt-6">
                            <button type="button" @click="deleteModal = false; $wire.cancelDelete()" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-300 text-xs font-semibold text-slate-800 transition cursor-pointer">
                                Batal
                            </button>
                            <button type="button" wire:click="deleteProcessing" class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition cursor-pointer">
                                Ya, Hapus Data
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Modal Detail Pengolahan Organik -->
            @if ($viewProcessingId && $selectedProcessing)
                <template x-teleport="body">
                    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn"
                         x-data
                         @keydown.escape.window="$wire.closeViewModal()">
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
                            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                                <div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">OLAH #{{ $selectedProcessing->id }}</span>
                                    <h3 class="text-base font-bold text-slate-900 mt-1">Rincian Pengolahan Organik</h3>
                                </div>
                                <button wire:click="closeViewModal" type="button" aria-label="Tutup modal detail" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div class="p-6 space-y-4">
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                        <span class="text-[11px] font-medium text-slate-500">Input Organik</span>
                                        <div class="font-mono text-lg font-bold text-slate-900 mt-0.5">{{ number_format($selectedProcessing->input_weight_kg, 1, ',', '.') }} kg</div>
                                    </div>
                                    <div class="p-3 rounded-xl bg-teal-50/70 border border-teal-200/70">
                                        <span class="text-[11px] font-medium text-teal-700">Hasil Pupuk</span>
                                        <div class="font-mono text-lg font-bold text-teal-800 mt-0.5">{{ number_format($selectedProcessing->compost_output_kg, 1, ',', '.') }} kg</div>
                                    </div>
                                    <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-200/70">
                                        <span class="text-[11px] font-medium text-amber-700">Hasil Pakan</span>
                                        <div class="font-mono text-lg font-bold text-amber-800 mt-0.5">{{ number_format($selectedProcessing->feed_output_kg, 1, ',', '.') }} kg</div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 overflow-hidden">
                                    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-700">Bahan Organik Digunakan</div>
                                    <div class="divide-y divide-slate-100 text-xs">
                                        @foreach ($selectedProcessing->items as $item)
                                            <div class="px-4 py-2.5 flex items-center justify-between">
                                                <span class="font-semibold text-slate-800">{{ $item->wasteType?->name ?? 'Organik' }}</span>
                                                <span class="font-mono font-bold text-slate-900">{{ number_format($item->weight_kg, 1, ',', '.') }} kg</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 divide-y divide-slate-100 overflow-hidden text-xs">
                                    <div class="px-4 py-2.5 flex justify-between bg-slate-50/50">
                                        <span class="text-slate-500">Unit Kampus</span>
                                        <span class="font-semibold text-slate-800">{{ $selectedProcessing->campus->name }}</span>
                                    </div>
                                    <div class="px-4 py-2.5 flex justify-between">
                                        <span class="text-slate-500">Tanggal &amp; Metode</span>
                                        <span class="font-semibold text-slate-800">{{ $selectedProcessing->process_date->translatedFormat('d M Y') }} ({{ $selectedProcessing->method ?: 'Pengolahan Mandiri' }})</span>
                                    </div>
                                    <div class="px-4 py-2.5 flex justify-between bg-slate-50/50">
                                        <span class="text-slate-500">Petugas Pencatat</span>
                                        <span class="text-slate-700">{{ $selectedProcessing->creator?->name ?? 'Sistem' }}</span>
                                    </div>
                                </div>

                                @if ($selectedProcessing->notes)
                                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70 text-xs text-slate-700">
                                        <span class="font-semibold block text-slate-600 mb-0.5">Catatan:</span>
                                        {{ $selectedProcessing->notes }}
                                    </div>
                                @endif
                            </div>

                            <div class="px-6 py-3 border-t border-slate-100 flex justify-end bg-slate-50/50">
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
