<?php

use App\Services\AlertService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(AlertService $alertService): array
    {
        $user = auth()->user();
        $activeCampusId = session('active_campus_id', $user->campus_id ?? null);
        $alerts = $alertService->getAlerts($activeCampusId);

        return [
            'alerts'      => $alerts,
            'totalAlerts' => count($alerts),
        ];
    }
}; ?>

<x-slot name="header">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="font-bold text-xl text-slate-900 tracking-tight">Notifikasi & Peringatan</h2>
            <p class="text-xs text-slate-500 mt-0.5">Ringkasan peringatan dan pengingat operasional sistem PS2 UAD.</p>
        </div>
    </div>
</x-slot>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

        {{-- Summary KPI --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Total Notif --}}
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-500 font-medium">Total Peringatan Aktif</p>
                    <p class="text-2xl font-black text-slate-900 leading-tight">{{ $totalAlerts }}</p>
                </div>
            </div>

            {{-- Kritis --}}
            @php
                $dangerCount  = collect($alerts)->where('type', 'danger')->count();
                $warningCount = collect($alerts)->where('type', 'warning')->count();
                $infoCount    = collect($alerts)->where('type', 'info')->count();
            @endphp
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-500 font-medium">Peringatan / Kritis</p>
                    <p class="text-2xl font-black text-slate-900 leading-tight">{{ $dangerCount + $warningCount }}</p>
                    <p class="text-[10px] text-slate-400">{{ $dangerCount }} kritis &bull; {{ $warningCount }} peringatan</p>
                </div>
            </div>

            {{-- Info --}}
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] text-slate-500 font-medium">Informasi / Pengingat</p>
                    <p class="text-2xl font-black text-slate-900 leading-tight">{{ $infoCount }}</p>
                </div>
            </div>
        </div>

        {{-- Daftar Notifikasi --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Daftar Peringatan Aktif</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Semua peringatan dan pengingat operasional yang membutuhkan tindakan.</p>
                </div>
                @if($totalAlerts > 0)
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-100">
                        {{ $totalAlerts }} Aktif
                    </span>
                @endif
            </div>

            @if($totalAlerts === 0)
                {{-- Empty State --}}
                <div class="py-16 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 mb-1">Semua Operasional Lancar</h3>
                    <p class="text-sm text-slate-500 max-w-xs mx-auto">Tidak ada peringatan atau pengingat aktif saat ini. Sistem berjalan normal.</p>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($alerts as $alert)
                        @php
                            $typeConfig = match($alert['type']) {
                                'danger'  => ['bg' => 'bg-rose-50',   'border' => 'border-rose-200',   'icon_bg' => 'bg-rose-100',   'icon_text' => 'text-rose-600',   'badge_bg' => 'bg-rose-100',   'badge_text' => 'text-rose-700',   'label' => 'Kritis'],
                                'warning' => ['bg' => 'bg-amber-50',  'border' => 'border-amber-200',  'icon_bg' => 'bg-amber-100',  'icon_text' => 'text-amber-600',  'badge_bg' => 'bg-amber-100',  'badge_text' => 'text-amber-700',  'label' => 'Peringatan'],
                                default   => ['bg' => 'bg-emerald-50','border' => 'border-emerald-200','icon_bg' => 'bg-emerald-100','icon_text' => 'text-emerald-600','badge_bg' => 'bg-emerald-100','badge_text' => 'text-emerald-700','label' => 'Info'],
                            };
                        @endphp
                        <div class="p-5 {{ $typeConfig['bg'] }} hover:brightness-[0.98] transition-all">
                            <div class="flex items-start gap-4">
                                {{-- Icon --}}
                                <div class="w-10 h-10 rounded-xl {{ $typeConfig['icon_bg'] }} {{ $typeConfig['icon_text'] }} flex items-center justify-center shrink-0 mt-0.5">
                                    @if($alert['type'] === 'danger')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                    @elseif($alert['type'] === 'warning')
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    @endif
                                </div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-1.5">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="text-sm font-bold text-slate-900">{{ $alert['title'] }}</h4>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $typeConfig['badge_bg'] }} {{ $typeConfig['badge_text'] }}">
                                                {{ $typeConfig['label'] }}
                                            </span>
                                        </div>
                                        @if(!empty($alert['time']))
                                            <span class="text-[11px] text-slate-400 font-medium shrink-0">{{ $alert['time'] }}</span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-slate-600 leading-relaxed">{{ $alert['message'] }}</p>
                                    @if(!empty($alert['action_url']))
                                        <div class="mt-3">
                                            <a href="{{ $alert['action_url'] }}" 
                                               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-bold {{ $typeConfig['icon_bg'] }} {{ $typeConfig['icon_text'] }} hover:brightness-95 transition border {{ $typeConfig['border'] }}">
                                                <span>{{ $alert['action_label'] }}</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info Section: Kondisi Pemicu Peringatan --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h3 class="text-sm font-bold text-slate-900 mb-3">Kondisi Pemicu Peringatan Otomatis</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-50">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-700">Reminder Input Harian</p>
                        <p class="text-[10px] text-slate-500 mt-0.5">Muncul jika belum ada sesi penimbangan yang diinput hari ini.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-50">
                    <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-700">Stok Terpilah Menumpuk</p>
                        <p class="text-[10px] text-slate-500 mt-0.5">Muncul jika akumulasi stok sampah siap jual &ge; <strong>500 kg</strong>.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-50">
                    <div class="w-7 h-7 rounded-lg bg-rose-100 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-700">Akumulasi Residu Tinggi</p>
                        <p class="text-[10px] text-slate-500 mt-0.5">Muncul jika akumulasi residu TPS &ge; <strong>1.000 kg</strong>.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-50">
                    <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-700">Saldo Kas Menipis</p>
                        <p class="text-[10px] text-slate-500 mt-0.5">Muncul jika saldo kas operasional &lt; <strong>Rp 500.000</strong>.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
