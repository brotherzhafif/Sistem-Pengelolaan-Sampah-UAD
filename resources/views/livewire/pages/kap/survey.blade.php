<?php

use App\Models\Campus;
use App\Models\KapSurvey;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    // Steps: 1 = Demografi, 2 = Knowledge, 3 = Attitude, 4 = Practice, 5 = Satisfaction, 6 = Fasilitas, 7 = Review, 8 = Selesai
    public int $currentStep = 1;
    public bool $isSubmitted = false;
    public ?KapSurvey $submittedSurvey = null;

    // Step 1: Demografi (D1 - D6)
    public ?int $campus_id = null;
    public string $respondent_role = 'mahasiswa'; // mahasiswa, dosen, tendik, outsourcing
    public string $faculty_unit = 'Fakultas Teknologi Industri';
    public string $gender = 'Laki-laki'; // Laki-laki, Perempuan
    public bool $has_attended_training = true;
    public bool $is_willing_volunteer = true;
    public string $respondent_name = ''; // Opsional / Anonim
    public string $respondent_identifier = ''; // Opsional NIM/NIP
    public ?string $residence_type = 'kos';

    // Step 2: Knowledge (K1 - K8) Benar (true) / Salah (false)
    public array $knowledge = [
        1 => true,  // K1: Sampah organik bisa dikompos (Kunci: Benar)
        2 => false, // K2: Semua plastik bisa didaur ulang tanpa syarat (Kunci: Salah)
        3 => true,  // K3: Tercampur minyak/kimia residu (Kunci: Benar)
        4 => true,  // K4: Tahu sistem pilah kampus (Kunci: Benar)
        5 => true,  // K5: Styrofoam dan kain sulit daur ulang (Kunci: Benar)
        6 => true,  // K6: Tahu buang sesuai label (Kunci: Benar)
        7 => false, // K7: Elektronik buang sampah biasa (Kunci: Salah)
        8 => true,  // K8: Kertas basah tidak bisa didaur ulang (Kunci: Benar)
    ];

    // Step 3: Attitude (A1 - A6) Likert 1-5 (STS - SS)
    public array $attitude = [
        1 => 5, // A1: Tanggung jawab bersama
        2 => 5, // A2: Penting memilah sebelum membuang
        3 => 4, // A3: Bersedia luangkan waktu ekstra
        4 => 5, // A4: Kampus bersih nyaman belajar
        5 => 4, // A5: Merasa bersalah jika tidak memilah
        6 => 5, // A6: Contoh bagi masyarakat sekitar
    ];

    // Step 4: Practice (P1 - P6) Likert 1-5 (TP - SL)
    public array $practice = [
        1 => 4, // P1: Memilah organik dan anorganik
        2 => 4, // P2: Buang sesuai label
        3 => 5, // P3: Bawa tumbler/botol sendiri
        4 => 4, // P4: Bawa tas belanja sendiri ke kantin
        5 => 4, // P5: Mengurangi plastik sekali pakai
        6 => 4, // P6: Buang elektronik ke tempat khusus
    ];

    // Step 5: Satisfaction (S1 - S5) Likert 1-5 (STP - SP)
    public array $satisfaction = [
        1 => 4, // S1: Jumlah tempat sampah memadai
        2 => 4, // S2: Label/petunjuk jelas
        3 => 4, // S3: Kebersihan area TPS baik
        4 => 4, // S4: Sosialisasi/informasi cukup
        5 => 4, // S5: Puas sistem keseluruhan
    ];

    // Step 6: Fasilitas & Hambatan (F1, B1, B2) Checkboxes & Masukan
    public array $facilities = [
        'Tempat sampah terpilah (organik/anorganik)',
    ];
    public array $barriers = [];
    public array $motivations = [
        'Kesadaran lingkungan',
    ];
    public string $feedback = '';

    public function mount(): void
    {
        $defaultCampus = Campus::where('code', 'KAMPUS-4')->first() ?? Campus::first();
        $this->campus_id = $defaultCampus?->id;
    }

    public function validateStep1(): void
    {
        $this->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'respondent_role' => ['required', 'in:mahasiswa,dosen,tendik,outsourcing'],
            'faculty_unit' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:Laki-laki,Perempuan'],
            'has_attended_training' => ['required', 'boolean'],
            'is_willing_volunteer' => ['required', 'boolean'],
            'respondent_name' => ['nullable', 'string', 'max:100'],
            'respondent_identifier' => ['nullable', 'string', 'max:50'],
        ], [
            'campus_id.required' => 'Pilih unit kampus tempat Anda beraktivitas.',
            'faculty_unit.required' => 'Pilih fakultas atau unit kerja Anda.',
        ]);
    }

    public function goToStep(int $step): void
    {
        if ($step < 1 || $step > 7) {
            return;
        }

        if ($this->currentStep === 1 && $step > 1) {
            $this->validateStep1();
        }

        $this->currentStep = $step;
    }

    public function nextStep(): void
    {
        if ($this->currentStep === 1) {
            $this->validateStep1();
        }

        if ($this->currentStep < 7) {
            $this->currentStep++;
        }
    }

    public function prevStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function submitSurvey(): void
    {
        $this->validateStep1();

        $this->validate([
            'knowledge.*' => ['required', 'boolean'],
            'attitude.*' => ['required', 'integer', 'between:1,5'],
            'practice.*' => ['required', 'integer', 'between:1,5'],
            'satisfaction.*' => ['required', 'integer', 'between:1,5'],
            'facilities' => ['nullable', 'array'],
            'barriers' => ['nullable', 'array'],
            'motivations' => ['nullable', 'array'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        // Kunci Jawaban Pengetahuan (K1 - K8)
        $knowledgeKeys = [
            1 => true,  // K1: Benar
            2 => false, // K2: Salah
            3 => true,  // K3: Benar
            4 => true,  // K4: Benar
            5 => true,  // K5: Benar
            6 => true,  // K6: Benar
            7 => false, // K7: Salah
            8 => true,  // K8: Benar
        ];

        $correctCount = 0;
        foreach ($knowledgeKeys as $idx => $expected) {
            $val = (bool) ($this->knowledge[$idx] ?? false);
            if ($val === $expected) {
                $correctCount++;
            }
        }
        $kScore = ($correctCount / 8) * 100;

        $aInt = array_map('intval', $this->attitude);
        $pInt = array_map('intval', $this->practice);
        $sInt = array_map('intval', $this->satisfaction);

        $aScore = (array_sum($aInt) / (count($aInt) * 5)) * 100;
        $pScore = (array_sum($pInt) / (count($pInt) * 5)) * 100;
        $sScore = (array_sum($sInt) / (count($sInt) * 5)) * 100;

        $overallScore = round(($kScore + $aScore + $pScore) / 3, 2);

        $this->submittedSurvey = KapSurvey::create([
            'campus_id' => $this->campus_id,
            'respondent_name' => $this->respondent_name ? strip_tags(trim($this->respondent_name)) : null,
            'respondent_identifier' => $this->respondent_identifier ? strip_tags(trim($this->respondent_identifier)) : null,
            'respondent_role' => $this->respondent_role,
            'faculty_unit' => strip_tags(trim($this->faculty_unit)),
            'gender' => $this->gender,
            'has_attended_training' => $this->has_attended_training,
            'is_willing_volunteer' => $this->is_willing_volunteer,
            'residence_type' => $this->residence_type,
            'knowledge_responses' => $this->knowledge,
            'attitude_responses' => array_values($aInt),
            'practice_responses' => array_values($pInt),
            'satisfaction_responses' => array_values($sInt),
            'facility_responses' => array_values($this->facilities),
            'barrier_responses' => [
                'barriers' => array_values($this->barriers),
                'motivations' => array_values($this->motivations),
            ],
            'knowledge_score' => $kScore,
            'attitude_score' => $aScore,
            'practice_score' => $pScore,
            'satisfaction_score' => $sScore,
            'overall_score' => $overallScore,
            'category' => KapSurvey::determineCategory($overallScore),
            'feedback' => $this->feedback ? strip_tags(trim($this->feedback)) : null,
            'survey_date' => Carbon::today()->format('Y-m-d'),
        ]);

        $this->isSubmitted = true;
        $this->currentStep = 8;

        $this->dispatch('toast', message: 'Survei KAP Anda berhasil dikirim! Terima kasih atas partisipasi Anda.', type: 'success');
    }

    public function resetForm(): void
    {
        $this->isSubmitted = false;
        $this->submittedSurvey = null;
        $this->currentStep = 1;
        $this->feedback = '';
    }

    public function with(): array
    {
        return [
            'campuses' => Campus::where('is_active', true)->orderBy('id')->get(),
            'faculties' => [
                'Fakultas Teknologi Industri',
                'Fakultas Teknik Industri',
                'Fakultas MIPA',
                'Fakultas Ekonomi & Bisnis',
                'Fakultas Hukum',
                'Fakultas Keguruan & Ilmu Pendidikan',
                'Fakultas Farmasi',
                'Fakultas Sastra, Budaya & Komunikasi',
                'Fakultas Psikologi',
                'Fakultas Agama Islam',
                'Fakultas Kedokteran',
                'Fakultas Kesehatan Masyarakat',
                'Pascasarjana',
                'Unit/Biro lainnya',
            ],
        ];
    }
}; ?>

<div x-data="{
    curStep: @entangle('currentStep'),
    k: @entangle('knowledge'),
    a: @entangle('attitude'),
    p: @entangle('practice'),
    s: @entangle('satisfaction'),
    gender: @entangle('gender'),
    training: @entangle('has_attended_training'),
    volunteer: @entangle('is_willing_volunteer'),
    role: @entangle('respondent_role'),
    facilities: @entangle('facilities'),
    barriers: @entangle('barriers'),
    motivations: @entangle('motivations'),
    setK(idx, val) {
        this.k[idx] = val;
        $wire.set('knowledge.' + idx, val, false);
    },
    setA(idx, val) {
        this.a[idx] = val;
        $wire.set('attitude.' + idx, val, false);
    },
    setP(idx, val) {
        this.p[idx] = val;
        $wire.set('practice.' + idx, val, false);
    },
    setS(idx, val) {
        this.s[idx] = val;
        $wire.set('satisfaction.' + idx, val, false);
    },
    setGender(val) {
        this.gender = val;
        $wire.set('gender', val, false);
    },
    setRole(val) {
        this.role = val;
        $wire.set('respondent_role', val, false);
    },
    setTraining(val) {
        this.training = val;
        $wire.set('has_attended_training', val, false);
    },
    setVolunteer(val) {
        this.volunteer = val;
        $wire.set('is_willing_volunteer', val, false);
    },
    toggleItem(listName, item) {
        let arr = this[listName] || [];
        let index = arr.indexOf(item);
        if (index > -1) {
            arr.splice(index, 1);
        } else {
            arr.push(item);
        }
        this[listName] = [...arr];
        $wire.set(listName, this[listName], false);
    }
}" class="max-w-3xl mx-auto space-y-6">

    <!-- Header Survei UAD (Clean Modern Header) -->
    <div class="text-center bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-6 sm:p-8 shadow-xl border border-slate-700/50 relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 w-44 h-44 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="text-4xl mb-3">♻️</div>
        <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white mb-2">
            Survei Pemilahan Sampah Kampus
        </h1>
        <p class="text-xs sm:text-sm text-slate-300 max-w-xl mx-auto leading-relaxed">
            Bantu kami memahami pengetahuan, sikap, dan perilaku Anda tentang pemilahan sampah di kampus Universitas Ahmad Dahlan.
        </p>
        
        <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 mt-5 text-xs text-slate-300 border-t border-slate-700/60 pt-4">
            <span class="flex items-center gap-1.5 font-medium">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                5–8 menit
            </span>
            <span class="flex items-center gap-1.5 font-medium">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                33 pertanyaan
            </span>
            <span class="flex items-center gap-1.5 font-medium">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Anonim & Terjaga
            </span>
        </div>
    </div>

    <!-- Stepper Navigation & Progress Bar -->
    @if(!$isSubmitted)
        <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-2xs space-y-3">
            <div class="grid grid-cols-7 gap-1 text-center text-[10px] sm:text-xs font-semibold">
                <div class="transition-colors truncate" :class="curStep === 1 ? 'text-emerald-700 font-bold' : (curStep > 1 ? 'text-emerald-600' : 'text-slate-400')">
                    1. Demografi
                </div>
                <div class="transition-colors truncate" :class="curStep === 2 ? 'text-emerald-700 font-bold' : (curStep > 2 ? 'text-emerald-600' : 'text-slate-400')">
                    2. Knowledge
                </div>
                <div class="transition-colors truncate" :class="curStep === 3 ? 'text-emerald-700 font-bold' : (curStep > 3 ? 'text-emerald-600' : 'text-slate-400')">
                    3. Attitude
                </div>
                <div class="transition-colors truncate" :class="curStep === 4 ? 'text-emerald-700 font-bold' : (curStep > 4 ? 'text-emerald-600' : 'text-slate-400')">
                    4. Practice
                </div>
                <div class="transition-colors truncate" :class="curStep === 5 ? 'text-emerald-700 font-bold' : (curStep > 5 ? 'text-emerald-600' : 'text-slate-400')">
                    5. Kepuasan
                </div>
                <div class="transition-colors truncate" :class="curStep === 6 ? 'text-emerald-700 font-bold' : (curStep > 6 ? 'text-emerald-600' : 'text-slate-400')">
                    6. Fasilitas
                </div>
                <div class="transition-colors truncate" :class="curStep === 7 ? 'text-emerald-700 font-bold' : 'text-slate-400'">
                    7. Review
                </div>
            </div>

            <!-- Animated Progress Line -->
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-500 h-2 rounded-full transition-all duration-300 ease-out"
                     :style="'width: ' + ((curStep / 7) * 100) + '%'"></div>
            </div>
        </div>
    @endif

    <!-- ═══ STEP 1: DEMOGRAFI (BAGIAN A) ═══ -->
    <div x-show="curStep === 1" x-cloak class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-2xs space-y-6">
        <div class="border-b border-slate-100 pb-4">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-800">
                Bagian A
            </span>
            <h2 class="text-lg font-bold text-slate-900 mt-2">Data Diri Civitas Akademika</h2>
            <p class="text-xs text-slate-500">Informasi dasar untuk analisis segmentasi pemilahan. Data Anda dijamin anonim.</p>
        </div>

        <div class="space-y-5">
            <!-- D1: Kampus -->
            <div>
                <label class="block text-xs font-bold text-slate-800 mb-1.5 flex items-center gap-1.5">
                    <span class="text-emerald-700 font-mono">D1</span>
                    <span>Kampus Lokasi Aktivitas Utama <span class="text-rose-500">*</span></span>
                </label>
                <select wire:model.defer="campus_id" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                    <option value="">Pilih Kampus UAD...</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}">
                            {{ $campus->name }} &mdash; {{ $campus->location_address ?? 'Yogyakarta' }}
                        </option>
                    @endforeach
                </select>
                @error('campus_id') <span class="text-[11px] text-rose-500 font-medium block mt-1">{{ $message }}</span> @enderror
            </div>

            <!-- D2: Status Civitas -->
            <div>
                <label class="block text-xs font-bold text-slate-800 mb-1.5 flex items-center gap-1.5">
                    <span class="text-emerald-700 font-mono">D2</span>
                    <span>Status di Kampus <span class="text-rose-500">*</span></span>
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    @foreach(['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen', 'tendik' => 'Tenaga Kependidikan', 'outsourcing' => 'Tenaga Outsourcing'] as $key => $lbl)
                        <button type="button" 
                                @click="setRole('{{ $key }}')"
                                :class="role === '{{ $key }}' ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                                class="px-3.5 py-2.5 rounded-xl border text-xs text-center transition flex items-center justify-center gap-2 cursor-pointer">
                            <span class="w-2.5 h-2.5 rounded-full" :class="role === '{{ $key }}' ? 'bg-emerald-600' : 'bg-slate-300'"></span>
                            <span>{{ $lbl }}</span>
                        </button>
                    @endforeach
                </div>
                @error('respondent_role') <span class="text-[11px] text-rose-500 font-medium block mt-1">{{ $message }}</span> @enderror
            </div>

            <!-- D3: Fakultas / Unit -->
            <div>
                <label class="block text-xs font-bold text-slate-800 mb-1.5 flex items-center gap-1.5">
                    <span class="text-emerald-700 font-mono">D3</span>
                    <span>Fakultas / Unit Kerja <span class="text-rose-500">*</span></span>
                </label>
                <select wire:model.defer="faculty_unit" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                    <option value="">Pilih Fakultas / Unit...</option>
                    @foreach($faculties as $fac)
                        <option value="{{ $fac }}">{{ $fac }}</option>
                    @endforeach
                </select>
                @error('faculty_unit') <span class="text-[11px] text-rose-500 font-medium block mt-1">{{ $message }}</span> @enderror
            </div>

            <!-- D4: Jenis Kelamin -->
            <div>
                <label class="block text-xs font-bold text-slate-800 mb-1.5 flex items-center gap-1.5">
                    <span class="text-emerald-700 font-mono">D4</span>
                    <span>Jenis Kelamin <span class="text-rose-500">*</span></span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" 
                            @click="setGender('Laki-laki')"
                            :class="gender === 'Laki-laki' ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            class="px-4 py-2.5 rounded-xl border text-xs text-center transition flex items-center justify-center gap-2 cursor-pointer">
                        <span class="w-2.5 h-2.5 rounded-full" :class="gender === 'Laki-laki' ? 'bg-emerald-600' : 'bg-slate-300'"></span>
                        <span>Laki-laki</span>
                    </button>
                    <button type="button" 
                            @click="setGender('Perempuan')"
                            :class="gender === 'Perempuan' ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            class="px-4 py-2.5 rounded-xl border text-xs text-center transition flex items-center justify-center gap-2 cursor-pointer">
                        <span class="w-2.5 h-2.5 rounded-full" :class="gender === 'Perempuan' ? 'bg-emerald-600' : 'bg-slate-300'"></span>
                        <span>Perempuan</span>
                    </button>
                </div>
            </div>

            <!-- D5: Pernah Sosialisasi -->
            <div>
                <label class="block text-xs font-bold text-slate-800 mb-1.5 flex items-center gap-1.5">
                    <span class="text-emerald-700 font-mono">D5</span>
                    <span>Pernah mengikuti sosialisasi pemilahan sampah di kampus? <span class="text-rose-500">*</span></span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" 
                            @click="setTraining(true)"
                            :class="training === true ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            class="px-4 py-2.5 rounded-xl border text-xs text-center transition flex items-center justify-center gap-2 cursor-pointer">
                        <span>✓ Ya, Pernah</span>
                    </button>
                    <button type="button" 
                            @click="setTraining(false)"
                            :class="training === false ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            class="px-4 py-2.5 rounded-xl border text-xs text-center transition flex items-center justify-center gap-2 cursor-pointer">
                        <span>✗ Belum Pernah</span>
                    </button>
                </div>
            </div>

            <!-- D6: Bersedia Menjadi Relawan -->
            <div>
                <label class="block text-xs font-bold text-slate-800 mb-1.5 flex items-center gap-1.5">
                    <span class="text-emerald-700 font-mono">D6</span>
                    <span>Bersedia menjadi relawan program pemilahan sampah kampus? <span class="text-rose-500">*</span></span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" 
                            @click="setVolunteer(true)"
                            :class="volunteer === true ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            class="px-4 py-2.5 rounded-xl border text-xs text-center transition flex items-center justify-center gap-2 cursor-pointer">
                        <span>✓ Ya, Bersedia</span>
                    </button>
                    <button type="button" 
                            @click="setVolunteer(false)"
                            :class="volunteer === false ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                            class="px-4 py-2.5 rounded-xl border text-xs text-center transition flex items-center justify-center gap-2 cursor-pointer">
                        <span>✗ Belum Bersedia</span>
                    </button>
                </div>
            </div>

            <!-- Optional Identitas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Nama Lengkap <span class="text-slate-400 font-normal">(Opsional / Boleh Dikosongkan)</span>
                    </label>
                    <input type="text" wire:model.defer="respondent_name" placeholder="Biarkan kosong jika ingin anonim" class="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        NIM / NIP / NIDN <span class="text-slate-400 font-normal">(Opsional)</span>
                    </label>
                    <input type="text" wire:model.defer="respondent_identifier" placeholder="Nomor induk civitas (opsional)" class="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex justify-end">
            <button type="button" 
                    wire:click="nextStep"
                    class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-2 cursor-pointer">
                <span>Lanjut ke Pengetahuan (Knowledge) &rarr;</span>
            </button>
        </div>
    </div>

    <!-- ═══ STEP 2: KNOWLEDGE (BAGIAN B - 8 PERTANYAAN BENAR/SALAH) ═══ -->
    <div x-show="curStep === 2" x-cloak class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-2xs space-y-6">
        <div class="border-b border-slate-100 pb-4">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-sky-100 text-sky-800">
                Bagian B
            </span>
            <h2 class="text-lg font-bold text-slate-900 mt-2">Pengetahuan (Knowledge)</h2>
            <p class="text-xs text-slate-500">Pilihlah <span class="font-bold text-slate-700">Benar</span> atau <span class="font-bold text-slate-700">Salah</span> untuk setiap pernyataan berikut.</p>
        </div>

        @php
            $kQuestions = [
                1 => 'Sampah organik (sisa makanan, daun) bisa dijadikan kompos.',
                2 => 'Semua jenis plastik bisa didaur ulang tanpa syarat apapun.',
                3 => 'Sampah yang tercampur minyak atau cairan kimia termasuk kategori residu.',
                4 => 'Saya mengetahui bahwa ada sistem pemilahan sampah di kampus UAD.',
                5 => 'Styrofoam dan kain sangat sulit diproses dalam rantai daur ulang konvensional.',
                6 => 'Saya tahu ke mana membuang sampah sesuai warna label tempat sampah kampus.',
                7 => 'Sampah elektronik (baterai, kabel, lampu) bisa dibuang bersama sampah anorganik biasa.',
                8 => 'Kertas dan kardus yang sudah basah atau berminyak tidak bisa didaur ulang kembali.',
            ];
        @endphp

        <div class="space-y-4">
            @foreach($kQuestions as $idx => $question)
                <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition space-y-3">
                    <div class="flex items-start gap-2.5">
                        <span class="font-mono text-xs font-bold text-sky-700 shrink-0 mt-0.5">K{{ $idx }}</span>
                        <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                            {{ $question }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <button type="button" 
                                @click="setK({{ $idx }}, true)"
                                :class="k[{{ $idx }}] === true ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                                class="py-2.5 px-3 rounded-xl border text-xs font-semibold text-center transition flex items-center justify-center gap-1.5 cursor-pointer">
                            <span>✓ Benar</span>
                        </button>
                        <button type="button" 
                                @click="setK({{ $idx }}, false)"
                                :class="k[{{ $idx }}] === false ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                                class="py-2.5 px-3 rounded-xl border text-xs font-semibold text-center transition flex items-center justify-center gap-1.5 cursor-pointer">
                            <span>✗ Salah</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <button type="button" wire:click="prevStep" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                &larr; Kembali
            </button>
            <button type="button" wire:click="nextStep" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-2 cursor-pointer">
                <span>Lanjut ke Sikap (Attitude) &rarr;</span>
            </button>
        </div>
    </div>

    <!-- ═══ STEP 3: ATTITUDE (BAGIAN C - 6 PERTANYAAN LIKERT 1-5 STS..SS) ═══ -->
    <div x-show="curStep === 3" x-cloak class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-2xs space-y-6">
        <div class="border-b border-slate-100 pb-4">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-teal-100 text-teal-800">
                Bagian C
            </span>
            <h2 class="text-lg font-bold text-slate-900 mt-2">Sikap (Attitude)</h2>
            <p class="text-xs text-slate-500">Seberapa setuju Anda dengan pernyataan berikut? (1 = Sangat Tidak Setuju, 5 = Sangat Setuju)</p>
        </div>

        @php
            $aQuestions = [
                1 => 'Pemilahan sampah adalah tanggung jawab bersama seluruh civitas, bukan hanya petugas kebersihan.',
                2 => 'Penting bagi setiap individu untuk memilah sampah sebelum membuangnya ke tempat sampah.',
                3 => 'Saya bersedia meluangkan waktu ekstra beberapa detik untuk memilah sampah dengan benar.',
                4 => 'Kampus yang bersih dan bebas sampah meningkatkan kenyamanan belajar dan bekerja.',
                5 => 'Saya merasa bersalah jika membuang sampah tercampur tanpa memilah.',
                6 => 'Pemilahan sampah di kampus UAD dapat menjadi teladan ramah lingkungan bagi masyarakat sekitar.',
            ];
            $likertScaleAttitude = [
                1 => ['STS', 'Sangat Tidak Setuju'],
                2 => ['TS', 'Tidak Setuju'],
                3 => ['N', 'Netral'],
                4 => ['S', 'Setuju'],
                5 => ['SS', 'Sangat Setuju'],
            ];
        @endphp

        <div class="space-y-5">
            @foreach($aQuestions as $idx => $question)
                <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-3">
                    <div class="flex items-start gap-2.5">
                        <span class="font-mono text-xs font-bold text-teal-700 shrink-0 mt-0.5">A{{ $idx }}</span>
                        <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                            {{ $question }}
                        </p>
                    </div>

                    <div class="grid grid-cols-5 gap-1.5 sm:gap-2 pt-1">
                        @foreach($likertScaleAttitude as $score => $labels)
                            <button type="button" 
                                    @click="setA({{ $idx }}, {{ $score }})"
                                    :class="a[{{ $idx }}] === {{ $score }} ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                                    class="py-2.5 px-1 sm:px-2 rounded-xl border text-center transition flex flex-col items-center justify-center gap-1 cursor-pointer">
                                <span class="font-mono text-sm sm:text-base font-bold">{{ $score }}</span>
                                <span class="text-[9px] sm:text-[10px] leading-tight text-center">{{ $labels[0] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <button type="button" wire:click="prevStep" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                &larr; Kembali
            </button>
            <button type="button" wire:click="nextStep" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-2 cursor-pointer">
                <span>Lanjut ke Perilaku (Practice) &rarr;</span>
            </button>
        </div>
    </div>

    <!-- ═══ STEP 4: PRACTICE (BAGIAN D - 6 PERTANYAAN LIKERT 1-5 TP..SL) ═══ -->
    <div x-show="curStep === 4" x-cloak class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-2xs space-y-6">
        <div class="border-b border-slate-100 pb-4">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-100 text-amber-800">
                Bagian D
            </span>
            <h2 class="text-lg font-bold text-slate-900 mt-2">Perilaku Nyata (Practice)</h2>
            <p class="text-xs text-slate-500">Seberapa sering Anda melakukan hal berikut di kampus? (1 = Tidak Pernah, 5 = Selalu)</p>
        </div>

        @php
            $pQuestions = [
                1 => 'Saya memilah sampah organik dan anorganik sebelum membuangnya.',
                2 => 'Saya membuang sampah tepat sesuai label petunjuk pada tempat sampah terpilah.',
                3 => 'Saya membawa tumbler atau botol minum sendiri saat beraktivitas di kampus.',
                4 => 'Saya membawa tas belanja atau tas kantin sendiri saat membeli makanan di lingkungan UAD.',
                5 => 'Saya secara aktif berusaha mengurangi penggunaan kantong atau wadah plastik sekali pakai.',
                6 => 'Saya membuang sampah elektronik (baterai/elektronik kecil) ke wadah drop point khusus.',
            ];
            $likertScalePractice = [
                1 => ['TP', 'Tidak Pernah'],
                2 => ['J', 'Jarang'],
                3 => ['K', 'Kadang'],
                4 => ['S', 'Sering'],
                5 => ['SL', 'Selalu'],
            ];
        @endphp

        <div class="space-y-5">
            @foreach($pQuestions as $idx => $question)
                <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-3">
                    <div class="flex items-start gap-2.5">
                        <span class="font-mono text-xs font-bold text-amber-700 shrink-0 mt-0.5">P{{ $idx }}</span>
                        <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                            {{ $question }}
                        </p>
                    </div>

                    <div class="grid grid-cols-5 gap-1.5 sm:gap-2 pt-1">
                        @foreach($likertScalePractice as $score => $labels)
                            <button type="button" 
                                    @click="setP({{ $idx }}, {{ $score }})"
                                    :class="p[{{ $idx }}] === {{ $score }} ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                                    class="py-2.5 px-1 sm:px-2 rounded-xl border text-center transition flex flex-col items-center justify-center gap-1 cursor-pointer">
                                <span class="font-mono text-sm sm:text-base font-bold">{{ $score }}</span>
                                <span class="text-[9px] sm:text-[10px] leading-tight text-center">{{ $labels[0] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <button type="button" wire:click="prevStep" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                &larr; Kembali
            </button>
            <button type="button" wire:click="nextStep" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-2 cursor-pointer">
                <span>Lanjut ke Kepuasan (Satisfaction) &rarr;</span>
            </button>
        </div>
    </div>

    <!-- ═══ STEP 5: SATISFACTION (BAGIAN E - 5 PERTANYAAN LIKERT 1-5 STP..SP) ═══ -->
    <div x-show="curStep === 5" x-cloak class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-2xs space-y-6">
        <div class="border-b border-slate-100 pb-4">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-indigo-100 text-indigo-800">
                Bagian E
            </span>
            <h2 class="text-lg font-bold text-slate-900 mt-2">Kepuasan Fasilitas (Satisfaction)</h2>
            <p class="text-xs text-slate-500">Seberapa puas Anda dengan sistem dan sarana pemilahan kampus? (1 = Sangat Tidak Puas, 5 = Sangat Puas)</p>
        </div>

        @php
            $sQuestions = [
                1 => 'Jumlah tempat sampah terpilah di lingkungan kampus sudah memadai dan mudah dijangkau.',
                2 => 'Label dan petunjuk pada tempat sampah sudah jelas, terbaca, dan mudah dipahami.',
                3 => 'Kebersihan area tempat penampungan sampah (TPS) kampus terpelihara dengan baik.',
                4 => 'Informasi, edukasi, dan sosialisasi tentang pemilahan sampah di kampus sudah mencukupi.',
                5 => 'Secara keseluruhan, saya puas dengan sistem tata kelola sampah ramah lingkungan di UAD.',
            ];
            $likertScaleSatisfaction = [
                1 => ['STP', 'Sangat Tidak Puas'],
                2 => ['TP', 'Tidak Puas'],
                3 => ['N', 'Netral'],
                4 => ['P', 'Puas'],
                5 => ['SP', 'Sangat Puas'],
            ];
        @endphp

        <div class="space-y-5">
            @foreach($sQuestions as $idx => $question)
                <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-3">
                    <div class="flex items-start gap-2.5">
                        <span class="font-mono text-xs font-bold text-indigo-700 shrink-0 mt-0.5">S{{ $idx }}</span>
                        <p class="text-xs sm:text-sm font-semibold text-slate-800 leading-snug">
                            {{ $question }}
                        </p>
                    </div>

                    <div class="grid grid-cols-5 gap-1.5 sm:gap-2 pt-1">
                        @foreach($likertScaleSatisfaction as $score => $labels)
                            <button type="button" 
                                    @click="setS({{ $idx }}, {{ $score }})"
                                    :class="s[{{ $idx }}] === {{ $score }} ? 'border-emerald-600 bg-emerald-50 text-emerald-800 font-bold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                                    class="py-2.5 px-1 sm:px-2 rounded-xl border text-center transition flex flex-col items-center justify-center gap-1 cursor-pointer">
                                <span class="font-mono text-sm sm:text-base font-bold">{{ $score }}</span>
                                <span class="text-[9px] sm:text-[10px] leading-tight text-center">{{ $labels[0] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <button type="button" wire:click="prevStep" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                &larr; Kembali
            </button>
            <button type="button" wire:click="nextStep" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-2 cursor-pointer">
                <span>Lanjut ke Fasilitas & Hambatan &rarr;</span>
            </button>
        </div>
    </div>

    <!-- ═══ STEP 6: FASILITAS & HAMBATAN (BAGIAN F - F1, B1, B2) ═══ -->
    <div x-show="curStep === 6" x-cloak class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-2xs space-y-6">
        <div class="border-b border-slate-100 pb-4">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-800">
                Bagian F
            </span>
            <h2 class="text-lg font-bold text-slate-900 mt-2">Fasilitas, Hambatan & Saran</h2>
            <p class="text-xs text-slate-500">Boleh memilih lebih dari satu jawaban pada opsi checkbox di bawah.</p>
        </div>

        <div class="space-y-6">
            <!-- F1: Fasilitas yang tersedia -->
            <div class="space-y-2.5">
                <label class="block text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <span class="text-emerald-700 font-mono">F1</span>
                    <span>Fasilitas pemilahan apa saja yang tersedia di sekitar Anda?</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach([
                        'Tempat sampah terpilah (organik/anorganik)',
                        'Komposter',
                        'Drop point khusus (elektronik, B3)',
                        'Tidak tahu / tidak memperhatikan'
                    ] as $item)
                        <button type="button" 
                                @click="toggleItem('facilities', '{{ $item }}')"
                                :class="facilities.includes('{{ $item }}') ? 'border-emerald-600 bg-emerald-50 text-emerald-900 font-semibold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                                class="p-3 rounded-xl border text-left text-xs transition flex items-center gap-2.5 cursor-pointer">
                            <span class="w-4 h-4 rounded border flex items-center justify-center text-[10px]"
                                  :class="facilities.includes('{{ $item }}') ? 'bg-emerald-600 border-emerald-600 text-white font-bold' : 'border-slate-300 bg-white text-transparent'">
                                ✓
                            </span>
                            <span class="leading-snug">{{ $item }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- B1: Hambatan Utama -->
            <div class="space-y-2.5">
                <label class="block text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <span class="text-emerald-700 font-mono">B1</span>
                    <span>Apa hambatan utama Anda dalam memilah sampah di kampus?</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach([
                        'Tidak tahu cara memilah yang benar',
                        'Tempat sampah terpilah terlalu jauh',
                        'Tidak ada waktu',
                        'Tidak tersedia tempat sampah terpilah',
                        'Malas / tidak terbiasa',
                        'Tidak ada hambatan'
                    ] as $item)
                        <button type="button" 
                                @click="toggleItem('barriers', '{{ $item }}')"
                                :class="barriers.includes('{{ $item }}') ? 'border-emerald-600 bg-emerald-50 text-emerald-900 font-semibold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                                class="p-3 rounded-xl border text-left text-xs transition flex items-center gap-2.5 cursor-pointer">
                            <span class="w-4 h-4 rounded border flex items-center justify-center text-[10px]"
                                  :class="barriers.includes('{{ $item }}') ? 'bg-emerald-600 border-emerald-600 text-white font-bold' : 'border-slate-300 bg-white text-transparent'">
                                ✓
                            </span>
                            <span class="leading-snug">{{ $item }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- B2: Faktor Motivasi -->
            <div class="space-y-2.5">
                <label class="block text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <span class="text-emerald-700 font-mono">B2</span>
                    <span>Apa yang paling memotivasi Anda untuk memilah sampah?</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach([
                        'Kesadaran lingkungan',
                        'Aturan/kebijakan kampus',
                        'Ajakan teman/dosen',
                        'Reward / insentif',
                        'Tidak ada yang memotivasi'
                    ] as $item)
                        <button type="button" 
                                @click="toggleItem('motivations', '{{ $item }}')"
                                :class="motivations.includes('{{ $item }}') ? 'border-emerald-600 bg-emerald-50 text-emerald-900 font-semibold ring-2 ring-emerald-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
                                class="p-3 rounded-xl border text-left text-xs transition flex items-center gap-2.5 cursor-pointer">
                            <span class="w-4 h-4 rounded border flex items-center justify-center text-[10px]"
                                  :class="motivations.includes('{{ $item }}') ? 'bg-emerald-600 border-emerald-600 text-white font-bold' : 'border-slate-300 bg-white text-transparent'">
                                ✓
                            </span>
                            <span class="leading-snug">{{ $item }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Kritik, Saran & Masukan -->
            <div class="space-y-1.5 pt-2 border-t border-slate-100">
                <label class="block text-xs font-bold text-slate-800">
                    Saran & Masukan untuk Pengelolaan Sampah Kampus UAD <span class="text-slate-400 font-normal">(Opsional)</span>
                </label>
                <textarea wire:model.defer="feedback" rows="3" placeholder="Tuliskan saran atau kendala fasilitas pemilahan yang Anda alami..." class="w-full rounded-xl border border-slate-200 p-3 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"></textarea>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <button type="button" wire:click="prevStep" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                &larr; Kembali
            </button>
            <button type="button" wire:click="nextStep" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-2 cursor-pointer">
                <span>Review & Kirim &rarr;</span>
            </button>
        </div>
    </div>

    <!-- ═══ STEP 7: REVIEW & KONFIRMASI ═══ -->
    <div x-show="curStep === 7" x-cloak class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-2xs space-y-6">
        <div class="text-center p-6 bg-emerald-50 border border-emerald-200/80 rounded-2xl space-y-2">
            <div class="text-3xl">✅</div>
            <h3 class="text-base sm:text-lg font-bold text-emerald-900">Survei Siap Dikirim</h3>
            <p class="text-xs text-slate-600 max-w-md mx-auto">
                Periksa kembali data Anda. Seluruh 33 pertanyaan telah terisi. Jawaban Anda akan membantu percepatan kampus hijau ramah lingkungan di UAD.
            </p>
        </div>

        <!-- 6 Dimensi Grid Ringkasan Pertanyaan -->
        <div class="grid grid-cols-2 sm:grid-cols-6 gap-2.5 text-center">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                <span class="block text-base font-bold text-slate-900">6</span>
                <span class="text-[10px] text-slate-500 font-semibold uppercase">Demografi</span>
            </div>
            <div class="p-3 rounded-xl bg-sky-50 border border-sky-100">
                <span class="block text-base font-bold text-sky-800">8</span>
                <span class="text-[10px] text-sky-700 font-semibold uppercase">Knowledge</span>
            </div>
            <div class="p-3 rounded-xl bg-teal-50 border border-teal-100">
                <span class="block text-base font-bold text-teal-800">6</span>
                <span class="text-[10px] text-teal-700 font-semibold uppercase">Attitude</span>
            </div>
            <div class="p-3 rounded-xl bg-amber-50 border border-amber-100">
                <span class="block text-base font-bold text-amber-800">6</span>
                <span class="text-[10px] text-amber-700 font-semibold uppercase">Practice</span>
            </div>
            <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-100">
                <span class="block text-base font-bold text-indigo-800">5</span>
                <span class="text-[10px] text-indigo-700 font-semibold uppercase">Kepuasan</span>
            </div>
            <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100">
                <span class="block text-base font-bold text-emerald-800">3</span>
                <span class="text-[10px] text-emerald-700 font-semibold uppercase">Fasilitas</span>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <button type="button" wire:click="prevStep" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                &larr; Cek Jawaban Sebelumnya
            </button>
            <button type="button" 
                    wire:click="submitSurvey"
                    wire:loading.attr="disabled"
                    class="px-8 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-md shadow-emerald-600/20 transition flex items-center gap-2 cursor-pointer disabled:opacity-50">
                <span wire:loading.remove>Kirim Survei Sekarang ✓</span>
                <span wire:loading>Memproses Jawaban...</span>
            </button>
        </div>
    </div>

    <!-- ═══ STEP 8: HASIL & TERIMA KASIH (SELESAI) ═══ -->
    <div x-show="curStep === 8" x-cloak class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-2xs space-y-6 text-center">
        @if($submittedSurvey)
            <div class="max-w-md mx-auto space-y-3">
                <div class="text-5xl">🎉</div>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900">Terima Kasih Banyak!</h2>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    Jawaban Anda telah berhasil dicatat. Kontribusi Anda sangat bernilai dalam mewujudkan kampus Universitas Ahmad Dahlan yang mandiri dan bersih.
                </p>
                <div class="text-[11px] font-mono text-slate-400">
                    Respons ID #{{ $submittedSurvey->id }} &bull; {{ $submittedSurvey->campus->name }} &bull; {{ $submittedSurvey->survey_date->format('d M Y') }}
                </div>
            </div>

            <!-- Score Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-lg mx-auto pt-2">
                <div class="p-3 bg-sky-50 rounded-xl border border-sky-100 text-center">
                    <span class="block text-[10px] text-sky-800 font-semibold uppercase">Knowledge</span>
                    <span class="font-mono text-lg font-black text-sky-800">{{ number_format($submittedSurvey->knowledge_score, 0) }}%</span>
                </div>
                <div class="p-3 bg-teal-50 rounded-xl border border-teal-100 text-center">
                    <span class="block text-[10px] text-teal-800 font-semibold uppercase">Attitude</span>
                    <span class="font-mono text-lg font-black text-teal-800">{{ number_format($submittedSurvey->attitude_score, 0) }}%</span>
                </div>
                <div class="p-3 bg-amber-50 rounded-xl border border-amber-100 text-center">
                    <span class="block text-[10px] text-amber-800 font-semibold uppercase">Practice</span>
                    <span class="font-mono text-lg font-black text-amber-800">{{ number_format($submittedSurvey->practice_score, 0) }}%</span>
                </div>
                <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100 text-center">
                    <span class="block text-[10px] text-emerald-800 font-semibold uppercase">Skor KAP</span>
                    <span class="font-mono text-lg font-black text-emerald-800">{{ number_format($submittedSurvey->overall_score, 1) }}</span>
                </div>
            </div>

            <div class="pt-4 max-w-xs mx-auto">
                <button type="button" 
                        wire:click="resetForm" 
                        class="w-full py-2.5 px-4 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                    Isi Survei Lainnya
                </button>
            </div>
        @endif
    </div>

</div>
