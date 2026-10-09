<?php

use App\Services\AlertService;
use Livewire\Volt\Component;

new class extends Component
{
    public function with(AlertService $alertService): array
    {
        $user = auth()->user();
        if (!$user) {
            return ['alerts' => []];
        }

        $activeCampusId = session('active_campus_id', $user->campus_id ?? null);
        $alerts = $alertService->getAlerts($activeCampusId);

        return [
            'alerts' => $alerts,
        ];
    }
}; ?>

<div class="fixed top-3.5 right-4 sm:right-6 md:right-8 z-40" 
     x-data="{ notifOpen: false }" 
     @click.outside="notifOpen = false"
     @keydown.escape.window="notifOpen = false">
    
    <!-- Floating Notification Bell Button ("Melayang vibes") -->
    <button @click="notifOpen = !notifOpen" 
            type="button" 
            class="relative p-2.5 rounded-xl bg-white/95 backdrop-blur-md border border-slate-200/90 hover:border-emerald-300 hover:bg-white text-slate-600 hover:text-slate-900 shadow-sm hover:shadow-md transition-all duration-200 active:scale-95 cursor-pointer flex items-center justify-center group"
            title="Notifikasi & Peringatan Operasional (SRS M10)">
        <svg class="w-4 h-4 text-slate-600 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        @if(!empty($alerts))
            <span class="absolute -top-1 -right-1 flex h-4 w-4">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex items-center justify-center rounded-full h-4 w-4 bg-rose-600 text-[9px] font-bold text-white leading-none shadow-xs">
                    {{ count($alerts) }}
                </span>
            </span>
        @endif
    </button>

    <!-- Floating Dropdown Popup Card -->
    <div x-show="notifOpen" 
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-1.5"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-1.5"
         class="absolute right-0 mt-2.5 w-80 sm:w-96 rounded-2xl bg-white/95 backdrop-blur-md shadow-2xl border border-slate-200/90 z-50 overflow-hidden divide-y divide-slate-100">
        
        <!-- Header Popup -->
        <div class="p-3.5 bg-slate-50/90 flex items-center justify-between border-b border-slate-100">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-900">Notifikasi Sistem</span>
                @if(!empty($alerts))
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">
                        {{ count($alerts) }} Peringatan
                    </span>
                @endif
            </div>
            <span class="text-[10px] text-slate-400 font-semibold tracking-wide uppercase">SRS M10 Alerts</span>
        </div>

        <!-- Daftar Notifikasi -->
        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
            @forelse($alerts as $alert)
                <div class="p-3.5 hover:bg-slate-50/80 transition flex items-start gap-3">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5 {{ $alert['type'] === 'danger' ? 'bg-rose-100 text-rose-600' : ($alert['type'] === 'warning' ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600') }}">
                        @if($alert['type'] === 'danger')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        @elseif($alert['type'] === 'warning')
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1">
                            <h4 class="text-xs font-bold text-slate-900 truncate">{{ $alert['title'] }}</h4>
                            @if(!empty($alert['time']))
                                <span class="text-[9px] text-slate-400 shrink-0 font-medium">{{ $alert['time'] }}</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500 mt-0.5 leading-snug">{{ $alert['message'] }}</p>
                        @if(!empty($alert['action_url']))
                            <div class="mt-2">
                                <a href="{{ $alert['action_url'] }}" 
                                   @click="notifOpen = false"
                                   class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 hover:text-emerald-700 transition">
                                    <span>{{ $alert['action_label'] }}</span>
                                    <span>&rarr;</span>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-xs text-slate-400">
                    <svg class="w-8 h-8 mx-auto text-emerald-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium text-slate-500">Semua operasional lancar</span>
                    <p class="text-[11px] text-slate-400 mt-0.5">Tidak ada peringatan aktif saat ini.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
