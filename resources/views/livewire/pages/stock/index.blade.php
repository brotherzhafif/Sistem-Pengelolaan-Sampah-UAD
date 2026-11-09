<div class="space-y-4 max-w-7xl mx-auto pb-10">
    <!-- Header Halaman -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </span>
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Rekapitulasi Stok Sampah</h1>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Monitoring ketersediaan stok fisik sampah terpilah, riwayat penimbangan, realisasi penjualan, dan sisa residu TPS3R.
            </p>
        </div>

        @if ($isSuperAdmin)
            <div class="flex items-center gap-2">
                <label for="campus-selector" class="text-xs font-semibold text-slate-600 shrink-0">Filter Unit:</label>
                <select id="campus-selector"
                        wire:model.live="selectedCampusId"
                        class="text-xs font-medium border-slate-300 rounded-xl px-3 py-2 bg-slate-50 text-slate-800 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition">
                    <option value="">Semua Kampus (Konsolidasi Pusat)</option>
                    @foreach ($campuses as $campus)
                        <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <!-- 4 Ringkasan Metrik Konsolidasi -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- 1. Total Ditimbang -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 text-xs font-medium mb-1">
                <span>Total Ditimbang</span>
                <span class="p-1 rounded bg-slate-100 text-slate-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                    </svg>
                </span>
            </div>
            <div class="text-xl font-bold font-mono text-slate-900">
                {{ number_format($stockSummary['total_weighed_kg'] ?? 0, 1, ',', '.') }}
                <span class="text-xs font-sans font-medium text-slate-400">kg</span>
            </div>
            <div class="text-[11px] text-slate-500 mt-1">Akumulasi seluruh sampah masuk TPS</div>
        </div>

        <!-- 2. Terjual -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 text-xs font-medium mb-1">
                <span>Terjual ke Mitra</span>
                <span class="p-1 rounded bg-emerald-50 text-emerald-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <div class="text-xl font-bold font-mono text-emerald-700">
                {{ number_format($stockSummary['total_sold_kg'] ?? 0, 1, ',', '.') }}
                <span class="text-xs font-sans font-medium text-slate-400">kg</span>
            </div>
            <div class="text-[11px] text-emerald-600 font-medium mt-1">
                Nilai: Rp {{ number_format($stockSummary['total_sales_revenue'] ?? 0, 0, ',', '.') }}
            </div>
        </div>

        <!-- 3. Sisa Stok Siap Jual -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 text-xs font-medium mb-1">
                <span>Stok Siap Jual</span>
                <span class="p-1 rounded bg-sky-50 text-sky-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                </span>
            </div>
            <div class="text-xl font-bold font-mono text-sky-700">
                {{ number_format($stockSummary['sellable_stock_kg'] ?? 0, 1, ',', '.') }}
                <span class="text-xs font-sans font-medium text-slate-400">kg</span>
            </div>
            <div class="text-[11px] text-sky-600 font-medium mt-1">
                Potensi: Rp {{ number_format($stockSummary['potential_revenue'] ?? 0, 0, ',', '.') }}
            </div>
        </div>

        <!-- 4. Residu / Terangkut -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/90 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 text-xs font-medium mb-1">
                <span>Residu & Terangkut</span>
                <span class="p-1 rounded bg-amber-50 text-amber-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </span>
            </div>
            <div class="text-xl font-bold font-mono text-amber-700">
                {{ number_format($stockSummary['total_picked_up_kg'] ?? 0, 1, ',', '.') }}
                <span class="text-xs font-sans font-medium text-slate-400">kg</span>
            </div>
            <div class="text-[11px] text-slate-500 mt-1">
                Sisa residu di TPS: <span class="font-mono font-semibold">{{ number_format($stockSummary['residual_stock_kg'] ?? 0, 1, ',', '.') }} kg</span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white rounded-xl border border-slate-200 p-3 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Search -->
            <div class="relative w-full sm:w-64">
                <input type="text"
                       wire:model.live.debounce.250ms="search"
                       placeholder="Cari jenis sampah..."
                       class="w-full text-xs border-slate-300 rounded-lg pl-8 pr-3 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 placeholder:text-slate-400">
                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <!-- Filter Kategori -->
            <select wire:model.live="filterCategory"
                    class="text-xs border-slate-300 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-700 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="all">Semua Kategori</option>
                <option value="Organik">Organik</option>
                <option value="Anorganik">Anorganik</option>
                <option value="Residu">Residu</option>
                <option value="B3">B3</option>
            </select>

            <!-- Filter Status Komersial -->
            <select wire:model.live="filterSellable"
                    class="text-xs border-slate-300 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-700 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="all">Semua Status</option>
                <option value="sellable">Bernilai Jual</option>
                <option value="non_sellable">Non-Komersial / Residu</option>
            </select>

            @if (!empty($search) || $filterCategory !== 'all' || $filterSellable !== 'all')
                <button type="button" 
                        wire:click="resetFilters" 
                        class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1 rounded transition hover:bg-rose-50">
                    Reset Filter
                </button>
            @endif
        </div>

        <div class="text-xs text-slate-500 font-medium">
            Menampilkan <span class="font-bold text-slate-800">{{ $items->count() }}</span> jenis sampah
        </div>
    </div>

    <!-- Tabel Rekapitulasi Rincian Stok Per Jenis Sampah (Zero Horizontal Scroll) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs table-fixed border-collapse">
                <thead class="bg-slate-50/80 text-[10px] font-bold text-slate-600 uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-3 w-[26%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('name')">
                            <div class="flex items-center gap-1 truncate">
                                <span>Jenis Sampah</span>
                                @if ($sortBy === 'name')
                                    <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="py-3 px-2 w-[14%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('category')">
                            <div class="flex items-center gap-1">
                                <span>Kategori</span>
                                @if ($sortBy === 'category')
                                    <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="py-3 px-2 w-[12%] text-center">Status</th>
                        <th class="py-3 px-2 text-right w-[12%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('weighed')">
                            <div class="flex items-center justify-end gap-1">
                                <span>Timbang (kg)</span>
                                @if ($sortBy === 'weighed')
                                    <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="py-3 px-2 text-right w-[12%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('sold')">
                            <div class="flex items-center justify-end gap-1">
                                <span>Terjual (kg)</span>
                                @if ($sortBy === 'sold')
                                    <span class="text-emerald-600 font-bold">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th class="py-3 px-3 text-right w-[12%] cursor-pointer hover:bg-slate-100 transition" wire:click="setSort('stock')">
                            <div class="flex items-center justify-end gap-1">
                                <span>Sisa Stok (kg)</span>
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($items as $item)
                        <tr class="hover:bg-slate-50/70 transition">
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
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700" title="Harga default: Rp {{ number_format($item['default_price_per_kg'], 0, ',', '.') }}/kg">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <span>Jual</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        <span>Residu</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Total Timbang -->
                            <td class="py-2.5 px-2 text-right font-mono text-slate-700">
                                {{ number_format($item['total_weighed_kg'], 1, ',', '.') }}
                            </td>

                            <!-- Terjual -->
                            <td class="py-2.5 px-2 text-right font-mono text-slate-700">
                                {{ number_format($item['total_sold_kg'], 1, ',', '.') }}
                            </td>

                            <!-- Sisa Stok -->
                            <td class="py-2.5 px-3 text-right font-mono font-bold {{ $item['available_stock_kg'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                {{ number_format($item['available_stock_kg'], 1, ',', '.') }}
                            </td>

                            <!-- Potensi Nilai -->
                            <td class="py-2.5 px-3 text-right font-mono text-slate-800 font-medium">
                                @if ($item['is_sellable'])
                                    Rp {{ number_format($item['potential_revenue'], 0, ',', '.') }}
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                                <svg class="w-8 h-8 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <span>Tidak ada data stok sampah yang cocok dengan filter.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
