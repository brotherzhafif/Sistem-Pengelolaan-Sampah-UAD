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
                            <a href="{{ route('register') }}" class="btn btn-primary btn-lg text-white font-bold shadow-xl shadow-primary/25 hover:shadow-primary/40 px-8">
                                Mulai Sekarang
                                <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                            <a href="{{ route('login') }}" class="btn btn-outline btn-lg font-bold px-8">
                                Masuk ke Akun
                            </a>
                        @endauth
                    </div>
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

        <!-- Feature Cards (Dribbble styled DaisyUI cards) -->
        <section class="py-16 bg-base-200/60 border-y border-base-200">
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
