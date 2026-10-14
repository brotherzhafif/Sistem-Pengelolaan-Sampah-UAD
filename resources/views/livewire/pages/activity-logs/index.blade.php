<div x-data="{ 
    detailModal: false, 
    activeLog: null 
}" 
@close-modal.window="detailModal = false"
@keydown.escape.window="detailModal = false">

    <!-- Topbar Header -->
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Log Aktivitas Sistem</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 uppercase tracking-wider">
                        Audit Trail
                    </span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    @if($isSuperAdmin)
                        Rekam jejak seluruh aksi penambahan, perubahan, dan penghapusan data di semua unit kampus UAD.
                    @else
                        Rekam jejak aktivitas operasional khusus di lingkungan unit {{ $userCampus?->name ?? 'kampus Anda' }}.
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-lg shadow-2xs font-medium">
                    {{ $isSuperAdmin ? 'Cakupan: Seluruh Kampus' : 'Cakupan: ' . ($userCampus?->name ?? 'Kampus Terdaftar') }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- 1. KPI Metric Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Log -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-slate-400"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Aktivitas</span>
                        <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalLogs, 0, ',', '.') }}</span>
                        <span class="text-xs text-slate-400">catatan</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">Total riwayat audit terdata</p>
                </div>

                <!-- Create Actions -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Aksi Penambahan</span>
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalCreate, 0, ',', '.') }}</span>
                        <span class="text-xs text-slate-400">entri baru</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">Transaksi / data yang dibuat</p>
                </div>

                <!-- Update Actions -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Aksi Perubahan</span>
                        <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalUpdate, 0, ',', '.') }}</span>
                        <span class="text-xs text-slate-400">perubahan</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">Pembaruan data & konfigurasi</p>
                </div>

                <!-- Delete Actions -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-rose-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Aksi Penghapusan</span>
                        <div class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalDelete, 0, ',', '.') }}</span>
                        <span class="text-xs text-slate-400">penghapusan</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">Data yang dibatalkan / dihapus</p>
                </div>
            </div>

            <!-- 2. Advanced Filter Toolbar -->
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                    <!-- Search Input -->
                    <div class="lg:col-span-2">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Cari Keterangan / Pengguna</label>
                        <div class="relative">
                            <input wire:model.live.debounce.300ms="search" 
                                   type="text" 
                                   placeholder="Cari aksi, user, atau IP..." 
                                   class="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 placeholder-slate-400">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Modul Filter -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Modul</label>
                        <select wire:model.live="filterModule" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                            <option value="">Semua Modul</option>
                            <option value="weighing">Penimbangan</option>
                            <option value="sales">Penjualan</option>
                            <option value="pickups">Pengangkutan</option>
                            <option value="expenses">Pengeluaran</option>
                            <option value="finance">Keuangan / Kas</option>
                            <option value="master_data">Master Data</option>
                            <option value="users">Pengguna</option>
                        </select>
                    </div>

                    <!-- Action Filter -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Tipe Aksi</label>
                        <select wire:model.live="filterAction" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                            <option value="">Semua Aksi</option>
                            <option value="create">Penambahan (Create)</option>
                            <option value="update">Pembaruan (Update)</option>
                            <option value="delete">Penghapusan (Delete)</option>
                        </select>
                    </div>

                    <!-- Campus Filter (Only for Super Admin) -->
                    @if($isSuperAdmin)
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Unit Kampus</label>
                            <select wire:model.live="filterCampusId" class="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                                <option value="">Semua Kampus</option>
                                @foreach($campuses as $campus)
                                    <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Unit Kampus</label>
                            <input type="text" value="{{ $userCampus?->name ?? 'Kampus Saya' }}" disabled class="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg bg-slate-50 text-slate-600 font-medium cursor-not-allowed">
                        </div>
                    @endif

                    <!-- Reset Filter Button -->
                    <div class="flex items-end">
                        <button wire:click="resetFilters" 
                                type="button" 
                                class="w-full py-2 px-3 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Reset Filter</span>
                        </button>
                    </div>
                </div>

                <!-- Date Range Row -->
                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100 text-xs text-slate-600">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Rentang Waktu:</span>
                    <div class="flex items-center gap-2">
                        <input wire:model.live="filterDateFrom" type="date" class="py-1 px-2.5 text-xs border border-slate-200 rounded-lg focus:ring-1 focus:ring-emerald-500 bg-white">
                        <span class="text-slate-400">s/d</span>
                        <input wire:model.live="filterDateTo" type="date" class="py-1 px-2.5 text-xs border border-slate-200 rounded-lg focus:ring-1 focus:ring-emerald-500 bg-white">
                    </div>
                </div>
            </div>

            <!-- 3. Activity Logs Table Card -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs table-auto">
                        <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4 w-40">Waktu (WIB)</th>
                                <th class="py-3 px-4 w-52">Pengguna & Kampus</th>
                                <th class="py-3 px-4 w-36">Modul & Aksi</th>
                                <th class="py-3 px-4">Keterangan Aktivitas</th>
                                <th class="py-3 px-4 text-center w-28">Detail Payload</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @forelse($logs as $log)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <!-- Timestamp -->
                                    <td class="py-3 px-4 align-top whitespace-nowrap">
                                        <div class="font-mono font-semibold text-slate-900">
                                            {{ $log->created_at->format('d/m/Y H:i:s') }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            {{ $log->created_at->diffForHumans() }}
                                        </div>
                                    </td>

                                    <!-- User & Campus -->
                                    <td class="py-3 px-4 align-top">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-[11px] shrink-0">
                                                {{ substr($log->user?->name ?? 'S', 0, 1) }}
                                            </div>
                                            <div class="min-w-0">
                                                <span class="block font-semibold text-slate-900 truncate">
                                                    {{ $log->user?->name ?? 'Sistem / Anonim' }}
                                                </span>
                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                    @if($log->campus)
                                                        <span class="px-1.5 py-0.2 rounded text-[10px] bg-slate-100 text-slate-600 border border-slate-200 font-medium">
                                                            {{ $log->campus->name }}
                                                        </span>
                                                    @else
                                                        <span class="px-1.5 py-0.2 rounded text-[10px] bg-indigo-50 text-indigo-600 border border-indigo-200 font-medium">
                                                            Global Pusat
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Module & Action Badges -->
                                    <td class="py-3 px-4 align-top">
                                        <div class="space-y-1">
                                            <!-- Module Badge -->
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                                @switch($log->module)
                                                    @case('weighing') bg-sky-50 text-sky-700 border border-sky-200 @break
                                                    @case('sales') bg-emerald-50 text-emerald-700 border border-emerald-200 @break
                                                    @case('pickups') bg-amber-50 text-amber-700 border border-amber-200 @break
                                                    @case('expenses') bg-rose-50 text-rose-700 border border-rose-200 @break
                                                    @case('finance') bg-indigo-50 text-indigo-700 border border-indigo-200 @break
                                                    @case('master_data') bg-purple-50 text-purple-700 border border-purple-200 @break
                                                    @case('users') bg-teal-50 text-teal-700 border border-teal-200 @break
                                                    @default bg-slate-100 text-slate-700 border border-slate-200
                                                @endswitch">
                                                {{ str_replace('_', ' ', $log->module) }}
                                            </span>

                                            <!-- Action Badge -->
                                            <div>
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                                    @switch($log->action)
                                                        @case('create') bg-emerald-100 text-emerald-800 @break
                                                        @case('update') bg-amber-100 text-amber-800 @break
                                                        @case('delete') bg-rose-100 text-rose-800 @break
                                                        @default bg-slate-100 text-slate-700
                                                    @endswitch">
                                                    @if($log->action === 'create')
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    @elseif($log->action === 'update')
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                    @elseif($log->action === 'delete')
                                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                    @endif
                                                    {{ $log->action }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Description -->
                                    <td class="py-3 px-4 align-top">
                                        <p class="font-medium text-slate-800 leading-snug">
                                            {{ $log->description }}
                                        </p>
                                        @if($log->ip_address)
                                            <span class="text-[10px] font-mono text-slate-400 mt-1 inline-block">
                                                IP: {{ $log->ip_address }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Payload Detail Button -->
                                    <td class="py-3 px-4 align-top text-center whitespace-nowrap">
                                        @if(!empty($log->properties))
                                            <button @click="activeLog = {{ json_encode([
                                                'id' => $log->id,
                                                'module' => $log->module,
                                                'action' => $log->action,
                                                'description' => $log->description,
                                                'user' => $log->user?->name ?? 'Anonim',
                                                'campus' => $log->campus?->name ?? 'Pusat / Global',
                                                'time' => $log->created_at->format('d F Y, H:i:s') . ' WIB',
                                                'ip' => $log->ip_address ?? '-',
                                                'user_agent' => $log->user_agent ?? '-',
                                                'properties' => $log->properties,
                                            ]) }}; detailModal = true"
                                                    type="button" 
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 border border-slate-200 hover:border-emerald-300 font-semibold text-[11px] transition cursor-pointer">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <span>Payload</span>
                                            </button>
                                        @else
                                            <span class="text-slate-300 text-[11px]">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400">
                                        <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <p class="font-medium text-slate-600">Tidak ada log aktivitas yang cocok.</p>
                                        <span class="text-[11px] text-slate-400">Coba ubah filter pencarian atau tanggal di atas.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                @if($logs->hasPages())
                    <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>

    <!-- 4. Payload Detail Modal (AlpineJS) -->
    <div x-show="detailModal" 
         x-cloak 
         class="fixed inset-0 z-50 overflow-y-auto" 
         aria-labelledby="modal-title" 
         role="dialog" 
         aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="detailModal" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" 
                 @click="detailModal = false"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="detailModal" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-200">
                
                <!-- Modal Header -->
                <div class="px-5 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Rincian Data Log Audit</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Payload teknis yang dicatat saat transaksi dieksekusi</p>
                    </div>
                    <button @click="detailModal = false" type="button" class="text-slate-400 hover:text-slate-600 transition p-1 rounded-lg hover:bg-slate-200/50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4">
                    <!-- Meta Grid -->
                    <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50/80 p-3.5 rounded-xl border border-slate-100">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pengguna:</span>
                            <span class="block font-semibold text-slate-800" x-text="activeLog?.user"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Unit Kampus:</span>
                            <span class="block font-semibold text-slate-800" x-text="activeLog?.campus"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Waktu Eksekusi:</span>
                            <span class="block font-mono text-slate-800" x-text="activeLog?.time"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">IP Address:</span>
                            <span class="block font-mono text-slate-800" x-text="activeLog?.ip"></span>
                        </div>
                    </div>

                    <!-- Deskripsi -->
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Deskripsi Aktivitas:</span>
                        <div class="p-2.5 rounded-lg bg-emerald-50/50 border border-emerald-100 text-xs font-medium text-emerald-900" x-text="activeLog?.description"></div>
                    </div>

                    <!-- JSON Properties Payload -->
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Data Payload (JSON Format):</span>
                        <pre class="bg-slate-900 text-emerald-400 p-4 rounded-xl text-xs font-mono overflow-x-auto max-h-60 leading-relaxed border border-slate-800" x-text="JSON.stringify(activeLog?.properties, null, 2)"></pre>
                    </div>

                    <!-- User Agent -->
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">User Agent Client:</span>
                        <span class="text-[11px] text-slate-500 font-mono break-all" x-text="activeLog?.user_agent"></span>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button @click="detailModal = false" type="button" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-xl transition cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
