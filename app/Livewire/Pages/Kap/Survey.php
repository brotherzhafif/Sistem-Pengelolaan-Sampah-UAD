<?php

namespace App\Livewire\Pages\Kap;

use App\Http\Controllers\KapController;
use App\Models\Campus;
use App\Models\KapSurvey;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Survey extends Component
{
    // Steps: 1 = Demografi, 2 = Knowledge, 3 = Attitude, 4 = Practice, 5 = Satisfaction, 6 = Fasilitas, 7 = Review, 8 = Selesai
    public int $currentStep = 1;
    public bool $isSubmitted = false;
    public ?KapSurvey $submittedSurvey = null;

    // Anti-Spam: Layer 1 (Unique Device Lock) & Layer 2 (Interactive CAPTCHA)
    public ?string $deviceId = null;
    public bool $deviceAlreadySubmitted = false;
    public ?int $captchaNum1 = null;
    public ?int $captchaNum2 = null;
    public ?int $captchaAnswer = null;
    public string $captchaInput = '';

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
        $this->generateCaptcha();
    }

    /**
     * Generate soal matematika sederhana untuk CAPTCHA (Layer 2).
     */
    public function generateCaptcha(): void
    {
        $this->captchaNum1 = rand(3, 15);
        $this->captchaNum2 = rand(2, 9);
        $this->captchaAnswer = $this->captchaNum1 + $this->captchaNum2;
        $this->captchaInput = '';
    }

    /**
     * Memperbarui soal CAPTCHA saat pengguna meminta ganti soal.
     */
    public function refreshCaptcha(): void
    {
        $this->generateCaptcha();
        $this->resetErrorBag('captchaInput');
    }

    /**
     * Menerima ID Unik Perangkat dari client-side browser (Layer 1).
     */
    public function checkDeviceSubmission(?string $deviceId): void
    {
        $this->deviceId = $deviceId ? strip_tags(trim($deviceId)) : null;
        if ($this->deviceId && KapSurvey::hasDeviceSubmittedToday($this->deviceId)) {
            $this->deviceAlreadySubmitted = true;
        }
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

        // 1. Verifikasi Device Lock (Layer 1 Anti-Spam)
        if ($this->deviceAlreadySubmitted || ($this->deviceId && KapSurvey::hasDeviceSubmittedToday($this->deviceId))) {
            $this->deviceAlreadySubmitted = true;
            $this->addError('device', 'Perangkat ini sudah digunakan untuk mengisi survei hari ini. Batas pengisian adalah 1 kali per perangkat per hari.');
            $this->dispatch('toast', message: 'Perangkat ini sudah digunakan mengisi survei hari ini.', type: 'error');
            return;
        }

        // 2. Verifikasi CAPTCHA Interaktif (Layer 2 Anti-Spam)
        $cleanInput = trim($this->captchaInput);
        if ($cleanInput === '' || (int) $cleanInput !== $this->captchaAnswer) {
            $this->addError('captchaInput', 'Jawaban verifikasi keamanan (CAPTCHA) belum tepat. Silakan hitung kembali.');
            $this->generateCaptcha();
            return;
        }

        $result = app(KapController::class)->submitSurvey([
            'campus_id' => $this->campus_id,
            'respondent_name' => $this->respondent_name,
            'respondent_identifier' => $this->respondent_identifier,
            'respondent_role' => $this->respondent_role,
            'faculty_unit' => $this->faculty_unit,
            'gender' => $this->gender,
            'has_attended_training' => $this->has_attended_training,
            'is_willing_volunteer' => $this->is_willing_volunteer,
            'residence_type' => $this->residence_type,
            'device_id' => $this->deviceId,
            'knowledge' => $this->knowledge,
            'attitude' => $this->attitude,
            'practice' => $this->practice,
            'satisfaction' => $this->satisfaction,
            'facilities' => $this->facilities,
            'barriers' => $this->barriers,
            'motivations' => $this->motivations,
            'feedback' => $this->feedback,
        ]);

        if (empty($result['success'])) {
            $this->addError('device', $result['message']);
            $this->dispatch('toast', message: $result['message'], type: 'error');
            return;
        }

        $this->deviceAlreadySubmitted = true;
        $this->submittedSurvey = $result['survey'];
        $this->isSubmitted = true;
        $this->currentStep = 8;

        $this->dispatch('toast', message: $result['message'], type: 'success');
    }

    public function resetForm(): void
    {
        $this->isSubmitted = false;
        $this->submittedSurvey = null;
        $this->currentStep = 1;
        $this->feedback = '';
    }

    public function render()
    {
        return view('livewire.pages.kap.survey', [
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
        ]);
    }
}
