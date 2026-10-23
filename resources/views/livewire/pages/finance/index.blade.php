<div x-data="{ 
    balanceModal: @entangle('showBalanceModal'), 
    detailModal: {{ !empty($viewKeuanganId) ? 'true' : 'false' }},
    showEditModal: @entangle('showEditModal'),
    deleteModal: @entangle('showDeleteModal') 
}" 
@close-modal.window="balanceModal = false; detailModal = false; showEditModal = false; deleteModal = false"
@open-edit-finance-modal.window="showEditModal = true"
class="space-y-6">

    <!-- Top Header -->
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Buku Kas & Buku Besar Keuangan</h2>
                <p class="text-xs text-slate-500 mt-0.5">Sistem pembukuan ganda mutasi kas TPS & neraca saldo harian kampus.</p>
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
                    @endif

                    @if ($canManageBalance)
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
                                            <div class="flex items-center justify-center gap-1">
                                            <button @click="detailModal = true; $wire.viewTransaction({{ $entry->id }})" 
                                                    type="button" 
                                                    title="Lihat Rincian Jurnal"
                                                    class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                </button>
                                                @if ($canManageBalance)
                                                    <button @click="showEditModal = true; $wire.openEditModal({{ $entry->id }})" 
                                                            type="button" 
                                                            title="Edit Transaksi Kas"
                                                            class="p-1.5 text-slate-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition cursor-pointer">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button @click="deleteModal = true; $wire.confirmDelete({{ $entry->id }})" 
                                                            type="button" 
                                                            title="Hapus Transaksi Kas"
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

            <!-- Modal Edit Transaksi Kas (Teleported to Body for 100% Full Viewport Backdrop) -->
            <template x-teleport="body">
                <div x-show="showEditModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">
                                    Edit Transaksi Kas
                                    @if ($editTransactionId)
                                        <span class="text-xs font-mono font-normal text-slate-500">#TRX-{{ str_pad($editTransactionId, 5, '0', STR_PAD_LEFT) }}</span>
                                    @endif
                                </h3>
                                <p class="text-xs text-slate-500">Perbarui mutasi kas dan sinkronkan Buku Besar</p>
                            </div>
                            <button @click="showEditModal = false; $wire.closeEditModal()" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        @if ($editBindingInfo && !empty($editBindingInfo['is_bound']))
                            <div class="p-6 space-y-4">
                                <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
                                    <div class="flex items-center gap-2 text-amber-800 font-bold text-xs mb-1">
                                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <span>Transaksi Terikat Modul {{ $editBindingInfo['features'][0] ?? 'Lain' }}</span>
                                    </div>
                                    <p class="text-xs text-amber-700 leading-relaxed">
                                        Transaksi kas ini otomatis tercatat dari modul <strong>{{ $editBindingInfo['features'][0] ?? 'terkait' }}</strong>. Untuk menjaga konsistensi data operasional dan stok, silakan ubah data melalui modul asalnya.
                                    </p>
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                    <button @click="showEditModal = false; $wire.closeEditModal()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                        Tutup
                                    </button>
                                    @if (!empty($editBindingInfo['primary_url']))
                                        <a href="{{ $editBindingInfo['primary_url'] }}" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 cursor-pointer">
                                            {{ $editBindingInfo['primary_label'] ?? 'Buka Modul Terkait' }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @else
                            <form wire:submit="updateTransaction" class="p-6 space-y-4">
                                <!-- Unit Kampus -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Unit Kampus <span class="text-rose-500">*</span></label>
                                    @if ($isSuperAdmin)
                                        <select wire:model="editCampusId" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                            @foreach ($campuses as $campus)
                                                <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="text-xs font-semibold text-slate-800 bg-slate-100 border border-slate-200 px-3 py-2 rounded-lg">
                                            {{ auth()->user()->campus?->name ?? 'Kampus Umum' }}
                                        </div>
                                    @endif
                                    @error('editCampusId') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <!-- Tanggal Transaksi -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Transaksi <span class="text-rose-500">*</span></label>
                                    <input type="date" wire:model="editDate" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                    @error('editDate') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <!-- Nominal Kas (Rp) -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Nominal (Rp) <span class="text-rose-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-slate-400">Rp</span>
                                        <input type="number" step="100" min="0" wire:model="editAmount" placeholder="Contoh: 500000" class="w-full text-xs font-mono font-bold border-slate-200 rounded-lg pl-9 pr-3 py-2 bg-slate-50 text-slate-900 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                    </div>
                                    @error('editAmount') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <!-- Keterangan -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan Transaksi</label>
                                    <input type="text" wire:model="editDescription" placeholder="Uraian mutasi kas" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                    @error('editDescription') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>

                                <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
                                    <button @click="showEditModal = false; $wire.closeEditModal()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                        Batal
                                    </button>
                                    <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 cursor-pointer disabled:opacity-50">
                                        <span wire:loading.remove wire:target="updateTransaction">Simpan Perubahan</span>
                                        <span wire:loading wire:target="updateTransaction">Menyimpan...</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </template>

            <!-- Modal Konfirmasi Hapus Transaksi Kas (Teleported to Body for 100% Full Viewport Backdrop) -->
            <template x-teleport="body">
                <div x-show="deleteModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md p-6 text-center">
                        @if ($deleteBindingInfo && !empty($deleteBindingInfo['is_bound']))
                            <div class="w-12 h-12 rounded-full bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-900">Data Tidak Dapat Dihapus</h3>
                            <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">
                                Transaksi kas ini tidak dapat dihapus langsung dari Buku Kas karena masih terikat dengan data pada fitur lain:
                            </p>
                            <div class="mt-3.5 p-3 bg-amber-50/80 border border-amber-200 rounded-xl text-left">
                                <p class="text-[11px] font-semibold text-amber-900 mb-2">Terikat dengan fitur:</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($deleteBindingInfo['features'] ?? ['Modul Terkait'] as $feature)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-100 text-amber-900 border border-amber-300/60">
                                            {{ $feature }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex items-center justify-center gap-3 mt-6">
                                <button type="button" @click="deleteModal = false; $wire.cancelDelete()" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-300 text-xs font-semibold text-slate-800 transition text-center cursor-pointer">
                                    Tutup
                                </button>
                                @if (!empty($deleteBindingInfo['primary_url']))
                                    <a href="{{ $deleteBindingInfo['primary_url'] }}" 
                                       class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm shadow-emerald-600/20 transition active:scale-95 text-center flex items-center justify-center cursor-pointer"
                                       style="background-color: #059669; color: #ffffff;">
                                        <span>{{ $deleteBindingInfo['primary_label'] ?? 'Buka Modul' }}</span>
                                    </a>
                                @endif
                            </div>
                        @else
                            <div class="w-12 h-12 rounded-full bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>
                            <h3 class="text-base font-bold text-slate-900">Hapus Transaksi Kas</h3>
                            <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                                Apakah Anda yakin ingin menghapus transaksi kas ini? Saldo Buku Besar akan otomatis dihitung ulang.
                            </p>
                            @if ($deleteTransactionDesc)
                                <div class="mt-3 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700">
                                    {{ $deleteTransactionDesc }}
                                </div>
                            @endif
                            <div class="flex items-center justify-center gap-3 mt-6">
                                <button type="button" @click="deleteModal = false; $wire.cancelDelete()" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-300 text-xs font-semibold text-slate-700 transition cursor-pointer">
                                    Batal
                                </button>
                                <button type="button" wire:click="executeDelete" wire:loading.attr="disabled" class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition active:scale-95 cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="executeDelete">Ya, Hapus Data</span>
                                    <span wire:loading wire:target="executeDelete">Menghapus...</span>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

