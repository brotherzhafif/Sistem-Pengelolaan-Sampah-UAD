<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>PS2 UAD - Sistem Pengelolaan Sampah Kampus</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts: DM Sans & JetBrains Mono -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

        <!-- Styles & Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans bg-base-100 text-base-content antialiased selection:bg-primary selection:text-white">
        <!-- Background Glows -->
        <div class="fixed top-0 left-1/4 w-96 h-96 bg-primary/10 rounded-full blur-3xl pointer-events-none -z-10"></div>
        <div class="fixed bottom-0 right-1/4 w-96 h-96 bg-secondary/10 rounded-full blur-3xl pointer-events-none -z-10"></div>

        <!-- Navbar -->
        <header class="sticky top-0 z-50 backdrop-blur-md bg-base-100/80 border-b border-base-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <a href="/" class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary to-emerald-400 flex items-center justify-center text-white shadow-md shadow-primary/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xl font-black tracking-tight text-neutral">PS2 <span class="text-primary">UAD</span></span>
                            <span class="hidden sm:inline-block ms-2 badge badge-ghost badge-sm font-semibold text-xs text-primary">Eco Campus</span>
                        </div>
                    </a>

                    @if (Route::has('login'))
                        <livewire:welcome.navigation />
                    @endif
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative pt-12 pb-20 md:pt-20 md:pb-32 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto">
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-neutral leading-tight">
                        Wujudkan Kampus Hijau & <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-emerald-600">Zero-Waste</span> Berkelanjutan
                    </h1>
                    
                    <p class="mt-6 text-base sm:text-lg text-base-content/70 leading-relaxed max-w-2xl mx-auto">
                        Sistem digital terpadu untuk pencatatan penimbangan sampah, monitoring logistik armada, integrasi bank sampah, dan survei KAP (Knowledge, Attitude, Practice) di seluruh Kampus UAD.
                    </p>

                    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg text-white font-bold shadow-xl shadow-primary/25 hover:shadow-primary/40 px-8">
                                Buka Dashboard
                                <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary btn-lg text-white font-bold shadow-xl shadow-primary/25 hover:shadow-primary/40 px-8">
                                Masuk ke Sistem
                            </a>
                            <a href="{{ route('kap.survey') }}" class="btn btn-outline btn-lg font-bold px-8">
                                Isi Survei KAP
                            </a>
                        @endauth
                    </div>
                    @guest
                        <p class="mt-3 text-xs text-base-content/60">
                            Akun pengelola diterbitkan oleh Admin Unit Kampus atau Admin Pusat.
                        </p>
                    @endguest
                </div>

                <!-- Stats Highlight (DaisyUI stats) -->
                <div class="mt-16 max-w-4xl mx-auto">
                    <div class="stats stats-vertical lg:stats-horizontal shadow-xl bg-base-100 border border-base-200 w-full rounded-2xl">
                        <div class="stat place-items-center">
                            <div class="stat-title text-xs uppercase font-bold text-base-content/60">Cakupan Lokasi</div>
                            <div class="stat-value text-primary font-black">6 Kampus</div>
                            <div class="stat-desc font-medium text-base-content/60">Kampus 1 hingga Kampus 6 UAD</div>
                        </div>
                        
                        <div class="stat place-items-center">
                            <div class="stat-title text-xs uppercase font-bold text-base-content/60">Alur Sirkular</div>
                            <div class="stat-value text-secondary font-black">Real-time</div>
                            <div class="stat-desc font-medium text-base-content/60">Timbang, Angkut & Jual ke Pengepul</div>
                        </div>
                        
                        <div class="stat place-items-center">
                            <div class="stat-title text-xs uppercase font-bold text-base-content/60">Riset Perilaku</div>
                            <div class="stat-value text-accent font-black">KAP Survey</div>
                            <div class="stat-desc font-medium text-base-content/60">Evaluasi Knowledge, Attitude & Practice</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Public Infographic Report: Sampah Terkelola vs Tidak Terkelola -->
        <section id="infografik-sampah" class="py-14 bg-base-200/70 border-y border-base-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- Header & Scope Filter -->
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                    <div class="max-w-2xl">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-bold uppercase tracking-wider text-[#047857]">
                            Laporan Transparansi Publik
                        </span>
                        <h2 class="mt-3 text-2xl sm:text-3xl font-black text-[#111a2e] tracking-tight">
                            Infografik Sampah Terkelola vs Tidak Terkelola
                        </h2>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                            Pemantauan terbuka aliran akhir sampah kampus. <strong class="text-[#047857]">Terkelola</strong> mencakup sampah anorganik yang dijual ke mitra dan sampah organik yang diolah mandiri. <strong class="text-[#c2410c]">Tidak Terkelola</strong> adalah residu yang diangkut menuju TPA.
                        </p>
                    </div>

                    <div class="text-xs text-slate-600 lg:text-right shrink-0">
                        <div class="font-semibold text-[#111a2e]">{{ $activeCampusName }}</div>
                        <div>
                            @if (!empty($infographic['last_updated_label']))
                                Pembaruan data: <span class="font-mono">{{ $infographic['last_updated_label'] }}</span>
                            @else
                                Data ringkasan langsung dari catatan TPS kampus
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Campus Filter Pills -->
                <div class="flex flex-wrap items-center gap-2" role="navigation" aria-label="Filter unit kampus untuk infografik">
                    <a href="{{ route('welcome') }}#infografik-sampah"
                       class="min-h-[40px] px-3.5 py-2 rounded-xl text-xs font-semibold border transition inline-flex items-center focus:outline-none focus:ring-2 focus:ring-primary {{ $activeCampusId === null ? 'bg-[#111a2e] text-white border-[#111a2e] shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                        Semua Kampus
                    </a>
                    @foreach ($campuses as $campus)
                        <a href="{{ route('welcome', ['campus_id' => $campus->id]) }}#infografik-sampah"
                           class="min-h-[40px] px-3.5 py-2 rounded-xl text-xs font-semibold border transition inline-flex items-center focus:outline-none focus:ring-2 focus:ring-primary {{ $activeCampusId === $campus->id ? 'bg-[#111a2e] text-white border-[#111a2e] shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                            {{ $campus->name }}
                        </a>
                    @endforeach
                </div>

                @php
                    $hasHandledData = ($infographic['total_handled_kg'] ?? 0) > 0;
                    $radius = 82;
                    $circumference = 2 * M_PI * $radius;
                    $soldPct = $infographic['sold_share_pct'] ?? 0;
                    $processedPct = $infographic['processed_share_pct'] ?? 0;
                    $unmanagedPct = $infographic['unmanaged_pct'] ?? 0;
                    $lenSold = ($soldPct / 100) * $circumference;
                    $lenProcessed = ($processedPct / 100) * $circumference;
                    $lenUnmanaged = ($unmanagedPct / 100) * $circumference;
                @endphp

                <!-- Main Infographic Grid (12 Columns Asymmetric) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                    <!-- Left: Visual Comparison & Proportions (7 cols) -->
                    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between gap-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-4">
                            <div>
                                <h3 class="text-base font-bold text-[#111a2e]">Proporsi Penanganan Akhir Sampah</h3>
                                <p class="text-xs text-slate-600 mt-0.5">Perbandingan berat keluar TPS berdasarkan kategori pengelolaan</p>
                            </div>
                            <div class="text-left sm:text-right">
                                <span class="text-[11px] uppercase tracking-wider font-semibold text-slate-500 block">Total Tertangani</span>
                                <span class="font-mono text-lg font-bold text-[#111a2e]">{{ number_format($infographic['total_handled_kg'], 1, ',', '.') }} kg</span>
                            </div>
                        </div>

                        @if ($hasHandledData)
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-6 items-center">
                                <!-- SVG Donut Chart -->
                                <div class="sm:col-span-5 flex items-center justify-center">
                                    <div class="relative w-48 h-48 flex items-center justify-center">
                                        <svg viewBox="0 0 220 220" class="w-48 h-48 -rotate-90" role="img" aria-label="Grafik proporsi sampah terkelola {{ number_format($infographic['managed_pct'], 1, ',', '.') }} persen dan tidak terkelola {{ number_format($infographic['unmanaged_pct'], 1, ',', '.') }} persen">
                                            <circle cx="110" cy="110" r="{{ $radius }}" fill="none" stroke="#e2e8f0" stroke-width="24" />
                                            @if ($lenSold > 0)
                                                <circle cx="110" cy="110" r="{{ $radius }}" fill="none" stroke="#3a9d6e" stroke-width="24"
                                                        stroke-dasharray="{{ $lenSold }} {{ $circumference }}"
                                                        stroke-dashoffset="0" />
                                            @endif
                                            @if ($lenProcessed > 0)
                                                <circle cx="110" cy="110" r="{{ $radius }}" fill="none" stroke="#0d9488" stroke-width="24"
                                                        stroke-dasharray="{{ $lenProcessed }} {{ $circumference }}"
                                                        stroke-dashoffset="-{{ $lenSold }}" />
                                            @endif
                                            @if ($lenUnmanaged > 0)
                                                <circle cx="110" cy="110" r="{{ $radius }}" fill="none" stroke="#ef6b4a" stroke-width="24"
                                                        stroke-dasharray="{{ $lenUnmanaged }} {{ $circumference }}"
                                                        stroke-dashoffset="-{{ $lenSold + $lenProcessed }}" />
                                            @endif
                                        </svg>
                                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none px-4">
                                            <span class="font-mono text-2xl font-black text-[#111a2e]">{{ number_format($infographic['managed_pct'], 1, ',', '.') }}%</span>
                                            <span class="text-xs font-bold text-[#047857] uppercase tracking-wider mt-0.5">Terkelola</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Horizontal Segmented Bar + Exact Legend -->
                                <div class="sm:col-span-7 space-y-4">
                                    <div>
                                        <div class="flex items-center justify-between text-xs font-semibold mb-1.5">
                                            <span class="text-[#047857]">Terkelola: {{ number_format($infographic['managed_pct'], 1, ',', '.') }}%</span>
                                            <span class="text-[#c2410c]">Tidak Terkelola: {{ number_format($infographic['unmanaged_pct'], 1, ',', '.') }}%</span>
                                        </div>
                                        <div class="w-full h-4 rounded-full bg-slate-100 overflow-hidden flex border border-slate-200">
                                            @if ($soldPct > 0)
                                                <div style="width: {{ $soldPct }}%; background-color: #3a9d6e;" title="Dijual: {{ number_format($infographic['sold_kg'], 1, ',', '.') }} kg"></div>
                                            @endif
                                            @if ($processedPct > 0)
                                                <div style="width: {{ $processedPct }}%; background-color: #0d9488;" title="Diolah Mandiri: {{ number_format($infographic['processed_kg'], 1, ',', '.') }} kg"></div>
                                            @endif
                                            @if ($unmanagedPct > 0)
                                                <div style="width: {{ $unmanagedPct }}%; background-color: #ef6b4a;" title="Diangkut ke TPA: {{ number_format($infographic['unmanaged_kg'], 1, ',', '.') }} kg"></div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="space-y-2.5 text-xs">
                                        <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200/70">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="w-3 h-3 rounded-sm shrink-0" style="background-color: #3a9d6e;"></span>
                                                <div class="min-w-0">
                                                    <div class="font-bold text-[#111a2e]">Dijual (Anorganik Terpilah)</div>
                                                    <div class="text-[11px] text-slate-600">Disalurkan ke bank sampah dan pengepul</div>
                                                </div>
                                            </div>
                                            <div class="text-right font-mono shrink-0 whitespace-nowrap">
                                                <div class="font-bold text-[#047857]">{{ number_format($infographic['sold_kg'], 1, ',', '.') }} kg</div>
                                                <div class="text-[11px] text-slate-600">{{ number_format($soldPct, 1, ',', '.') }}%</div>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200/70">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="w-3 h-3 rounded-sm shrink-0" style="background-color: #0d9488;"></span>
                                                <div class="min-w-0">
                                                    <div class="font-bold text-[#111a2e]">Diolah Mandiri (Organik)</div>
                                                    <div class="text-[11px] text-slate-600">Diolah menjadi pupuk dan pakan</div>
                                                </div>
                                            </div>
                                            <div class="text-right font-mono shrink-0 whitespace-nowrap">
                                                <div class="font-bold text-[#047857]">{{ number_format($infographic['processed_kg'], 1, ',', '.') }} kg</div>
                                                <div class="text-[11px] text-slate-600">{{ number_format($processedPct, 1, ',', '.') }}%</div>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200/70">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="w-3 h-3 rounded-sm shrink-0" style="background-color: #ef6b4a;"></span>
                                                <div class="min-w-0">
                                                    <div class="font-bold text-[#111a2e]">Diangkut ke TPA (Tidak Terkelola)</div>
                                                    <div class="text-[11px] text-slate-600">Residu non-organik yang dibuang keluar</div>
                                                </div>
                                            </div>
                                            <div class="text-right font-mono shrink-0 whitespace-nowrap">
                                                <div class="font-bold text-[#c2410c]">{{ number_format($infographic['unmanaged_kg'], 1, ',', '.') }} kg</div>
                                                <div class="text-[11px] text-slate-600">{{ number_format($unmanagedPct, 1, ',', '.') }}%</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="py-10 px-6 rounded-xl bg-slate-50 border border-dashed border-slate-300 text-center">
                                <p class="text-sm font-bold text-[#111a2e]">Belum Ada Data Penanganan Keluar yang Tercatat</p>
                                <p class="text-xs text-slate-600 mt-1 max-w-md mx-auto leading-relaxed">
                                    Grafik persentase Sampah Terkelola vs Tidak Terkelola akan muncul otomatis setelah transaksi penjualan, pengolahan organik, atau pengangkutan residu dicatat pada {{ $activeCampusName }}.
                                </p>
                            </div>
                        @endif

                        <!-- Neutral Factual Context Strip (Non-Financial) -->
                        <div class="pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <span class="text-[11px] font-medium text-slate-600 block">Total Timbang Masuk TPS</span>
                                <span class="font-mono font-bold text-[#111a2e] text-sm">{{ number_format($infographic['total_weighed_kg'], 1, ',', '.') }} kg</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70">
                                <span class="text-[11px] font-medium text-slate-600 block">Sisa Sampah di TPS</span>
                                <span class="font-mono font-bold text-[#111a2e] text-sm">{{ number_format($infographic['remaining_stock_kg'], 1, ',', '.') }} kg</span>
                            </div>
                            <div class="p-3 rounded-xl bg-emerald-50/70 border border-emerald-200/70">
                                <span class="text-[11px] font-medium text-[#047857] block">Produk Olah Organik</span>
                                <span class="font-mono font-bold text-[#047857] text-xs">
                                    Pupuk {{ number_format($infographic['compost_kg'], 1, ',', '.') }} kg | Pakan {{ number_format($infographic['feed_kg'], 1, ',', '.') }} kg
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Two Dedicated Headline Cards (5 cols) -->
                    <div class="lg:col-span-5 flex flex-col gap-5 justify-between">
                        <!-- Card 1: Sampah Terkelola -->
                        <div class="flex-1 bg-white rounded-2xl border border-emerald-200 shadow-sm p-6 relative overflow-hidden flex flex-col justify-between">
                            <div class="absolute top-0 left-0 right-0 h-1.5" style="background-color: #3a9d6e;"></div>
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <span class="text-xs font-bold uppercase tracking-wider text-[#047857]">Sampah Terkelola</span>
                                        <p class="text-xs text-slate-600 mt-0.5">Dijual ke mitra + Diolah mandiri</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 font-mono text-sm font-black text-[#047857]">
                                        {{ number_format($infographic['managed_pct'], 1, ',', '.') }}%
                                    </span>
                                </div>

                                <div class="mt-5 flex items-baseline gap-2">
                                    <span class="font-mono text-4xl font-black text-[#111a2e]">{{ number_format($infographic['managed_kg'], 1, ',', '.') }}</span>
                                    <span class="text-sm font-semibold text-slate-600">kg</span>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100 grid grid-cols-2 gap-3 text-xs">
                                <div class="p-3 rounded-xl bg-emerald-50/60 border border-emerald-100">
                                    <span class="text-[11px] text-[#047857] font-semibold block">Dijual</span>
                                    <span class="font-mono font-bold text-[#111a2e] text-sm">{{ number_format($infographic['sold_kg'], 1, ',', '.') }} kg</span>
                                </div>
                                <div class="p-3 rounded-xl bg-teal-50/60 border border-teal-100">
                                    <span class="text-[11px] text-[#047857] font-semibold block">Diolah Sendiri</span>
                                    <span class="font-mono font-bold text-[#111a2e] text-sm">{{ number_format($infographic['processed_kg'], 1, ',', '.') }} kg</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Sampah Tidak Terkelola -->
                        <div class="flex-1 bg-white rounded-2xl border border-orange-200 shadow-sm p-6 relative overflow-hidden flex flex-col justify-between">
                            <div class="absolute top-0 left-0 right-0 h-1.5" style="background-color: #ef6b4a;"></div>
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <span class="text-xs font-bold uppercase tracking-wider text-[#c2410c]">Sampah Tidak Terkelola</span>
                                        <p class="text-xs text-slate-600 mt-0.5">Residu diangkut ke TPA</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-lg bg-orange-50 border border-orange-200 font-mono text-sm font-black text-[#c2410c]">
                                        {{ number_format($infographic['unmanaged_pct'], 1, ',', '.') }}%
                                    </span>
                                </div>

                                <div class="mt-5 flex items-baseline gap-2">
                                    <span class="font-mono text-4xl font-black text-[#111a2e]">{{ number_format($infographic['unmanaged_kg'], 1, ',', '.') }}</span>
                                    <span class="text-sm font-semibold text-slate-600">kg</span>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-slate-100 text-xs text-slate-600">
                                Mencakup residu non-organik yang tidak bernilai jual dan tidak dapat dikomposkan mandiri di TPS kampus.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Per-Campus Breakdown Strip (When viewing all campuses) -->
                @if ($activeCampusId === null && count($campusBreakdown) > 0)
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-5">
                            <div>
                                <h3 class="text-base font-bold text-[#111a2e]">Ringkasan per Unit Kampus UAD</h3>
                                <p class="text-xs text-slate-600 mt-0.5">Klik nama kampus untuk memfilter detail infografik</p>
                            </div>
                            <div class="flex items-center gap-4 text-xs font-semibold">
                                <span class="inline-flex items-center gap-1.5 text-[#047857]">
                                    <span class="w-2.5 h-2.5 rounded-sm" style="background-color: #3a9d6e;"></span>
                                    Terkelola
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-[#c2410c]">
                                    <span class="w-2.5 h-2.5 rounded-sm" style="background-color: #ef6b4a;"></span>
                                    Tidak Terkelola
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($campusBreakdown as $row)
                                <a href="{{ route('welcome', ['campus_id' => $row['id']]) }}#infografik-sampah"
                                   class="block p-4 rounded-xl border border-slate-200 hover:border-emerald-300 hover:bg-slate-50/70 transition focus:outline-none focus:ring-2 focus:ring-primary">
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <span class="text-sm font-bold text-[#111a2e] truncate">{{ $row['name'] }}</span>
                                        <span class="font-mono text-xs font-bold text-[#047857] shrink-0">
                                            {{ $row['total_handled_kg'] > 0 ? number_format($row['managed_pct'], 1, ',', '.') . '%' : '0%' }} terkelola
                                        </span>
                                    </div>
                                    <div class="w-full h-2.5 rounded-full bg-slate-100 overflow-hidden flex border border-slate-200/70 mb-2.5">
                                        @if ($row['total_handled_kg'] > 0)
                                            @if ($row['managed_pct'] > 0)
                                                <div style="width: {{ $row['managed_pct'] }}%; background-color: #3a9d6e;"></div>
                                            @endif
                                            @if ($row['unmanaged_pct'] > 0)
                                                <div style="width: {{ $row['unmanaged_pct'] }}%; background-color: #ef6b4a;"></div>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between text-[11px] font-mono text-slate-600">
                                        <span>Terkelola: <strong class="text-[#111a2e]">{{ number_format($row['managed_kg'], 1, ',', '.') }} kg</strong></span>
                                        <span>Diangkut: <strong class="text-[#111a2e]">{{ number_format($row['unmanaged_kg'], 1, ',', '.') }} kg</strong></span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <!-- Feature Cards (Dribbble styled DaisyUI cards) -->
        <section class="py-16 bg-base-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-12">
                    <h2 class="text-3xl font-black text-neutral">Fitur Unggulan Sistem PS2</h2>
                    <p class="text-base text-base-content/70 mt-2">Dukungan operasional zero-waste dari hulu ke hilir untuk civitas akademika</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Card 1 -->
                    <div class="card bg-base-100 shadow-md hover:shadow-xl transition-all duration-300 border border-base-300">
                        <div class="card-body">
                            <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                                </svg>
                            </div>
                            <h3 class="card-title text-neutral font-bold text-lg">Pencatatan & Timbangan</h3>
                            <p class="text-sm text-base-content/70">Catat pemilahan sampah organik, anorganik terpilah, dan residu secara presisi per sumber titik kampus.</p>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="card bg-base-100 shadow-md hover:shadow-xl transition-all duration-300 border border-base-300">
                        <div class="card-body">
                            <div class="w-12 h-12 rounded-2xl bg-secondary/10 text-secondary flex items-center justify-center font-bold mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                            <h3 class="card-title text-neutral font-bold text-lg">Bank Sampah & Keuangan</h3>
                            <p class="text-sm text-base-content/70">Kelola penjualan sampah terpilah ke pihak ketiga (pengepul), biaya retribusi residu, dan pembukuan transparan.</p>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="card bg-base-100 shadow-md hover:shadow-xl transition-all duration-300 border border-base-300">
                        <div class="card-body">
                            <div class="w-12 h-12 rounded-2xl bg-accent/10 text-amber-600 flex items-center justify-center font-bold mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h3 class="card-title text-neutral font-bold text-lg">Survei Perilaku (KAP)</h3>
                            <p class="text-sm text-base-content/70">Pengisian kuesioner otomatis dengan kalkulasi skor Likert dan klasifikasi tingkat kesadaran civitas kampus.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer footer-center p-8 bg-base-100 text-base-content/70 border-t border-base-200">
            <aside>
                <div class="flex items-center gap-2 mb-2">
                    <span class="font-extrabold text-neutral tracking-tight">PS2 UAD</span>
                    <span class="text-xs">&bull;</span>
                    <span class="text-xs">Sistem Pengelolaan Sampah & Perilaku Kampus</span>
                </div>
                <p class="text-xs">&copy; {{ date('Y') }} Universitas Ahmad Dahlan. Dikembangkan untuk efisiensi dan kelestarian lingkungan kampus.</p>
            </aside>
        </footer>
    </body>
</html>
