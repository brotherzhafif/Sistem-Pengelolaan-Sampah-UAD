<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
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
            <span class="block text-[10px] text-slate-400 font-medium">Zero Waste Campus</span>
        </div>
    </div>

    <!-- Active Campus Badge -->
    <div class="mx-3.5 my-3 p-2.5 rounded-lg bg-white/5 border border-white/10 flex items-center justify-between text-xs font-semibold text-emerald-400">
        <div class="flex items-center gap-2 truncate">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
            <span class="truncate">{{ auth()->user()->campus?->name ?? 'Pusat / Seluruh Kampus' }}</span>
        </div>
        <span class="text-[10px] text-slate-400">▾</span>
    </div>

    <!-- Navigation Menu Items -->
    <div class="flex-1 px-3 py-2 space-y-5 overflow-y-auto">
        <!-- Section: PENCATATAN -->
        <div>
            <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                Pencatatan
            </div>
            <div class="space-y-0.5">
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('dashboard') ? 'bg-white/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/5' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-80 cursor-not-allowed" 
                   title="Tersedia di Phase 3">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                    <span>Penimbangan (M2)</span>
                </a>

                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-80 cursor-not-allowed"
                   title="Tersedia di Phase 4">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Penjualan (M3)</span>
                </a>

                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-80 cursor-not-allowed"
                   title="Tersedia di Phase 5">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <span>Pengangkutan (M4)</span>
                </a>
            </div>
        </div>

        <!-- Section: KEUANGAN (SRS v2 Buku Kas & Buku Besar) -->
        <div>
            <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                Keuangan (v2)
            </div>
            <div class="space-y-0.5">
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-80 cursor-not-allowed"
                   title="Tersedia di Phase 6">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" />
                    </svg>
                    <span>Pengeluaran (M5)</span>
                </a>

                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition text-slate-400 hover:text-white hover:bg-white/5 opacity-80 cursor-not-allowed"
                   title="Tersedia di Phase 7">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>Buku Kas & Besar (M6)</span>
                </a>
            </div>
        </div>

        <!-- Section: SISTEM -->
        <div>
            <div class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                Sistem & Master
            </div>
            <div class="space-y-0.5">
                <a href="{{ route('master-data') }}" 
                   class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('master-data') ? 'bg-white/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:text-white hover:bg-white/5' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Master Data (M7)</span>
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

