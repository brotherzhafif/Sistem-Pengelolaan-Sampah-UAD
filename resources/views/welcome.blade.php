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
    <body class="font-sans bg-[#f5f7fa] text-slate-900 antialiased selection:bg-emerald-600 selection:text-white min-h-screen flex flex-col">
        <!-- Subtle Background Glows -->
        <div class="fixed top-0 left-1/4 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none -z-10"></div>
        <div class="fixed bottom-0 right-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl pointer-events-none -z-10"></div>

        <!-- Navbar -->
        <header class="sticky top-0 z-50 backdrop-blur-md bg-white/90 border-b border-slate-200/80 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <a href="/" class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-md shadow-emerald-600/20">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xl font-black tracking-tight text-slate-900">PS2 <span class="text-emerald-600">UAD</span></span>
                            <span class="hidden sm:inline-block ms-2 badge bg-emerald-50 border border-emerald-200 font-semibold text-xs text-emerald-700">Eco Campus</span>
                        </div>
                    </a>

                    @if (Route::has('login'))
                        <livewire:welcome.navigation />
                    @endif
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative pt-12 pb-20 md:pt-20 md:pb-28 overflow-hidden flex-1">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto">
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 leading-tight">
                        Wujudkan Kampus Hijau & <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">Zero-Waste</span> Berkelanjutan
                    </h1>
                    
                    <p class="mt-6 text-base sm:text-lg text-slate-600 leading-relaxed max-w-2xl mx-auto font-normal">
                        Sistem digital terpadu untuk pencatatan penimbangan sampah, monitoring logistik armada, integrasi bank sampah, dan survei KAP (Knowledge, Attitude, Practice) di seluruh Kampus UAD.
                    </p>

                    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn bg-emerald-600 hover:bg-emerald-700 border-none btn-lg text-white font-bold shadow-xl shadow-emerald-600/25 px-8">
                                Buka Dashboard
                                <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="btn bg-emerald-600 hover:bg-emerald-700 border-none btn-lg text-white font-bold shadow-xl shadow-emerald-600/25 px-8">
                                Mulai Sekarang
                                <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                            <a href="{{ route('login') }}" class="btn border-slate-300 bg-white hover:bg-slate-50 text-slate-700 btn-lg font-bold px-8 shadow-xs">
                                Masuk ke Akun
                            </a>
                        @endauth
                    </div>
                </div>

                <!-- Stats Highlight -->
                <div class="mt-16 max-w-4xl mx-auto">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-white border border-slate-200/80 shadow-md rounded-2xl p-6 divide-y md:divide-y-0 md:divide-x divide-slate-100">
                        <div class="text-center py-3 md:py-0 px-4">
                            <div class="text-xs uppercase font-bold text-slate-400 tracking-wider">Cakupan Lokasi</div>
                            <div class="text-3xl font-black text-emerald-600 mt-1">6 Kampus</div>
                            <div class="text-xs font-medium text-slate-500 mt-0.5">Kampus 1 hingga Kampus 6 UAD</div>
                        </div>
                        
                        <div class="text-center py-3 md:py-0 px-4">
                            <div class="text-xs uppercase font-bold text-slate-400 tracking-wider">Alur Sirkular</div>
                            <div class="text-3xl font-black text-blue-600 mt-1">Real-time</div>
                            <div class="text-xs font-medium text-slate-500 mt-0.5">Timbang, Angkut & Jual ke Pengepul</div>
                        </div>
                        
                        <div class="text-center py-3 md:py-0 px-4">
                            <div class="text-xs uppercase font-bold text-slate-400 tracking-wider">Riset Perilaku</div>
                            <div class="text-3xl font-black text-amber-500 mt-1">KAP Survey</div>
                            <div class="text-xs font-medium text-slate-500 mt-0.5">Evaluasi Knowledge, Attitude & Practice</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Cards -->
        <section class="py-16 bg-white border-y border-slate-200/80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-12">
                    <h2 class="text-3xl font-black text-slate-900">Fitur Unggulan Sistem PS2</h2>
                    <p class="text-base text-slate-500 mt-2">Dukungan operasional zero-waste dari hulu ke hilir untuk civitas akademika</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Card 1 -->
                    <div class="bg-[#f8fafc] rounded-2xl p-6 shadow-xs hover:shadow-md transition-all duration-200 border border-slate-200/80">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold mb-4 border border-emerald-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                            </svg>
                        </div>
                        <h3 class="text-slate-900 font-bold text-lg">Pencatatan & Timbangan</h3>
                        <p class="text-sm text-slate-600 mt-1.5 leading-relaxed">Catat pemilahan sampah organik, anorganik terpilah, dan residu secara presisi per sumber titik kampus.</p>
                    </div>

                    <!-- Card 2 -->
                    <div class="bg-[#f8fafc] rounded-2xl p-6 shadow-xs hover:shadow-md transition-all duration-200 border border-slate-200/80">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold mb-4 border border-blue-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h3 class="text-slate-900 font-bold text-lg">Bank Sampah & Keuangan</h3>
                        <p class="text-sm text-slate-600 mt-1.5 leading-relaxed">Kelola penjualan sampah terpilah ke pihak ketiga (pengepul), biaya retribusi residu, dan pembukuan transparan.</p>
                    </div>

                    <!-- Card 3 -->
                    <div class="bg-[#f8fafc] rounded-2xl p-6 shadow-xs hover:shadow-md transition-all duration-200 border border-slate-200/80">
                        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold mb-4 border border-amber-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-slate-900 font-bold text-lg">Survei Perilaku (KAP)</h3>
                        <p class="text-sm text-slate-600 mt-1.5 leading-relaxed">Pengisian kuesioner otomatis dengan kalkulasi skor Likert dan klasifikasi tingkat kesadaran civitas kampus.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-8 bg-white border-t border-slate-200 text-center text-slate-500">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex items-center justify-center gap-2 mb-1.5">
                    <span class="font-extrabold text-slate-900 tracking-tight">PS2 UAD</span>
                    <span class="text-xs text-slate-400">&bull;</span>
                    <span class="text-xs text-slate-500 font-medium">Sistem Pengelolaan Sampah & Perilaku Kampus</span>
                </div>
                <p class="text-xs text-slate-400">&copy; {{ date('Y') }} Universitas Ahmad Dahlan. Dikembangkan untuk efisiensi dan kelestarian lingkungan kampus.</p>
            </div>
        </footer>
    </body>
</html>
