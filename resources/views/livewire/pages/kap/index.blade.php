<?php

use App\Models\Campus;
use App\Models\KapSurvey;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public ?int $selectedCampusId = null;
    public string $filterRole = '';
    public string $filterCategory = '';
    public ?string $filterDateFrom = null;
    public ?string $filterDateTo = null;

    public ?int $viewSurveyId = null;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $sessionCampus = session('active_campus_id');
            $this->selectedCampusId = $sessionCampus !== null && $sessionCampus !== '' ? (int) $sessionCampus : null;
        } else {
            $this->selectedCampusId = (int) $user->campus_id;
        }
    }

    public function updatedSelectedCampusId($value): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateTo(): void
    {
        $this->resetPage();
    }

    public function viewSurvey(int $id): void
    {
        $this->viewSurveyId = $id;
    }

    public function closeDetailModal(): void
    {
        $this->viewSurveyId = null;
    }

    public function with(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (int) $user->campus_id;

        $query = KapSurvey::with('campus')
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterRole, fn($q) => $q->where('respondent_role', $this->filterRole))
            ->when($this->filterCategory, fn($q) => $q->where('category', $this->filterCategory))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('survey_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('survey_date', '<=', $this->filterDateTo))
            ->orderBy('survey_date', 'desc')
            ->orderBy('id', 'desc');

        // Rule 7: Tepat 8 baris per halaman
        $surveys = $query->paginate(8);

        // Agregasi Statistik Analitik KAP
        $statsQuery = KapSurvey::query()
            ->when($campusQueryId, fn($q) => $q->where('campus_id', $campusQueryId))
            ->when($this->filterRole, fn($q) => $q->where('respondent_role', $this->filterRole))
            ->when($this->filterCategory, fn($q) => $q->where('category', $this->filterCategory))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('survey_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('survey_date', '<=', $this->filterDateTo));

        $totalRespondents = (int) (clone $statsQuery)->count();
        $avgKnowledge = (float) (clone $statsQuery)->avg('knowledge_score') ?? 0.0;
        $avgAttitude = (float) (clone $statsQuery)->avg('attitude_score') ?? 0.0;
        $avgPractice = (float) (clone $statsQuery)->avg('practice_score') ?? 0.0;
        $avgOverall = (float) (clone $statsQuery)->avg('overall_score') ?? 0.0;

        $highCount = (int) (clone $statsQuery)->where('category', 'sangat_baik')->count();
        $moderateCount = (int) (clone $statsQuery)->where('category', 'sedang')->count();
        $lowCount = (int) (clone $statsQuery)->where('category', 'kurang')->count();

        $highPct = $totalRespondents > 0 ? round(($highCount / $totalRespondents) * 100, 1) : 0.0;
        $moderatePct = $totalRespondents > 0 ? round(($moderateCount / $totalRespondents) * 100, 1) : 0.0;
        $lowPct = $totalRespondents > 0 ? round(($lowCount / $totalRespondents) * 100, 1) : 0.0;

        $detailedSurvey = $this->viewSurveyId 
            ? KapSurvey::with('campus')->find($this->viewSurveyId) 
            : null;

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => Campus::where('is_active', true)->orderBy('id')->get(),
            'surveys' => $surveys,
            'detailedSurvey' => $detailedSurvey,
            'totalRespondents' => $totalRespondents,
            'avgKnowledge' => round($avgKnowledge, 1),
            'avgAttitude' => round($avgAttitude, 1),
            'avgPractice' => round($avgPractice, 1),
            'avgOverall' => round($avgOverall, 1),
            'highCount' => $highCount,
            'moderateCount' => $moderateCount,
            'lowCount' => $lowCount,
            'highPct' => $highPct,
            'moderatePct' => $moderatePct,
            'lowPct' => $lowPct,
        ];
    }
}; ?>

<div x-data="{ 
    detailModal: @entangle('viewSurveyId')
}"
@close-modal.window="detailModal = null">

    <!-- Topbar Header -->
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Survei Perilaku (KAP) Civitas UAD</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Analisis indeks Knowledge, Attitude, and Practice pemilahan sampah civitas akademika.
                </p>
            </div>

            <!-- Action Button: Copy Survey Link & Open Public Form -->
            <div class="flex items-center gap-2">
                <button type="button" 
                        x-data="{ copied: false }"
                        @click="window.copyToClipboard('{{ url('/survei-kap') }}', 'Tautan survei KAP berhasil disalin ke clipboard!'); copied = true; setTimeout(() => copied = false, 2500);"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs transition flex items-center gap-1.5 active:scale-95 cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <span x-text="copied ? 'Tautan Disalin!' : 'Salin Link Survei'">Salin Link Survei</span>
                </button>

                <a href="{{ url('/survei-kap') }}" target="_blank" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    <span>Buka Form Publik &rarr;</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- 1. KPI Cards Analitik KAP (5 Cards Clean Dribbble Style) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                
                <!-- Card 1: Total Responden -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-indigo-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Responden</span>
                        <div class="w-6 h-6 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalRespondents) }}</span>
                        <span class="text-xs text-slate-500">orang</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 truncate">Civitas mengisi kuesioner</p>
                </div>

                <!-- Card 2: Indeks KAP Kampus Keseluruhan -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-emerald-600"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Indeks KAP Kampus</span>
                        <div class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-1">
                        <span class="font-mono text-2xl font-bold text-emerald-700">{{ $avgOverall }}</span>
                        <span class="text-xs text-slate-400 font-bold">/ 100</span>
                    </div>
                    <p class="text-[10px] text-emerald-600 font-medium mt-1 truncate">
                        {{ $avgOverall >= 80 ? 'Kategori Sangat Baik' : ($avgOverall >= 60 ? 'Kategori Cukup / Sedang' : 'Perlu Peningkatan') }}
                    </p>
                </div>

                <!-- Card 3: Skor Pengetahuan (Knowledge) -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-sky-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pengetahuan (K)</span>
                        <div class="w-6 h-6 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-0.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ $avgKnowledge }}</span>
                        <span class="text-xs text-sky-600 font-bold">%</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 truncate">Pemahaman pemilahan sampah</p>
                </div>

                <!-- Card 4: Skor Sikap (Attitude) -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-teal-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sikap & Peduli (A)</span>
                        <div class="w-6 h-6 rounded-md bg-teal-50 text-teal-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-0.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ $avgAttitude }}</span>
                        <span class="text-xs text-teal-600 font-bold">%</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 truncate">Kesadaran & kepedulian kampus</p>
                </div>

                <!-- Card 5: Skor Perilaku Nyata (Practice) -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-2xs relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-1 bg-amber-500"></div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Perilaku Nyata (P)</span>
                        <div class="w-6 h-6 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-2.5 flex items-baseline gap-0.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ $avgPractice }}</span>
                        <span class="text-xs text-amber-600 font-bold">%</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 truncate">Tindakan memilah & bawa tumbler</p>
                </div>

            </div>

            <!-- 2. Visual Komparasi Dimensi & Distribusi Kategori -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                
                <!-- Kolom Kiri: Komparasi Gap Dimensi (Knowledge vs Attitude vs Practice) -->
                <div class="lg:col-span-7 bg-white border border-slate-200 rounded-xl p-5 shadow-2xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">Perbandingan Dimensi KAP</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Identifikasi kesenjangan antara pengetahuan, sikap, dan tindakan nyata</p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded bg-slate-100 text-slate-700">Skala 0 - 100</span>
                    </div>

                    <div class="space-y-4 pt-1">
                        <!-- Knowledge Bar -->
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                                    Pengetahuan (Knowledge)
                                </span>
                                <span class="font-mono font-bold text-slate-900">{{ $avgKnowledge }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden">
                                <div class="bg-sky-500 h-full transition-all duration-500" style="width: {{ $avgKnowledge }}%;"></div>
                            </div>
                            <span class="text-[10px] text-slate-400">Pemahaman jenis sampah, bahaya plastik, fasilitas TPS3R & warna wadah.</span>
                        </div>

                        <!-- Attitude Bar -->
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-teal-500"></span>
                                    Sikap & Kepedulian (Attitude)
                                </span>
                                <span class="font-mono font-bold text-slate-900">{{ $avgAttitude }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden">
                                <div class="bg-teal-500 h-full transition-all duration-500" style="width: {{ $avgAttitude }}%;"></div>
                            </div>
                            <span class="text-[10px] text-slate-400">Rasa tanggung jawab, kesediaan memilah, dan dukungan pembatasan kresek.</span>
                        </div>

                        <!-- Practice Bar -->
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                    Perilaku Nyata (Practice)
                                </span>
                                <span class="font-mono font-bold text-slate-900">{{ $avgPractice }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden">
                                <div class="bg-amber-500 h-full transition-all duration-500" style="width: {{ $avgPractice }}%;"></div>
                            </div>
                            <span class="text-[10px] text-slate-400">Kebiasaan memilah rutin, menghabiskan makanan, dan membawa tumbler mandiri.</span>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Distribusi Kategori Kesadaran -->
                <div class="lg:col-span-5 bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Distribusi Kategori Kesadaran</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Proporsi tingkat kesadaran seluruh responden</p>

                        <!-- Segmented Distribution Bar -->
                        <div class="w-full h-4 rounded-full overflow-hidden bg-slate-100 flex mt-4 shadow-inner">
                            @if($totalRespondents > 0)
                                <div style="width: {{ $highPct }}%;" class="bg-emerald-500 transition-all duration-500" title="Sangat Baik: {{ $highPct }}%"></div>
                                <div style="width: {{ $moderatePct }}%;" class="bg-amber-500 transition-all duration-500" title="Sedang: {{ $moderatePct }}%"></div>
                                <div style="width: {{ $lowPct }}%;" class="bg-rose-500 transition-all duration-500" title="Kurang: {{ $lowPct }}%"></div>
                            @else
                                <div class="w-full bg-slate-200"></div>
                            @endif
                        </div>

                        <!-- Legend Cards -->
                        <div class="space-y-2 mt-4 text-xs">
                            <div class="flex items-center justify-between p-2 rounded-lg bg-emerald-50/60 border border-emerald-100">
                                <span class="text-emerald-800 font-semibold flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    Sangat Baik (Skor &ge; 80)
                                </span>
                                <div class="text-right">
                                    <span class="font-mono font-bold text-slate-900">{{ $highCount }}</span>
                                    <span class="text-[10px] text-slate-500">({{ $highPct }}%)</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-2 rounded-lg bg-amber-50/60 border border-amber-100">
                                <span class="text-amber-800 font-semibold flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    Cukup / Sedang (60 - 79.9)
                                </span>
                                <div class="text-right">
                                    <span class="font-mono font-bold text-slate-900">{{ $moderateCount }}</span>
                                    <span class="text-[10px] text-slate-500">({{ $moderatePct }}%)</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-2 rounded-lg bg-rose-50/60 border border-rose-100">
                                <span class="text-rose-800 font-semibold flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                    Kurang (&lt; 60)
                                </span>
                                <div class="text-right">
                                    <span class="font-mono font-bold text-slate-900">{{ $lowCount }}</span>
                                    <span class="text-[10px] text-slate-500">({{ $lowPct }}%)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-400 mt-3 pt-2 border-t border-slate-100">
                        Target UAD Zero Waste: &gt; 80% civitas masuk kategori Sangat Baik.
                    </p>
                </div>

            </div>

            <!-- 3. Filter Bar & Container Tabel Responden -->
            <div id="kap-table-container" class="bg-white border border-slate-200 rounded-xl shadow-2xs overflow-hidden">
                
                <!-- Filter Toolbar -->
                <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Filter Kampus -->
                        @if($isSuperAdmin)
                            <select wire:model.live="selectedCampusId" class="text-xs rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white">
                                <option value="">Semua Kampus</option>
                                @foreach($campuses as $campus)
                                    <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                @endforeach
                            </select>
                        @endif

                        <!-- Filter Role -->
                        <select wire:model.live="filterRole" class="text-xs rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white">
                            <option value="">Semua Status Responden</option>
                            <option value="mahasiswa">Mahasiswa</option>
                            <option value="dosen">Dosen</option>
                            <option value="tendik">Tenaga Kependidikan</option>
                        </select>

                        <!-- Filter Kategori -->
                        <select wire:model.live="filterCategory" class="text-xs rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white">
                            <option value="">Semua Kategori</option>
                            <option value="sangat_baik">Sangat Baik</option>
                            <option value="sedang">Cukup / Sedang</option>
                            <option value="kurang">Kurang</option>
                        </select>
                    </div>

                    <!-- Filter Tanggal -->
                    <div class="flex items-center gap-2">
                        <input type="date" wire:model.live="filterDateFrom" class="text-xs rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white" placeholder="Dari">
                        <span class="text-xs text-slate-400">s/d</span>
                        <input type="date" wire:model.live="filterDateTo" class="text-xs rounded-lg border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white" placeholder="Sampai">
                    </div>
                </div>

                <!-- Table Content Wrapper with Smooth In-Place Loading Overlay -->
                <div class="relative overflow-x-auto">
                    <!-- Smooth Loading Overlay -->
                    <div wire:loading.flex class="absolute inset-0 bg-white/70 backdrop-blur-2xs z-20 items-center justify-center">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-semibold shadow-md">
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Memperbarui data survei...</span>
                        </div>
                    </div>

                    <!-- Table -->
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Responden</th>
                                <th class="py-3 px-4">Kampus & Unit</th>
                                <th class="py-3 px-4 text-center">Pengetahuan</th>
                                <th class="py-3 px-4 text-center">Sikap</th>
                                <th class="py-3 px-4 text-center">Perilaku</th>
                                <th class="py-3 px-4 text-right">Skor KAP</th>
                                <th class="py-3 px-4 text-center">Kategori</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($surveys as $survey)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3 px-4 whitespace-nowrap text-slate-500 font-mono text-[11px]">
                                        {{ $survey->survey_date->format('d/m/Y') }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-900">
                                            {{ $survey->respondent_name ?? 'Anonim' }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                                            <span class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 font-medium">
                                                {{ $survey->role_label }}
                                            </span>
                                            @if($survey->respondent_identifier)
                                                <span>&bull; {{ $survey->respondent_identifier }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-slate-800">{{ $survey->campus->name }}</div>
                                        <div class="text-[11px] text-slate-400 truncate max-w-xs">{{ $survey->faculty_unit }}</div>
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-bold text-sky-700">
                                        {{ number_format($survey->knowledge_score, 0) }}%
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-bold text-teal-700">
                                        {{ number_format($survey->attitude_score, 0) }}%
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-bold text-amber-700">
                                        {{ number_format($survey->practice_score, 0) }}%
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <span class="font-mono text-sm font-bold text-slate-900">{{ number_format($survey->overall_score, 1) }}</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold {{ $survey->category === 'sangat_baik' ? 'bg-emerald-100 text-emerald-800' : ($survey->category === 'sedang' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                            {{ $survey->category_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <button type="button" 
                                                wire:click="viewSurvey({{ $survey->id }})" 
                                                class="w-7 h-7 rounded-md border border-slate-200 text-slate-600 hover:text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 transition inline-flex items-center justify-center"
                                                title="Lihat Rincian Jawaban Responden">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="p-8 text-center text-xs text-slate-400">
                                        Tidak ada data survei responden yang sesuai filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer (In-Place Independen & 8 baris per halaman) -->
                @if($surveys->hasPages())
                    <div class="p-3 border-t border-slate-100 bg-slate-50/50">
                        {{ $surveys->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>

        </div>
    </div>

    <!-- MODAL DETAIL JAWABAN RESPONDEN (RULE 1: TELEPORT BODY & FULL BACKDROP BLUR) -->
    <template x-teleport="body">
        <div x-show="detailModal" 
             x-cloak 
             style="display: none;"
             class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            
            <div @click.away="detailModal = null; $wire.closeDetailModal()"
                 class="bg-white rounded-2xl max-w-xl w-full max-h-[90vh] overflow-hidden flex flex-col shadow-2xl border border-slate-200">
                
                <!-- Modal Header -->
                <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Rincian Survei Responden</h3>
                        <p class="text-[11px] text-slate-400">Detail jawaban kuesioner evaluasi perilaku pemilahan</p>
                    </div>
                    <button type="button" @click="detailModal = null; $wire.closeDetailModal()" class="w-7 h-7 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200 flex items-center justify-center text-sm font-bold">
                        &times;
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 overflow-y-auto space-y-4 text-xs">
                    @if($detailedSurvey)
                        <!-- Identitas Responden -->
                        <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <div>
                                <span class="block text-[10px] text-slate-400 uppercase font-semibold">Nama Responden</span>
                                <span class="font-bold text-slate-800">{{ $detailedSurvey->respondent_name ?? 'Anonim' }}</span>
                                <span class="block text-[11px] text-slate-500">{{ $detailedSurvey->role_label }} &bull; {{ $detailedSurvey->respondent_identifier ?? '-' }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-400 uppercase font-semibold">Unit Kampus</span>
                                <span class="font-bold text-slate-800">{{ $detailedSurvey->campus->name }}</span>
                                <span class="block text-[11px] text-slate-500">{{ $detailedSurvey->faculty_unit }}</span>
                            </div>
                        </div>

                        <!-- Ringkasan Nilai Dimensi -->
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div class="p-2.5 rounded-lg bg-sky-50 border border-sky-100">
                                <span class="block text-[10px] text-sky-800 font-semibold uppercase">Knowledge</span>
                                <span class="font-mono text-base font-bold text-slate-900">{{ number_format($detailedSurvey->knowledge_score, 1) }}%</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-teal-50 border border-teal-100">
                                <span class="block text-[10px] text-teal-800 font-semibold uppercase">Attitude</span>
                                <span class="font-mono text-base font-bold text-slate-900">{{ number_format($detailedSurvey->attitude_score, 1) }}%</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-100">
                                <span class="block text-[10px] text-amber-800 font-semibold uppercase">Practice</span>
                                <span class="font-mono text-base font-bold text-slate-900">{{ number_format($detailedSurvey->practice_score, 1) }}%</span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-100">
                                <span class="block text-[10px] text-emerald-800 font-semibold uppercase">Skor KAP</span>
                                <span class="font-mono text-base font-bold text-emerald-800">{{ number_format($detailedSurvey->overall_score, 1) }}</span>
                            </div>
                        </div>

                        <!-- Feedback Responden -->
                        @if($detailedSurvey->feedback)
                            <div class="p-3 bg-indigo-50/60 border border-indigo-100 rounded-xl space-y-1">
                                <span class="block text-[10px] font-bold text-indigo-900 uppercase tracking-wide">Kritik, Saran & Masukan Fasilitas:</span>
                                <p class="text-xs text-slate-700 italic leading-relaxed">
                                    "{{ $detailedSurvey->feedback }}"
                                </p>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="p-3.5 border-t border-slate-100 bg-slate-50 flex items-center justify-end">
                    <button type="button" @click="detailModal = null; $wire.closeDetailModal()" class="px-4 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>

