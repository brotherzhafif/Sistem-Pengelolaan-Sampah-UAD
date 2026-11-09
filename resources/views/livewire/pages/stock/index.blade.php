<div x-data="{ detailModal: false }"
     @close-modal.window="detailModal = false"
     @open-stock-detail-modal.window="detailModal = true">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Rekapitulasi Stok Sampah</h2>
                <p class="text-xs text-slate-500 mt-0.5">Monitoring ketersediaan stok fisik sampah terpilah, riwayat penimbangan, realisasi penjualan, dan sisa residu TPS3R</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- 4 Metric Cards (Clean Dribbble style matching dashboard & other modules) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. Total Akumulasi Timbang -->
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
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($stockSummary['total_weighed_kg'] ?? 0, 1) }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Akumulasi seluruh sampah masuk TPS</p>
                </div>

                <!-- 2. Total Terjual ke Mitra -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Terjual ke Mitra</span>
                        <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-sky-600">{{ number_format($stockSummary['total_sold_kg'] ?? 0, 1) }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] border-t border-slate-100 pt-2">
                        <span class="text-slate-500">Realisasi Nilai:</span>
                        <span class="font-mono font-semibold text-emerald-600">Rp {{ number_format($stockSummary['total_sales_revenue'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- 3. Stok Terpilah (Siap Jual) -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Stok Siap Jual</span>
                            <p class="text-[10px] text-slate-400 mt-0.5">Sampah bernilai jual tersedia</p>
                        </div>
                        <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-emerald-700">{{ number_format($stockSummary['sellable_stock_kg'] ?? 0, 1) }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] border-t border-slate-100 pt-2">
                        <span class="text-slate-500">Potensi Nilai:</span>
                        <span class="font-mono font-semibold text-emerald-700">Rp {{ number_format($stockSummary['potential_revenue'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- 4. Residu & Terangkut -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Residu & Terangkut</span>
                            <p class="text-[10px] text-slate-400 mt-0.5">Sisa organik & residu TPA</p>
                        </div>
                        <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-amber-600">{{ number_format($stockSummary['total_picked_up_kg'] ?? 0, 1) }}</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] border-t border-slate-100 pt-2">
                        <span class="text-slate-500">Sisa di TPS:</span>
                        <span class="font-mono font-semibold text-amber-700">{{ number_format($stockSummary['residual_stock_kg'] ?? 0, 1) }} kg</span>
                    </div>
                </div>
            </div>

            <!-- Filter & Search Toolbar (Matching other modules exactly) -->
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

                    <div class="relative w-full sm:w-60">
                        <input type="text"
                               wire:model.live.debounce.250ms="search"
                               placeholder="Cari jenis sampah..."
                               class="w-full text-xs border-slate-200 rounded-lg pl-8 pr-3 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 placeholder:text-slate-400">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-slate-500">Kategori:</label>
                        <select wire:model.live="filterCategory" class="text-xs font-medium border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="all">Semua Kategori</option>
                            <option value="Organik">Organik</option>
                            <option value="Anorganik">Anorganik</option>
                            <option value="Residu">Residu</option>
                            <option value="B3">B3</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-slate-500">Status:</label>
                        <select wire:model.live="filterSellable" class="text-xs font-medium border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="all">Semua Status</option>
                            <option value="sellable">Siap Jual</option>
                            <option value="non_sellable">Non-Komersial / Residu</option>
                        </select>
                    </div>

                    @if (!empty($search) || $filterCategory !== 'all' || $filterSellable !== 'all')
                        <button type="button" wire:click="resetFilters" class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1 rounded transition hover:bg-rose-50 cursor-pointer">
                            Reset Filter
                        </button>
                    @endif
                </div>

                <div class="text-xs text-slate-500 font-medium">
                    Menampilkan <span class="font-bold text-slate-800">{{ $items->count() }}</span> jenis sampah
                </div>
            </div>

            <!-- Stock Table Container (Matching other modules exactly) -->
            <div class="relative bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" id="stock-table-container">
                <!-- Independent Table Loading State Indicator -->
                <div wire:loading wire:target="search, filterCategory, filterSellable, selectedCampusId, setSort" 
                     class="absolute inset-0 bg-white/70 backdrop-blur-[1px] z-20 flex items-center justify-center transition-all duration-150">
                    <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900/90 text-white text-xs font-semibold shadow-lg border border-slate-800">
                        <svg class="animate-spin w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Memperbarui data stok sampah...</span>
                    </div>
                </div>

                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-800">Daftar Rekapitulasi Stok Sampah</h3>
                        <span class="text-xs text-slate-400">({{ $items->count() }} Jenis Sampah Terdata)</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs table-fixed">
                        <thead>
                            <tr class="bg-slate-50/75 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 w-[26%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('name')">
                                    <div class="flex items-center gap-1 truncate">
                                        <span>Jenis Sampah</span>
                                        @if ($sortBy === 'name')
                                            <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-2 w-[14%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('category')">
                                    <div class="flex items-center gap-1 truncate">
                                        <span>Kategori</span>
                                        @if ($sortBy === 'category')
                                            <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-2 text-center w-[12%]">Status</th>
                                <th class="py-3 px-2 text-right w-[12%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('weighed')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Timbang</span>
                                        @if ($sortBy === 'weighed')
                                            <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-2 text-right w-[12%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('sold')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Terjual</span>
                                        @if ($sortBy === 'sold')
                                            <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-3 text-right w-[12%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('stock')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Sisa Stok</span>
                                        @if ($sortBy === 'stock')
                                            <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-3 text-right w-[12%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('value')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Potensi (Rp)</span>
                                        @if ($sortBy === 'value')
                                            <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                        @endif
                                    </div>
                                </th>
                                <th class="py-3 px-2 text-center w-[8%]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse ($items as $item)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <!-- Jenis Sampah -->
                                    <td class="py-2.5 px-3 truncate">
                                        <span class="font-semibold text-slate-800 truncate block" title="{{ $item['waste_name'] }}">
                                            {{ $item['waste_name'] }}
                                        </span>
                                    </td>

                                    <!-- Kategori -->
                                    <td class="py-2.5 px-2 truncate">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold truncate max-w-full
                                            {{ $item['waste_category'] === 'Organik' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : '' }}
                                            {{ $item['waste_category'] === 'Anorganik' ? 'bg-sky-50 text-sky-700 border border-sky-100' : '' }}
                                            {{ $item['waste_category'] === 'Residu' ? 'bg-amber-50 text-amber-700 border border-amber-100' : '' }}
                                            {{ $item['waste_category'] === 'B3' ? 'bg-rose-50 text-rose-700 border border-rose-100' : '' }}">
                                            {{ $item['waste_category'] }}
                                        </span>
                                    </td>

                                    <!-- Status Jual -->
                                    <td class="py-2.5 px-2 text-center">
                                        @if ($item['is_sellable'])
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100" title="Harga default: Rp {{ number_format($item['default_price_per_kg'], 0, ',', '.') }}/kg">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span>Siap Jual</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                <span>Residu</span>
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Total Timbang -->
                                    <td class="py-2.5 px-2 text-right font-mono text-slate-700 whitespace-nowrap">
                                        {{ number_format($item['total_weighed_kg'], 1, ',', '.') }} <span class="text-[10px] font-sans text-slate-400">kg</span>
                                    </td>

                                    <!-- Terjual -->
                                    <td class="py-2.5 px-2 text-right font-mono text-slate-700 whitespace-nowrap">
                                        {{ number_format($item['total_sold_kg'], 1, ',', '.') }} <span class="text-[10px] font-sans text-slate-400">kg</span>
                                    </td>

                                    <!-- Sisa Stok -->
                                    <td class="py-2.5 px-3 text-right font-mono font-bold whitespace-nowrap {{ $item['available_stock_kg'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                        {{ number_format($item['available_stock_kg'], 1, ',', '.') }} <span class="text-[10px] font-sans font-normal text-slate-400">kg</span>
                                    </td>

                                    <!-- Potensi Nilai -->
                                    <td class="py-2.5 px-3 text-right font-mono text-slate-800 font-medium whitespace-nowrap">
                                        @if ($item['is_sellable'])
                                            Rp {{ number_format($item['potential_revenue'], 0, ',', '.') }}
                                        @else
                                            <span class="text-slate-300">-</span>
                                        @endif
                                    </td>

                                    <!-- Aksi (Lihat Detail) -->
                                    <td class="py-2.5 px-2 text-center">
                                        <div class="flex items-center justify-center">
                                            <button @click="detailModal = true; $wire.viewStockDetail({{ $item['waste_type_id'] }})"
                                                    type="button"
                                                    title="Lihat Rincian Stok"
                                                    class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-slate-400 text-xs">
                                        <svg class="w-8 h-8 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                        </svg>
                                        <span>Tidak ada data stok sampah yang cocok dengan filter pencarian.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Rincian Detail Stok (Teleported to Body for 100% Full Viewport Backdrop) -->
    <template x-teleport="body">
        <div x-show="detailModal"
             x-cloak
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg overflow-hidden animate-scaleUp">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Rincian Stok Jenis Sampah</h3>
                        <p class="text-xs text-slate-500">Informasi komprehensif penimbangan, penjualan, dan saldo stok</p>
                    </div>
                    <button @click="detailModal = false; $wire.closeDetailModal()" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4">
                    @if ($detailData)
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between">
                            <div>
                                <span class="text-xs text-slate-400 font-medium">Jenis Sampah:</span>
                                <h4 class="text-sm font-bold text-slate-900">{{ $detailData['waste_name'] }}</h4>
                            </div>
                            <span class="inline-block px-2.5 py-1 rounded-md text-xs font-semibold
                                {{ $detailData['waste_category'] === 'Organik' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                {{ $detailData['waste_category'] === 'Anorganik' ? 'bg-sky-100 text-sky-800' : '' }}
                                {{ $detailData['waste_category'] === 'Residu' ? 'bg-amber-100 text-amber-800' : '' }}
                                {{ $detailData['waste_category'] === 'B3' ? 'bg-rose-100 text-rose-800' : '' }}">
                                {{ $detailData['waste_category'] }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 rounded-lg border border-slate-100 bg-white">
                                <span class="text-[11px] text-slate-400 font-medium">Status Komersial</span>
                                <div class="text-xs font-bold text-slate-800 mt-1">
                                    {{ $detailData['is_sellable'] ? 'Bernilai Jual' : 'Non-Komersial / Residu' }}
                                </div>
                            </div>
                            <div class="p-3 rounded-lg border border-slate-100 bg-white">
                                <span class="text-[11px] text-slate-400 font-medium">Harga Default</span>
                                <div class="text-xs font-bold font-mono text-slate-800 mt-1">
                                    {{ $detailData['is_sellable'] ? 'Rp ' . number_format($detailData['default_price_per_kg'], 0, ',', '.') . '/kg' : '-' }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 border-t border-slate-100 pt-3">
                            <div class="flex items-center justify-between text-xs py-1">
                                <span class="text-slate-500">Total Akumulasi Timbang:</span>
                                <span class="font-mono font-bold text-slate-800">{{ number_format($detailData['total_weighed_kg'], 1, ',', '.') }} kg</span>
                            </div>
                            <div class="flex items-center justify-between text-xs py-1">
                                <span class="text-slate-500">Total Telah Terjual:</span>
                                <span class="font-mono font-bold text-sky-600">{{ number_format($detailData['total_sold_kg'], 1, ',', '.') }} kg</span>
                            </div>
                            <div class="flex items-center justify-between text-xs py-1.5 border-t border-dashed border-slate-200">
                                <span class="font-semibold text-slate-800">Sisa Stok Fisik Tersedia:</span>
                                <span class="font-mono font-bold text-sm {{ $detailData['available_stock_kg'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                    {{ number_format($detailData['available_stock_kg'], 1, ',', '.') }} kg
                                </span>
                            </div>
                            @if ($detailData['is_sellable'])
                                <div class="flex items-center justify-between text-xs py-1.5 bg-emerald-50/70 p-2.5 rounded-lg border border-emerald-100">
                                    <span class="font-semibold text-emerald-800">Estimasi Potensi Nilai Stok:</span>
                                    <span class="font-mono font-bold text-emerald-800">
                                        Rp {{ number_format($detailData['potential_revenue'], 0, ',', '.') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="py-8 text-center text-slate-400 text-xs">Memuat rincian stok...</div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button @click="detailModal = false; $wire.closeDetailModal()" type="button" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
