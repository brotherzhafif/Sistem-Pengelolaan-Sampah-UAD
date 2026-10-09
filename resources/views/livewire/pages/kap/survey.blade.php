<?php

use App\Models\Campus;
use App\Models\KapSurvey;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    // Step state: 1 = Demografi, 2 = Knowledge, 3 = Attitude, 4 = Practice, 5 = Facility, 6 = Selesai
    public int $currentStep = 1;
    public bool $isSubmitted = false;
    public ?KapSurvey $submittedSurvey = null;

    // Step 1: Demografi
    public ?int $campus_id = null;
    public string $respondent_name = '';
    public string $respondent_identifier = '';
    public string $respondent_role = 'mahasiswa'; // mahasiswa, dosen, tendik
    public string $faculty_unit = '';
    public string $residence_type = 'kos'; // kos, asrama, rumah_sendiri, kontrakan

    // Step 2: Knowledge (Pengetahuan) - Likert 1-5
    public array $knowledge = [
        1 => 4, // K1: Tahu beda organik vs anorganik vs residu
        2 => 4, // K2: Bahaya plastik sekali pakai
        3 => 4, // K3: UAD punya program zero waste & TPS3R
        4 => 4, // K4: Tahu fungsi warna/kategori tempat sampah pilah
    ];

    // Step 3: Attitude (Sikap) - Likert 1-5
    public array $attitude = [
        1 => 5, // A1: Tanggung jawab atas sampah sendiri
        2 => 5, // A2: Bersedia memilah sebelum membuang
        3 => 5, // A3: Dukung larangan kresek di kantin UAD
        4 => 4, // A4: Bersedia bawa tumbler/kotak makan sendiri
    ];

    // Step 4: Practice (Perilaku Nyata) - Likert 1-5
    public array $practice = [
        1 => 4, // P1: Selalu buang sampah sesuai kategori pilah
        2 => 4, // P2: Menghabiskan makanan (cegah food waste)
        3 => 4, // P3: Membawa botol minum sendiri saat beraktivitas
        4 => 3, // P4: Mengingatkan teman yang buang sampah sembarangan
    ];

    // Step 5: Fasilitas & Masukan
    public array $facility = [
        1 => 4, // F1: Tempat sampah pilah di kampus mudah ditemukan
        2 => 4, // F2: Petunjuk/label pemilahan terbaca jelas
    ];
    public string $feedback = '';

    public function mount(): void
    {
        $defaultCampus = Campus::where('code', 'KAMPUS-4')->first() ?? Campus::first();
        $this->campus_id = $defaultCampus?->id;
    }

    public function nextStep(): void
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'campus_id' => ['required', 'exists:campuses,id'],
                'respondent_role' => ['required', 'in:mahasiswa,dosen,tendik'],
                'faculty_unit' => ['required', 'string', 'max:100'],
                'residence_type' => ['required', 'in:kos,asrama,rumah_sendiri,kontrakan'],
                'respondent_name' => ['nullable', 'string', 'max:100'],
                'respondent_identifier' => ['nullable', 'string', 'max:50'],
            ], [
                'campus_id.required' => 'Pilih unit kampus lokasi utama aktivitas Anda.',
                'faculty_unit.required' => 'Isi nama fakultas, prodi, atau unit kerja Anda.',
            ]);
        }

        $this->currentStep++;
    }

    public function prevStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function submitSurvey(): void
    {
        $this->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'respondent_role' => ['required', 'in:mahasiswa,dosen,tendik'],
            'faculty_unit' => ['required', 'string', 'max:100'],
            'residence_type' => ['required', 'in:kos,asrama,rumah_sendiri,kontrakan'],
            'knowledge.*' => ['required', 'integer', 'between:1,5'],
            'attitude.*' => ['required', 'integer', 'between:1,5'],
            'practice.*' => ['required', 'integer', 'between:1,5'],
            'facility.*' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $kSum = array_sum($this->knowledge);
        $aSum = array_sum($this->attitude);
        $pSum = array_sum($this->practice);

        $kScore = ($kSum / (count($this->knowledge) * 5)) * 100;
        $aScore = ($aSum / (count($this->attitude) * 5)) * 100;
        $pScore = ($pSum / (count($this->practice) * 5)) * 100;
        $overallScore = round(($kScore + $aScore + $pScore) / 3, 2);

        $this->submittedSurvey = KapSurvey::create([
            'campus_id' => $this->campus_id,
            'respondent_name' => $this->respondent_name ? strip_tags(trim($this->respondent_name)) : null,
            'respondent_identifier' => $this->respondent_identifier ? strip_tags(trim($this->respondent_identifier)) : null,
            'respondent_role' => $this->respondent_role,
            'faculty_unit' => strip_tags(trim($this->faculty_unit)),
            'residence_type' => $this->residence_type,
            'knowledge_responses' => array_values($this->knowledge),
            'attitude_responses' => array_values($this->attitude),
            'practice_responses' => array_values($this->practice),
            'facility_responses' => array_values($this->facility),
            'knowledge_score' => $kScore,
            'attitude_score' => $aScore,
            'practice_score' => $pScore,
            'overall_score' => $overallScore,
            'category' => KapSurvey::determineCategory($overallScore),
            'feedback' => $this->feedback ? strip_tags(trim($this->feedback)) : null,
            'survey_date' => Carbon::today()->format('Y-m-d'),
        ]);

        $this->dispatch('toast', message: 'Kuesioner KAP berhasil dikirim! Terima kasih atas partisipasi Anda.', type: 'success');
        $this->isSubmitted = true;
        $this->currentStep = 6;
    }

    public function resetSurvey(): void
    {
        $this->reset([
            'currentStep', 'isSubmitted', 'submittedSurvey',
            'respondent_name', 'respondent_identifier', 'faculty_unit',
            'feedback'
        ]);
        $this->mount();
    }

    public function with(): array
    {
        return [
            'campuses' => Campus::where('is_active', true)->orderBy('id')->get(),
        ];
    }
}; ?>

<div class="w-full">
    @if(!$isSubmitted)
        <!-- Survey Header -->
        <div class="text-center mb-8">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Kuesioner Perilaku Pemilahan Sampah
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1.5 max-w-lg mx-auto leading-relaxed">
                Survei Knowledge, Attitude & Practice (KAP) civitas akademika menuju kampus ramah lingkungan UAD.
            </p>
        </div>

        <!-- Step Progress Indicator -->
        <div class="mb-8 bg-slate-50 border border-slate-200 rounded-2xl p-4">
            <div class="grid grid-cols-5 text-center text-[10px] sm:text-xs font-semibold mb-2.5">
                <span class="{{ $currentStep >= 1 ? 'text-emerald-700 font-bold' : 'text-slate-400' }}">1. Demografi</span>
                <span class="{{ $currentStep >= 2 ? 'text-emerald-700 font-bold' : 'text-slate-400' }}">2. Pengetahuan</span>
                <span class="{{ $currentStep >= 3 ? 'text-emerald-700 font-bold' : 'text-slate-400' }}">3. Sikap</span>
                <span class="{{ $currentStep >= 4 ? 'text-emerald-700 font-bold' : 'text-slate-400' }}">4. Perilaku</span>
                <span class="{{ $currentStep >= 5 ? 'text-emerald-700 font-bold' : 'text-slate-400' }}">5. Masukan</span>
            </div>
            <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                <div class="bg-emerald-600 h-full transition-all duration-300"
                     style="width: {{ ($currentStep / 5) * 100 }}%;"></div>
            </div>
        </div>

        <form wire:submit.prevent="{{ $currentStep === 5 ? 'submitSurvey' : 'nextStep' }}">
            
            <!-- STEP 1: DEMOGRAFI -->
            @if($currentStep === 1)
                <div class="space-y-5">
                    <div class="p-3.5 bg-emerald-50/70 border border-emerald-100 rounded-xl text-xs sm:text-sm text-emerald-800 flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Isi profil singkat Anda di bawah ini. Nama dan identitas bersifat opsional jika ingin anonim.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Status Civitas Akademika <span class="text-rose-500">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="h-11 flex items-center justify-center gap-2 p-3 rounded-xl border text-xs sm:text-sm font-semibold cursor-pointer transition select-none {{ $respondent_role === 'mahasiswa' ? 'bg-emerald-50 border-emerald-500 text-emerald-800 shadow-xs ring-1 ring-emerald-400' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                <input type="radio" wire:model.live="respondent_role" value="mahasiswa" class="hidden">
                                <span class="w-2 h-2 rounded-full {{ $respondent_role === 'mahasiswa' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                Mahasiswa
                            </label>
                            <label class="h-11 flex items-center justify-center gap-2 p-3 rounded-xl border text-xs sm:text-sm font-semibold cursor-pointer transition select-none {{ $respondent_role === 'dosen' ? 'bg-emerald-50 border-emerald-500 text-emerald-800 shadow-xs ring-1 ring-emerald-400' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                <input type="radio" wire:model.live="respondent_role" value="dosen" class="hidden">
                                <span class="w-2 h-2 rounded-full {{ $respondent_role === 'dosen' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                Dosen
                            </label>
                            <label class="h-11 flex items-center justify-center gap-2 p-3 rounded-xl border text-xs sm:text-sm font-semibold cursor-pointer transition select-none {{ $respondent_role === 'tendik' ? 'bg-emerald-50 border-emerald-500 text-emerald-800 shadow-xs ring-1 ring-emerald-400' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                <input type="radio" wire:model.live="respondent_role" value="tendik" class="hidden">
                                <span class="w-2 h-2 rounded-full {{ $respondent_role === 'tendik' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                Tenaga Kependidikan (Tendik)
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Unit Kampus Utama Aktivitas <span class="text-rose-500">*</span></label>
                            <select wire:model="campus_id" class="w-full h-10 text-xs sm:text-sm rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white">
                                @foreach($campuses as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('campus_id') <span class="text-rose-500 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tempat Tinggal Saat Ini <span class="text-rose-500">*</span></label>
                            <select wire:model="residence_type" class="w-full h-10 text-xs sm:text-sm rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white">
                                <option value="kos">Kos Sekitar Kampus</option>
                                <option value="asrama">Asrama UAD (Persada)</option>
                                <option value="rumah_sendiri">Rumah Sendiri / Keluarga</option>
                                <option value="kontrakan">Rumah Kontrakan</option>
                            </select>
                            @error('residence_type') <span class="text-rose-500 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Fakultas / Program Studi / Unit Kerja <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="faculty_unit" placeholder="Contoh: Fakultas Teknologi Industri / Teknik Informatika" class="w-full h-10 text-xs sm:text-sm rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white" required>
                        @error('faculty_unit') <span class="text-rose-500 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nama Lengkap <span class="text-xs text-slate-400 font-normal">(Boleh Kosong / Anonim)</span></label>
                            <input type="text" wire:model="respondent_name" placeholder="Nama Anda (opsional)" class="w-full h-10 text-xs sm:text-sm rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">NIM / NIP <span class="text-xs text-slate-400 font-normal">(Opsional)</span></label>
                            <input type="text" wire:model="respondent_identifier" placeholder="Nomor Induk (opsional)" class="w-full h-10 text-xs sm:text-sm rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white">
                        </div>
                    </div>
                </div>
            @endif

            <!-- STEP 2: KNOWLEDGE (PENGETAHUAN) -->
            @if($currentStep === 2)
                <div class="space-y-4">
                    <div class="p-3.5 bg-sky-50/70 border border-sky-100 rounded-xl text-xs sm:text-sm text-sky-800">
                        <strong>Bagian 1: Pengetahuan (Knowledge)</strong><br>
                        Pilihlah skala 1 (Sangat Tidak Paham) sampai 5 (Sangat Paham) untuk setiap pernyataan berikut.
                    </div>

                    <!-- K1 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-sky-50 text-sky-700 font-bold text-xs flex items-center justify-center shrink-0 border border-sky-200 mt-0.5">1</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya memahami perbedaan sampah organik (sisa makanan/daun), anorganik bernilai (plastik/kertas), dan residu TPA.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Paham</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $knowledge[1] === $i ? 'bg-sky-600 border-sky-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="knowledge.1" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-sky-700">5 — Sangat Paham</span>
                            </div>
                        </div>
                    </div>

                    <!-- K2 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-sky-50 text-sky-700 font-bold text-xs flex items-center justify-center shrink-0 border border-sky-200 mt-0.5">2</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya mengetahui dampak negatif sampah plastik sekali pakai bagi lingkungan dan ekosistem kampus UAD.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Tahu</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $knowledge[2] === $i ? 'bg-sky-600 border-sky-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="knowledge.2" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-sky-700">5 — Sangat Tahu</span>
                            </div>
                        </div>
                    </div>

                    <!-- K3 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-sky-50 text-sky-700 font-bold text-xs flex items-center justify-center shrink-0 border border-sky-200 mt-0.5">3</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya mengetahui bahwa UAD memiliki program zero waste dan fasilitas pemilahan sampah TPS3R mandiri.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Tahu</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $knowledge[3] === $i ? 'bg-sky-600 border-sky-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="knowledge.3" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-sky-700">5 — Sangat Tahu</span>
                            </div>
                        </div>
                    </div>

                    <!-- K4 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-sky-50 text-sky-700 font-bold text-xs flex items-center justify-center shrink-0 border border-sky-200 mt-0.5">4</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya mengetahui fungsi dan label warna pada tempat sampah terpilah yang diletakkan di gedung perkuliahan UAD.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Paham</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $knowledge[4] === $i ? 'bg-sky-600 border-sky-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="knowledge.4" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-sky-700">5 — Sangat Paham</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- STEP 3: ATTITUDE (SIKAP) -->
            @if($currentStep === 3)
                <div class="space-y-4">
                    <div class="p-3.5 bg-emerald-50/70 border border-emerald-100 rounded-xl text-xs sm:text-sm text-emerald-800">
                        <strong>Bagian 2: Sikap & Kepedulian (Attitude)</strong><br>
                        Pilihlah skala 1 (Sangat Tidak Setuju) sampai 5 (Sangat Setuju) untuk setiap pernyataan berikut.
                    </div>

                    <!-- A1 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200 mt-0.5">1</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya merasa bertanggung jawab penuh atas sampah yang saya hasilkan selama beraktivitas di lingkungan kampus UAD.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Setuju</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $attitude[1] === $i ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="attitude.1" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-emerald-700">5 — Sangat Setuju</span>
                            </div>
                        </div>
                    </div>

                    <!-- A2 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200 mt-0.5">2</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya bersedia menyisihkan waktu beberapa detik untuk memilah sampah sebelum membuangnya ke tempat sampah.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Setuju</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $attitude[2] === $i ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="attitude.2" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-emerald-700">5 — Sangat Setuju</span>
                            </div>
                        </div>
                    </div>

                    <!-- A3 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200 mt-0.5">3</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya mendukung penuh larangan penggunaan kantong plastik kresek sekali pakai di seluruh kantin UAD.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Setuju</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $attitude[3] === $i ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="attitude.3" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-emerald-700">5 — Sangat Setuju</span>
                            </div>
                        </div>
                    </div>

                    <!-- A4 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-700 font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200 mt-0.5">4</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya bersedia membawa botol minum/tumbler dan wadah makan sendiri dari rumah/kos untuk mengurangi timbulan sampah.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Setuju</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $attitude[4] === $i ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="attitude.4" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-emerald-700">5 — Sangat Setuju</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- STEP 4: PRACTICE (PERILAKU NYATA) -->
            @if($currentStep === 4)
                <div class="space-y-4">
                    <div class="p-3.5 bg-amber-50/70 border border-amber-100 rounded-xl text-xs sm:text-sm text-amber-800">
                        <strong>Bagian 3: Perilaku Nyata Sehari-hari (Practice)</strong><br>
                        Pilihlah skala 1 (Tidak Pernah) sampai 5 (Selalu / Rutin) untuk setiap pernyataan berikut.
                    </div>

                    <!-- P1 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-amber-50 text-amber-700 font-bold text-xs flex items-center justify-center shrink-0 border border-amber-200 mt-0.5">1</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya membuang sampah selalu sesuai dengan kategori tempat sampah pilah (organik, botol/plastik, kertas, residu).
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Pernah</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $practice[1] === $i ? 'bg-amber-600 border-amber-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="practice.1" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-amber-700">5 — Selalu / Rutin</span>
                            </div>
                        </div>
                    </div>

                    <!-- P2 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-amber-50 text-amber-700 font-bold text-xs flex items-center justify-center shrink-0 border border-amber-200 mt-0.5">2</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya selalu berusaha menghabiskan makanan yang saya beli agar tidak menyisakan sampah sisa makanan (food waste).
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Jarang Habis</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $practice[2] === $i ? 'bg-amber-600 border-amber-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="practice.2" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-amber-700">5 — Selalu Habis</span>
                            </div>
                        </div>
                    </div>

                    <!-- P3 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-amber-50 text-amber-700 font-bold text-xs flex items-center justify-center shrink-0 border border-amber-200 mt-0.5">3</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya membawa botol minum/tumbler sendiri saat kuliah atau beraktivitas kerja di kampus UAD.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Pernah</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $practice[3] === $i ? 'bg-amber-600 border-amber-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="practice.3" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-amber-700">5 — Selalu Bawa</span>
                            </div>
                        </div>
                    </div>

                    <!-- P4 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-amber-50 text-amber-700 font-bold text-xs flex items-center justify-center shrink-0 border border-amber-200 mt-0.5">4</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Saya mengingatkan atau menegur rekan civitas jika melihat mereka membuang sampah sembarangan atau salah tempat.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Pernah</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $practice[4] === $i ? 'bg-amber-600 border-amber-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="practice.4" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-amber-700">5 — Selalu Peduli</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- STEP 5: FASILITAS & SARAN -->
            @if($currentStep === 5)
                <div class="space-y-4">
                    <div class="p-3.5 bg-indigo-50/70 border border-indigo-100 rounded-xl text-xs sm:text-sm text-indigo-800">
                        <strong>Bagian 4: Fasilitas Kampus & Masukan</strong><br>
                        Bantu kami mengevaluasi ketersediaan dan kejelasan fasilitas tempat sampah di kampus Anda.
                    </div>

                    <!-- F1 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0 border border-indigo-200 mt-0.5">1</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Tempat sampah terpilah di gedung perkuliahan, laboratorium, dan kantin mudah ditemukan dan mencukupi.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Sangat Kurang</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $facility[1] === $i ? 'bg-indigo-600 border-indigo-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="facility.1" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-indigo-700">5 — Sangat Memadai</span>
                            </div>
                        </div>
                    </div>

                    <!-- F2 -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-3 shadow-2xs">
                        <div class="flex items-start gap-3">
                            <span class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0 border border-indigo-200 mt-0.5">2</span>
                            <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                                Petunjuk dan tulisan label pada tempat sampah terpilah jelas, terbaca, dan mudah dipahami.
                            </p>
                        </div>
                        <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="sm:w-36 text-left shrink-0">
                                <span class="text-[11px] font-medium text-slate-500">1 — Tidak Jelas</span>
                            </div>
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 flex-1 max-w-xs mx-auto sm:mx-0">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="h-10 rounded-lg border-2 flex items-center justify-center text-xs sm:text-sm font-bold cursor-pointer transition active:scale-95 select-none {{ $facility[2] === $i ? 'bg-indigo-600 border-indigo-600 text-white shadow-xs' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">
                                        <input type="radio" wire:model.live="facility.2" value="{{ $i }}" class="hidden">
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                            <div class="sm:w-36 text-right shrink-0">
                                <span class="text-[11px] font-bold text-indigo-700">5 — Sangat Jelas</span>
                            </div>
                        </div>
                    </div>

                    <!-- Feedback -->
                    <div class="p-4 sm:p-5 bg-white border border-slate-200 rounded-xl space-y-2 shadow-2xs">
                        <label class="block text-xs sm:text-sm font-semibold text-slate-800">
                            Saran & Masukan untuk Pengelola Sampah Kampus UAD <span class="text-xs text-slate-400 font-normal">(Opsional)</span>
                        </label>
                        <textarea wire:model="feedback" rows="3" placeholder="Tuliskan kritik konstruktif, usulan penambahan titik tempat sampah, atau ide gerakan zero waste..." class="w-full text-xs sm:text-sm rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 bg-white leading-relaxed p-3"></textarea>
                    </div>
                </div>
            @endif

            <!-- Navigation Buttons -->
            <div class="mt-8 pt-5 border-t border-slate-100 flex items-center justify-between gap-4">
                @if($currentStep > 1)
                    <button type="button" wire:click="prevStep" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 transition active:scale-95">
                        &larr; Sebelumnya
                    </button>
                @else
                    <div></div>
                @endif

                @if($currentStep < 5)
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-xs transition flex items-center gap-2 active:scale-95">
                        <span>Lanjut ke Langkah {{ $currentStep + 1 }}</span>
                        <span>&rarr;</span>
                    </button>
                @else
                    <button type="submit" wire:loading.attr="disabled" class="px-7 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-md transition flex items-center gap-2 active:scale-95">
                        <span wire:loading.remove>Kirim Tanggapan & Lihat Skor &rarr;</span>
                        <span wire:loading>Menyimpan tanggapan...</span>
                    </button>
                @endif
            </div>
        </form>
    @else
        <!-- RESULT SCREEN (SUKSES) -->
        <div class="text-center py-6 space-y-6">
            <div class="w-20 h-20 rounded-2xl bg-emerald-100 text-emerald-700 mx-auto flex items-center justify-center font-bold text-3xl shadow-sm">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <div>
                <span class="px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 uppercase tracking-wide">
                    Survei Berhasil Disimpan
                </span>
                <h2 class="text-2xl font-bold text-slate-900 mt-3 tracking-tight">Terima Kasih Atas Partisipasi Anda!</h2>
                <p class="text-xs sm:text-sm text-slate-500 mt-1.5 max-w-md mx-auto leading-relaxed">
                    Kontribusi Anda sangat berharga bagi pemetaan perilaku dan perbaikan fasilitas pengelolaan sampah di Universitas Ahmad Dahlan.
                </p>
            </div>

            <!-- Score Badge Card -->
            @if($submittedSurvey)
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 max-w-md mx-auto shadow-sm text-left">
                    <div class="flex items-center justify-between pb-3.5 border-b border-slate-200">
                        <span class="text-xs sm:text-sm font-bold text-slate-700">Skor Indeks KAP Anda</span>
                        <span class="px-3 py-1 rounded-lg text-xs font-bold {{ $submittedSurvey->category === 'sangat_baik' ? 'bg-emerald-100 text-emerald-800' : ($submittedSurvey->category === 'sedang' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                            {{ $submittedSurvey->category_label }}
                        </span>
                    </div>

                    <div class="my-5 text-center">
                        <span class="font-mono text-5xl font-extrabold text-slate-900">{{ number_format($submittedSurvey->overall_score, 1) }}</span>
                        <span class="text-sm font-bold text-slate-400">/ 100</span>
                    </div>

                    <div class="space-y-2.5 text-xs sm:text-sm pt-3 border-t border-slate-200">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">Pengetahuan (Knowledge):</span>
                            <span class="font-mono font-bold text-sky-700">{{ number_format($submittedSurvey->knowledge_score, 1) }}%</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">Sikap (Attitude):</span>
                            <span class="font-mono font-bold text-emerald-700">{{ number_format($submittedSurvey->attitude_score, 1) }}%</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">Perilaku Nyata (Practice):</span>
                            <span class="font-mono font-bold text-amber-700">{{ number_format($submittedSurvey->practice_score, 1) }}%</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="/" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-semibold transition active:scale-95 shadow-xs">
                    Kembali ke Beranda
                </a>
                <button type="button" wire:click="resetSurvey" class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-200 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 transition active:scale-95">
                    Isi Tanggapan Baru
                </button>
            </div>
        </div>
    @endif
</div>
