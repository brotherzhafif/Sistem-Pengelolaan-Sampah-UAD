<?php

use App\Livewire\Actions\Logout;
use App\Models\Campus;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $activeCampusId = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->activeCampusId = session('active_campus_id', $user->campus_id ?? null);
    }

    public function switchCampus(?int $campusId): void
    {
        // Hanya Super Admin / Auditor yang bisa berpindah kampus global
        $user = auth()->user();
        $canSwitch = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        if (!$canSwitch) {
            return;
        }

        $this->activeCampusId = $campusId ?: null;
        session(['active_campus_id' => $this->activeCampusId]);

        // Refresh halaman saat ini agar data tabel/metrik ter-filter otomatis sesuai kampus yang dipilih
        $this->redirect(request()->header('Referer') ?? route('dashboard'), navigate: false);
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    public function with(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        $activeCampus = $this->activeCampusId ? Campus::find($this->activeCampusId) : $user->campus;

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'activeCampus' => $activeCampus,
            'campuses' => Campus::where('is_active', true)->orderBy('id')->get(),
        ];
    }
}; ?>

<aside class="w-64 bg-slate-950 text-slate-300 flex flex-col shrink-0 min-h-screen border-r border-slate-900 select-none">
    <!-- Brand Header -->
    <div class="p-5 flex items-center gap-3 border-b border-white/5">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-sm shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        </div>
        <div class="leading-tight">
            <span class="text-base font-bold tracking-tight text-white">PS2 <span class="text-emerald-400">UAD</span></span>
            <span class="block text-[10px] text-slate-500 font-medium">Zero Waste Campus</span>
        </div>
    </div>

    <!-- Active Campus Selector (Interactive Dropdown for Super Admin) -->
    <div class="mx-3.5 my-3 relative" x-data="{ open: false }">
        @if ($isSuperAdmin)
            <!-- Super Admin Dropdown Trigger -->
            <button @click="open = !open" 
                    type="button"
                    class="w-full p-2.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 flex items-center justify-between text-xs font-semibold text-emerald-400 transition cursor-pointer text-left">
                <div class="flex items-center gap-2 truncate min-w-0">
                    <svg class="w-3.5 h-3.5 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="truncate font-semibold text-slate-200">
                        {{ $activeCampus ? $activeCampus->name : 'Semua Kampus (Pusat)' }}
                    </span>
                </div>
                <svg class="w-3 h-3 text-slate-400 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <!-- Dropdown Menu -->
            <div x-show="open" 
                 @click.outside="open = false" 
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="transform opacity-0 scale-95"
                 x-transition:enter-end="transform opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="transform opacity-100 scale-100"
                 x-transition:leave-end="transform opacity-0 scale-95"
                 class="absolute left-0 right-0 mt-1 py-1 bg-slate-900 border border-slate-800 rounded-xl shadow-xl z-50 overflow-hidden text-xs">
                
                <button wire:click="switchCampus(null)" 
                        @click="open = false"
                        type="button"
                        class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-white/5 transition {{ !$activeCampusId ? 'text-emerald-400 font-bold bg-white/5' : 'text-slate-300' }}">
                    <span>Semua Kampus (Pusat)</span>
                    @if (!$activeCampusId)
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    @endif
                </button>

                <div class="border-t border-white/5 my-1"></div>

                @foreach ($campuses as $campus)
                    <button wire:click="switchCampus({{ $campus->id }})" 
                            @click="open = false"
                            type="button"
                            class="w-full px-3 py-2 text-left flex items-center justify-between hover:bg-white/5 transition {{ $activeCampusId === $campus->id ? 'text-emerald-400 font-bold bg-white/5' : 'text-slate-300' }}">
                        <span class="truncate">{{ $campus->name }}</span>
                        @if ($activeCampusId === $campus->id)
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        @endif
                    </button>
                @endforeach
            </div>
        @else
            <!-- Regular User: Fixed Badge -->
            <div class="p-2.5 rounded-lg bg-white/5 border border-white/10 flex items-center gap-2 text-xs font-semibold text-emerald-400">
                <svg class="w-3.5 h-3.5 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="truncate font-semibold text-slate-200">{{ auth()->user()->campus?->name ?? 'Kampus Umum' }}</span>
            </div>
        @endif
    </div>

    <!-- Navigation Menu Items (Clean SVG Icons ala Dribbble) -->
    <div class="flex-1 px-3 py-2 space-y-4 overflow-y-auto">
        <!-- Section: PENCATATAN -->
        <div>
            <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                Pencatatan
            </div>
            <div class="space-y-0.5">
                <!-- Dashboard -->
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('dashboard') ? 'bg-white/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/5' }}">
                    <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('dashboard') ? 'text-emerald-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>Dashboard</span>
                </a>

                <!-- Penimbangan -->
                <a href="{{ route('weighing') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('weighing') ? 'bg-white/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/5' }}">
                    <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('weighing') ? 'text-emerald-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                    <span>Penimbangan</span>
                </a>

                <!-- Penjualan -->
                <a href="{{ route('sales') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('sales') ? 'bg-white/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/5' }}">
                    <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('sales') ? 'text-emerald-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Penjualan</span>
                </a>

                <!-- Pengangkutan -->
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-70 cursor-not-allowed"
                   title="Pengangkutan Residu">
                    <svg class="w-4 h-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <span>Pengangkutan</span>
                </a>
            </div>
        </div>

        <!-- Section: KEUANGAN (SRS v2 Buku Kas & Buku Besar) -->
        <div>
            <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                Keuangan
            </div>
            <div class="space-y-0.5">
                <!-- Pengeluaran -->
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-70 cursor-not-allowed"
                   title="Pengeluaran Operasional">
                    <svg class="w-4 h-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Pengeluaran</span>
                </a>

                <!-- Buku Kas & Buku Besar -->
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-70 cursor-not-allowed"
                   title="Buku Kas & Buku Besar">
                    <svg class="w-4 h-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>Buku Kas</span>
                </a>
            </div>
        </div>

        <!-- Section: ANALITIK -->
        <div>
            <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                Analitik
            </div>
            <div class="space-y-0.5">
                <!-- Survei KAP -->
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-70 cursor-not-allowed"
                   title="Survei Perilaku (KAP)">
                    <svg class="w-4 h-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 14l9-5-9-5-9 5 9 5z" />
                        <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                    </svg>
                    <span>Survei KAP</span>
                </a>

                <!-- Laporan -->
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-70 cursor-not-allowed"
                   title="Laporan & Ekspor Data">
                    <svg class="w-4 h-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Laporan</span>
                </a>
            </div>
        </div>

        <!-- Section: SISTEM -->
        <div>
            <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                Sistem
            </div>
            <div class="space-y-0.5">
                <!-- Master Data -->
                <a href="{{ route('master-data') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('master-data') ? 'bg-white/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/5' }}">
                    <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('master-data') ? 'text-emerald-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Master Data</span>
                </a>

                <!-- Pengguna -->
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-70 cursor-not-allowed"
                   title="Manajemen Pengguna">
                    <svg class="w-4 h-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>Pengguna</span>
                </a>

                <!-- Notifikasi -->
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-70 cursor-not-allowed"
                   title="Notifikasi & Alerts">
                    <div class="flex items-center gap-2.5">
                        <svg class="w-4 h-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span>Notifikasi</span>
                    </div>
                    <span class="bg-rose-500 text-white text-[9px] font-bold px-1.5 py-0.2 rounded-full">3</span>
                </a>
            </div>
        </div>
    </div>

    <!-- User Profile & Logout Footer -->
    <div class="p-3.5 border-t border-white/5 flex items-center justify-between">
        <a href="{{ route('profile') }}" class="flex items-center gap-2.5 min-w-0 hover:opacity-80 transition">
            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-sky-500 to-indigo-500 text-white flex items-center justify-center font-bold text-xs shrink-0">
                {{ substr(auth()->user()->name, 0, 1) }}
            </div>
            <div class="truncate text-left leading-tight">
                <span class="block text-xs font-semibold text-slate-200 truncate">{{ auth()->user()->name }}</span>
                <span class="block text-[10px] text-slate-500 truncate">{{ auth()->user()->roles->first()?->name ?? 'User' }}</span>
            </div>
        </a>
        <button wire:click="logout" title="Keluar" class="p-1.5 text-slate-400 hover:text-rose-400 hover:bg-white/5 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
        </button>
    </div>
</aside>
