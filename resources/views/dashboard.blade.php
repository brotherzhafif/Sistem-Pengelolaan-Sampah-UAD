<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">
                    Dashboard Ringkasan
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Selamat datang kembali, <span class="font-semibold text-slate-700">{{ Auth::user()->name }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    {{ Auth::user()->campus?->name ?? 'Pusat / Seluruh Kampus' }}
                </span>
                @if(Auth::user()->roles->isNotEmpty())
                    <span class="inline-flex items-center px-2.5 py-1.5 rounded-lg bg-slate-100 border border-slate-200 text-[11px] font-semibold text-slate-600 uppercase tracking-wide">
                        {{ str_replace('_', ' ', Auth::user()->roles->first()->name) }}
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Metric Cards (Clean Dribbble style matching ref tokens) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Sage Accent -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Timbangan Hari Ini</span>
                        <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">0.00</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Belum ada timbangan masuk</p>
                </div>

                <!-- Card 2: Sky Accent -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Stok Terpilah</span>
                        <div class="w-7 h-7 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">0.00</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Siap disalurkan ke pengepul</p>
                </div>

                <!-- Card 3: Coral/Amber Accent -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Stok Residu</span>
                        <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">0.00</span>
                        <span class="text-xs text-slate-500 font-medium">kg</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Menunggu pengangkutan TPA</p>
                </div>

                <!-- Card 4: Sage Accent -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-600"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Saldo Bank Sampah</span>
                        <div class="w-7 h-7 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1">
                        <span class="text-xs font-semibold text-slate-400">Rp</span>
                        <span class="font-mono text-2xl font-bold text-slate-900">0</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Kas operasional sirkular</p>
                </div>
            </div>

            <!-- Quick Action Module Cards -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                <div class="mb-4">
                    <h3 class="font-bold text-sm text-slate-900">Modul Operasional Pengelolaan Sampah</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Akses cepat sesuai peran akun Anda di kampus</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-4 rounded-lg border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-emerald-300 transition duration-150">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">M2</span>
                            <span class="font-semibold text-xs text-slate-800">Penimbangan Sampah</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">Pencatatan harian sampah masuk per titik sumber (Gedung, Kantin, TPS).</p>
                    </div>

                    <div class="p-4 rounded-lg border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-sky-300 transition duration-150">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800">M4</span>
                            <span class="font-semibold text-xs text-slate-800">Bank Sampah & Penjualan</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">Catat penjualan anorganik terpilah ke pengepul & kas sirkular.</p>
                    </div>

                    <div class="p-4 rounded-lg border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-amber-300 transition duration-150">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">M6</span>
                            <span class="font-semibold text-xs text-slate-800">Survei Perilaku (KAP)</span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">Kuesioner evaluasi Knowledge, Attitude & Practice civitas UAD.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
