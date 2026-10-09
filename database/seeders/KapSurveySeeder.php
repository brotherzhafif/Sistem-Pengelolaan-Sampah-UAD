<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\KapSurvey;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class KapSurveySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $campuses = Campus::all();
        if ($campuses->isEmpty()) {
            return;
        }

        // Kunci Jawaban Resmi Pengetahuan (K1 - K8)
        $knowledgeKeys = [
            1 => true,  // K1: Sampah organik bisa dikompos (Benar)
            2 => false, // K2: Semua plastik bisa didaur ulang tanpa syarat (Salah)
            3 => true,  // K3: Sampah tercampur minyak/kimia termasuk residu (Benar)
            4 => true,  // K4: Mengetahui sistem pemilahan di kampus (Benar)
            5 => true,  // K5: Styrofoam dan kain sulit diproses daur ulang (Benar)
            6 => true,  // K6: Tahu ke mana membuang sampah sesuai label (Benar)
            7 => false, // K7: Sampah elektronik bisa dibuang bersama sampah biasa (Salah)
            8 => true,  // K8: Kertas/kardus basah tidak bisa didaur ulang (Benar)
        ];

        $sampleRespondents = [
            // Responden 1 (Mahasiswa FTI Kampus 4 - Sangat Sadar Lingkungan)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => 'Fadhil Rahman',
                'nim' => '2100018021',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Teknologi Industri',
                'gender' => 'Laki-laki',
                'has_attended_training' => true,
                'is_willing_volunteer' => true,
                'residence' => 'kos',
                'k' => [1 => true, 2 => false, 3 => true, 4 => true, 5 => true, 6 => true, 7 => false, 8 => true], // 8/8 (100%)
                'a' => [5, 5, 5, 5, 4, 5], // 29/30
                'p' => [5, 5, 5, 4, 5, 4], // 28/30
                's' => [4, 4, 4, 4, 4],    // 20/25
                'f' => ['Tempat sampah terpilah (organik/anorganik)', 'Drop point khusus (elektronik, B3)'],
                'b' => [
                    'barriers' => ['Tempat sampah terpilah terlalu jauh'],
                    'motivations' => ['Kesadaran lingkungan', 'Aturan/kebijakan kampus'],
                ],
                'feedback' => 'Tempat sampah pilah di lantai 3 Gedung Utama Kampus 4 perlu ditambah jumlahnya, sering penuh menjelang siang.',
                'days_ago' => 6,
            ],
            // Responden 2 (Mahasiswa FKM/Farmasi Kampus 3 - Cukup Baik)
            [
                'campus_code' => 'KAMPUS-3',
                'name' => 'Annisa Putri',
                'nim' => '2200029014',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Farmasi',
                'gender' => 'Perempuan',
                'has_attended_training' => true,
                'is_willing_volunteer' => false,
                'residence' => 'asrama',
                'k' => [1 => true, 2 => false, 3 => true, 4 => true, 5 => true, 6 => true, 7 => true, 8 => true], // 7/8 (87.5%)
                'a' => [4, 4, 4, 5, 3, 4], // 24/30
                'p' => [3, 4, 4, 3, 4, 3], // 21/30
                's' => [3, 3, 3, 3, 3],    // 15/25
                'f' => ['Tempat sampah terpilah (organik/anorganik)', 'Komposter'],
                'b' => [
                    'barriers' => ['Tidak tahu cara memilah yang benar'],
                    'motivations' => ['Kesadaran lingkungan'],
                ],
                'feedback' => 'Label warna tempat sampah kadang sudah pudar, tolong diperbarui stiker pemilahannya.',
                'days_ago' => 5,
            ],
            // Responden 3 (Dosen FK/MIPA Kampus 4 - Sangat Baik)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => 'dr. Hendra Kurniawan, M.Kes',
                'nim' => '198504122010121002',
                'role' => 'dosen',
                'faculty' => 'Fakultas MIPA',
                'gender' => 'Laki-laki',
                'has_attended_training' => true,
                'is_willing_volunteer' => true,
                'residence' => 'rumah_sendiri',
                'k' => [1 => true, 2 => false, 3 => true, 4 => true, 5 => true, 6 => true, 7 => false, 8 => true], // 8/8 (100%)
                'a' => [5, 5, 5, 5, 5, 5], // 30/30
                'p' => [5, 5, 5, 4, 5, 5], // 29/30
                's' => [5, 4, 4, 4, 5],    // 22/25
                'f' => ['Tempat sampah terpilah (organik/anorganik)', 'Drop point khusus (elektronik, B3)', 'Komposter'],
                'b' => [
                    'barriers' => ['Tidak ada hambatan'],
                    'motivations' => ['Kesadaran lingkungan', 'Aturan/kebijakan kampus', 'Ajakan teman/dosen'],
                ],
                'feedback' => 'Sistem bank sampah kampus sudah sangat bagus. Perlu sosialisasi berkala di awal semester.',
                'days_ago' => 4,
            ],
            // Responden 4 (Mahasiswa Psikologi Kampus 2 - Sedang)
            [
                'campus_code' => 'KAMPUS-2',
                'name' => 'Rizky Pratama',
                'nim' => '2300023055',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Psikologi',
                'gender' => 'Laki-laki',
                'has_attended_training' => false,
                'is_willing_volunteer' => false,
                'residence' => 'kos',
                'k' => [1 => true, 2 => true, 3 => true, 4 => false, 5 => true, 6 => true, 7 => true, 8 => false], // 4/8 (50%)
                'a' => [4, 3, 3, 4, 3, 4], // 21/30
                'p' => [3, 3, 3, 2, 3, 2], // 16/30
                's' => [2, 3, 2, 2, 3],    // 12/25
                'f' => ['Tempat sampah terpilah (organik/anorganik)'],
                'b' => [
                    'barriers' => ['Tempat sampah terpilah terlalu jauh', 'Tidak ada waktu'],
                    'motivations' => ['Reward / insentif'],
                ],
                'feedback' => 'Kantin masih banyak memakai kantong kresek sekali pakai, mohon ada wadah alternatif.',
                'days_ago' => 4,
            ],
            // Responden 5 (Tenaga Kependidikan Kampus 1 - Sangat Baik)
            [
                'campus_code' => 'KAMPUS-1',
                'name' => 'Bambang Sudibyo, S.Kom',
                'nim' => '198901152015041001',
                'role' => 'tendik',
                'faculty' => 'Unit/Biro lainnya',
                'gender' => 'Laki-laki',
                'has_attended_training' => true,
                'is_willing_volunteer' => true,
                'residence' => 'rumah_sendiri',
                'k' => [1 => true, 2 => false, 3 => true, 4 => true, 5 => true, 6 => true, 7 => false, 8 => true], // 8/8
                'a' => [5, 5, 4, 5, 4, 5], // 28/30
                'p' => [4, 4, 4, 4, 5, 4], // 25/30
                's' => [4, 4, 4, 4, 4],    // 20/25
                'f' => ['Tempat sampah terpilah (organik/anorganik)', 'Drop point khusus (elektronik, B3)'],
                'b' => [
                    'barriers' => ['Tidak ada hambatan'],
                    'motivations' => ['Kesadaran lingkungan', 'Aturan/kebijakan kampus'],
                ],
                'feedback' => 'Pencatatan sampah kertas arsip kantor bisa diintegrasikan dengan bank sampah.',
                'days_ago' => 3,
            ],
            // Responden 6 (Mahasiswa FEB Kampus 1 - Perlu Peningkatan)
            [
                'campus_code' => 'KAMPUS-1',
                'name' => 'Dimas Wicaksono',
                'nim' => '2200011089',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Ekonomi & Bisnis',
                'gender' => 'Laki-laki',
                'has_attended_training' => false,
                'is_willing_volunteer' => false,
                'residence' => 'kontrakan',
                'k' => [1 => true, 2 => true, 3 => false, 4 => false, 5 => false, 6 => false, 7 => true, 8 => false], // 1/8 (12.5%)
                'a' => [3, 2, 2, 3, 2, 3], // 15/30
                'p' => [2, 2, 1, 2, 1, 1], // 9/30
                's' => [2, 2, 2, 1, 2],    // 9/25
                'f' => ['Tidak tahu / tidak memperhatikan'],
                'b' => [
                    'barriers' => ['Malas / tidak terbiasa', 'Tidak tahu cara memilah yang benar'],
                    'motivations' => ['Tidak ada yang memotivasi'],
                ],
                'feedback' => 'Terkadang buru-buru jadi buang sampah di tempat sampah terdekat saja tanpa memilah.',
                'days_ago' => 3,
            ],
            // Responden 7 (Mahasiswa FAI Kampus 4 - Sangat Baik)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => 'Siti Nurhaliza',
                'nim' => '2100008044',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Agama Islam',
                'gender' => 'Perempuan',
                'has_attended_training' => true,
                'is_willing_volunteer' => true,
                'residence' => 'asrama',
                'k' => [1 => true, 2 => false, 3 => true, 4 => true, 5 => true, 6 => true, 7 => false, 8 => true], // 8/8
                'a' => [5, 5, 5, 5, 5, 5],
                'p' => [5, 5, 5, 4, 5, 4],
                's' => [5, 5, 4, 4, 5],
                'f' => ['Tempat sampah terpilah (organik/anorganik)', 'Komposter'],
                'b' => [
                    'barriers' => ['Tidak ada hambatan'],
                    'motivations' => ['Kesadaran lingkungan', 'Aturan/kebijakan kampus'],
                ],
                'feedback' => 'Gerakan membawa tumbler di asrama Persada sangat efektif, bisa diterapkan di seluruh kampus.',
                'days_ago' => 2,
            ],
            // Responden 8 (Tenaga Outsourcing Kampus 4 - Sangat Paham Lapangan)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => 'Sutrisno',
                'nim' => null,
                'role' => 'outsourcing',
                'faculty' => 'Unit/Biro lainnya',
                'gender' => 'Laki-laki',
                'has_attended_training' => true,
                'is_willing_volunteer' => true,
                'residence' => 'rumah_sendiri',
                'k' => [1 => true, 2 => false, 3 => true, 4 => true, 5 => true, 6 => true, 7 => false, 8 => true], // 8/8
                'a' => [5, 5, 5, 5, 4, 5],
                'p' => [5, 5, 4, 4, 4, 5],
                's' => [4, 4, 4, 4, 4],
                'f' => ['Tempat sampah terpilah (organik/anorganik)', 'Drop point khusus (elektronik, B3)', 'Komposter'],
                'b' => [
                    'barriers' => ['Tidak ada hambatan'],
                    'motivations' => ['Aturan/kebijakan kampus', 'Kesadaran lingkungan'],
                ],
                'feedback' => 'Banyak mahasiswa yang masih buang plastik berisi sisa minuman manis ke tempat sampah anorganik.',
                'days_ago' => 2,
            ],
            // Responden 9 (Dosen Hukum Kampus 4 - Sangat Baik)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => 'Dr. Rahmat Hidayat, S.H., M.H.',
                'nim' => '197903102008011015',
                'role' => 'dosen',
                'faculty' => 'Fakultas Hukum',
                'gender' => 'Laki-laki',
                'has_attended_training' => true,
                'is_willing_volunteer' => false,
                'residence' => 'rumah_sendiri',
                'k' => [1 => true, 2 => false, 3 => true, 4 => true, 5 => true, 6 => true, 7 => false, 8 => true], // 8/8
                'a' => [5, 5, 5, 5, 5, 5],
                'p' => [5, 5, 5, 5, 5, 4],
                's' => [5, 5, 5, 4, 5],
                'f' => ['Tempat sampah terpilah (organik/anorganik)', 'Drop point khusus (elektronik, B3)'],
                'b' => [
                    'barriers' => ['Tidak ada hambatan'],
                    'motivations' => ['Kesadaran lingkungan', 'Aturan/kebijakan kampus'],
                ],
                'feedback' => 'Apresiasi untuk tim pengelola TPS3R UAD yang aktif mencatat dan mengolah timbulan sampah.',
                'days_ago' => 1,
            ],
            // Responden 10 (Mahasiswa FKIP Kampus 5 - Sedang)
            [
                'campus_code' => 'KAMPUS-5',
                'name' => 'Zahra Aulia',
                'nim' => '2200005078',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Keguruan & Ilmu Pendidikan',
                'gender' => 'Perempuan',
                'has_attended_training' => true,
                'is_willing_volunteer' => true,
                'residence' => 'kos',
                'k' => [1 => true, 2 => false, 3 => true, 4 => true, 5 => true, 6 => true, 7 => true, 8 => true], // 7/8
                'a' => [4, 4, 4, 5, 4, 4],
                'p' => [4, 4, 4, 3, 4, 3],
                's' => [3, 4, 4, 3, 4],
                'f' => ['Tempat sampah terpilah (organik/anorganik)'],
                'b' => [
                    'barriers' => ['Tempat sampah terpilah terlalu jauh'],
                    'motivations' => ['Kesadaran lingkungan', 'Ajakan teman/dosen'],
                ],
                'feedback' => 'Pengangkutan sampah di Kampus 5 tepat waktu dan bersih.',
                'days_ago' => 1,
            ],
            // Responden 11 (Anonim Mahasiswa Kampus 4 - Sedang)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => null,
                'nim' => null,
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Sastra, Budaya & Komunikasi',
                'gender' => 'Perempuan',
                'has_attended_training' => false,
                'is_willing_volunteer' => false,
                'residence' => 'kos',
                'k' => [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => true, 7 => true, 8 => true], // 6/8
                'a' => [4, 4, 3, 4, 3, 4],
                'p' => [3, 3, 3, 2, 3, 2],
                's' => [3, 3, 3, 2, 3],
                'f' => ['Tempat sampah terpilah (organik/anorganik)'],
                'b' => [
                    'barriers' => ['Tidak ada waktu'],
                    'motivations' => ['Ajakan teman/dosen'],
                ],
                'feedback' => null,
                'days_ago' => 0,
            ],
            // Responden 12 (Tendik Kampus 2 - Sangat Baik)
            [
                'campus_code' => 'KAMPUS-2',
                'name' => 'Tri Wahyuni',
                'nim' => '199208242018022001',
                'role' => 'tendik',
                'faculty' => 'Unit/Biro lainnya',
                'gender' => 'Perempuan',
                'has_attended_training' => true,
                'is_willing_volunteer' => true,
                'residence' => 'rumah_sendiri',
                'k' => [1 => true, 2 => false, 3 => true, 4 => true, 5 => true, 6 => true, 7 => false, 8 => true], // 8/8
                'a' => [5, 5, 5, 5, 4, 5],
                'p' => [4, 5, 4, 4, 5, 4],
                's' => [4, 4, 4, 4, 4],
                'f' => ['Tempat sampah terpilah (organik/anorganik)', 'Drop point khusus (elektronik, B3)'],
                'b' => [
                    'barriers' => ['Tidak ada hambatan'],
                    'motivations' => ['Kesadaran lingkungan', 'Aturan/kebijakan kampus'],
                ],
                'feedback' => 'Sangat setuju jika kampus bebas kantong plastik dan botol minum plastik sekali pakai.',
                'days_ago' => 0,
            ],
        ];

        // Delete existing surveys to avoid duplication on repeated seed
        KapSurvey::query()->delete();

        // Generate additional realistic respondents across all campuses (total ~55 respondents)
        $names = [
            'Rizky Pratama', 'Siti Nurhaliza', 'Budi Utomo', 'Dewi Lestari', 'Ahmad Fauzi',
            'Mega Suryani', 'Eko Prasetyo', 'Nurfadilah', 'Hendra Setiawan', 'Anisa Rahmawati',
            'Ilham Kurniawan', 'Dina Mariana', 'Agus Supriyanto', 'Rina Wulandari', 'Dimas Anggara',
            'Tri Wahyuni', 'Danang Prasetya', 'Sri Wahyuningsih', 'Bambang Pamungkas', 'Lestari Handayani',
            'Fajar Hidayat', 'Maya Safitri', 'Bayu Aji', 'Ratna Sari', 'Yusuf Maulana',
            'Nurul Hidayah', 'Indra Gunawan', 'Fitri Handayani', 'Adi Nugroho', 'Endang Susilowati',
            'Wawan Setiawan', 'Retno Palupi', 'Wahyu Pratama', 'Haryanto', 'Tuti Alawiyah',
            'Surya Darma', 'Ika Nuraini', 'Gugun Gunawan', 'Kartika Putri', 'Joko Susilo',
            'Rudi Hartono', 'Sri Rahayu', 'Dwi Santoso'
        ];

        $faculties = [
            'Fakultas Teknologi Industri',
            'Fakultas Farmasi',
            'Fakultas Kedokteran',
            'Fakultas Keguruan dan Ilmu Pendidikan',
            'Fakultas Ekonomi dan Bisnis',
            'Fakultas Hukum',
            'Fakultas Sastra, Budaya, dan Komunikasi',
            'Fakultas Sains dan Teknologi Terapan',
            'Fakultas Kesehatan Masyarakat',
            'Fakultas Agama Islam',
            'Fakultas Psikologi',
            'Biro Administrasi Umum & Sarpras',
        ];

        foreach ($names as $idx => $name) {
            $campus = $campuses->get($idx % $campuses->count());
            $roleRand = rand(1, 100);
            $role = $roleRand <= 60 ? 'mahasiswa' : ($roleRand <= 75 ? 'dosen' : ($roleRand <= 90 ? 'tendik' : 'outsourcing'));
            $gender = ($idx % 2 === 0) ? 'Laki-laki' : 'Perempuan';
            $training = (rand(1, 10) <= 6);
            $volunteer = (rand(1, 10) <= 7);

            $kResponses = [];
            foreach ($knowledgeKeys as $qId => $expected) {
                // ~80% chance of answering correctly
                $kResponses[$qId] = (rand(1, 10) <= 8) ? $expected : !$expected;
            }

            // Attitude 1-5 (mostly positive 3-5)
            $aResponses = [rand(3, 5), rand(3, 5), rand(4, 5), rand(3, 5), rand(3, 5), rand(4, 5)];

            // Practice 1-5
            $pResponses = [rand(3, 5), rand(2, 5), rand(3, 5), rand(3, 5), rand(3, 5), rand(3, 5)];

            // Satisfaction 1-5
            $sResponses = [rand(3, 5), rand(3, 5), rand(3, 5), rand(3, 5), rand(3, 5)];

            $sampleRespondents[] = [
                'campus_code' => $campus->code,
                'name' => $name,
                'nim' => $role === 'mahasiswa' ? ('2' . rand(1, 3) . '000' . rand(10000, 99999)) : null,
                'role' => $role,
                'faculty' => $faculties[array_rand($faculties)],
                'gender' => $gender,
                'has_attended_training' => $training,
                'is_willing_volunteer' => $volunteer,
                'residence' => 'kos',
                'k' => $kResponses,
                'a' => $aResponses,
                'p' => $pResponses,
                's' => $sResponses,
                'f' => ['Tempat sampah terpilah (organik/anorganik)'],
                'b' => [
                    'barriers' => ['Kurangnya tempat sampah terpilah di titik tertentu'],
                    'motivations' => ['Kesadaran lingkungan'],
                ],
                'feedback' => 'Perbanyak tempat sampah terpilah di lorong kelas dan dekat kantin.',
                'days_ago' => rand(1, 28),
            ];
        }

        foreach ($sampleRespondents as $item) {
            $campus = Campus::where('code', $item['campus_code'])->first() ?? $campuses->first();

            // Hitung Knowledge Score berbasis Kunci Jawaban
            $correctCount = 0;
            foreach ($knowledgeKeys as $idx => $expected) {
                if (($item['k'][$idx] ?? null) === $expected) {
                    $correctCount++;
                }
            }
            $kScore = ($correctCount / 8) * 100;

            // Hitung Sikap (Attitude) Score (Max 30)
            $aScore = (array_sum($item['a']) / (count($item['a']) * 5)) * 100;

            // Hitung Perilaku (Practice) Score (Max 30)
            $pScore = (array_sum($item['p']) / (count($item['p']) * 5)) * 100;

            // Hitung Kepuasan (Satisfaction) Score (Max 25)
            $sScore = (array_sum($item['s']) / (count($item['s']) * 5)) * 100;

            // Komposit Skor KAP Utama (Knowledge, Attitude, Practice)
            $overall = round(($kScore + $aScore + $pScore) / 3, 2);

            KapSurvey::create([
                'campus_id' => $campus->id,
                'respondent_name' => $item['name'],
                'respondent_identifier' => $item['nim'],
                'respondent_role' => $item['role'],
                'faculty_unit' => $item['faculty'],
                'gender' => $item['gender'],
                'has_attended_training' => $item['has_attended_training'],
                'is_willing_volunteer' => $item['is_willing_volunteer'],
                'residence_type' => $item['residence'],
                'knowledge_responses' => $item['k'],
                'attitude_responses' => $item['a'],
                'practice_responses' => $item['p'],
                'satisfaction_responses' => $item['s'],
                'facility_responses' => $item['f'],
                'barrier_responses' => $item['b'],
                'knowledge_score' => $kScore,
                'attitude_score' => $aScore,
                'practice_score' => $pScore,
                'satisfaction_score' => $sScore,
                'overall_score' => $overall,
                'category' => KapSurvey::determineCategory($overall),
                'feedback' => $item['feedback'],
                'survey_date' => Carbon::today()->subDays($item['days_ago'])->format('Y-m-d'),
            ]);
        }
    }
}

