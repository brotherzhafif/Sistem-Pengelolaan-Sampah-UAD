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

        $sampleRespondents = [
            // Responden 1 (Mahasiswa FTI Kampus 4 - Sangat Sadar Lingkungan)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => 'Fadhil Rahman',
                'nim' => '2100018021',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Teknologi Industri (Informatika)',
                'residence' => 'kos',
                'k' => [5, 5, 4, 5],
                'a' => [5, 5, 5, 4],
                'p' => [4, 5, 5, 4],
                'f' => [4, 4],
                'feedback' => 'Tempat sampah pilah di lantai 3 Gedung Utama perlu ditambah jumlahnya, sering penuh menjelang siang.',
                'days_ago' => 6,
            ],
            // Responden 2 (Mahasiswa FKM Kampus 3 - Cukup Baik)
            [
                'campus_code' => 'KAMPUS-3',
                'name' => 'Annisa Putri',
                'nim' => '2200029014',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Kesehatan Masyarakat',
                'residence' => 'asrama',
                'k' => [4, 4, 3, 4],
                'a' => [4, 4, 5, 4],
                'p' => [3, 4, 4, 3],
                'f' => [3, 3],
                'feedback' => 'Label warna tempat sampah kadang sudah pudar, tolong diperbarui stiker pemilahannya.',
                'days_ago' => 5,
            ],
            // Responden 3 (Dosen FK Kampus 4 - Sangat Baik)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => 'dr. Hendra Kurniawan, M.Kes',
                'nim' => '198504122010121002',
                'role' => 'dosen',
                'faculty' => 'Fakultas Kedokteran',
                'residence' => 'rumah_sendiri',
                'k' => [5, 5, 5, 5],
                'a' => [5, 5, 5, 5],
                'p' => [5, 5, 5, 4],
                'f' => [4, 5],
                'feedback' => 'Sistem bank sampah kampus sudah sangat bagus. Perlu sosialisasi berkala di awal semester.',
                'days_ago' => 4,
            ],
            // Responden 4 (Mahasiswa Farmasi Kampus 3 - Sedang)
            [
                'campus_code' => 'KAMPUS-3',
                'name' => 'Rizky Pratama',
                'nim' => '2300023055',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Farmasi',
                'residence' => 'kos',
                'k' => [4, 3, 3, 3],
                'a' => [4, 3, 4, 3],
                'p' => [3, 3, 3, 2],
                'f' => [2, 3],
                'feedback' => 'Kantin masih banyak memakai kantong kresek sekali pakai, mohon ada wadah pengganti.',
                'days_ago' => 4,
            ],
            // Responden 5 (Tenaga Kependidikan Kampus 1 - Sangat Baik)
            [
                'campus_code' => 'KAMPUS-1',
                'name' => 'Bambang Sudibyo, S.Kom',
                'nim' => '198901152015041001',
                'role' => 'tendik',
                'faculty' => 'Biro Sistem Informasi & Komunikasi',
                'residence' => 'rumah_sendiri',
                'k' => [5, 4, 4, 4],
                'a' => [5, 5, 4, 5],
                'p' => [4, 4, 4, 4],
                'f' => [4, 4],
                'feedback' => 'Pencatatan sampah kertas arsip kantor bisa diintegrasikan dengan bank sampah.',
                'days_ago' => 3,
            ],
            // Responden 6 (Mahasiswa FEB Kampus 1 - Kurang Sadar Lingkungan)
            [
                'campus_code' => 'KAMPUS-1',
                'name' => 'Dimas Wicaksono',
                'nim' => '2200011089',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Ekonomi dan Bisnis (Manajemen)',
                'residence' => 'kontrakan',
                'k' => [3, 2, 2, 2],
                'a' => [3, 3, 2, 2],
                'p' => [2, 2, 1, 2],
                'f' => [3, 2],
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
                'residence' => 'asrama',
                'k' => [5, 5, 4, 5],
                'a' => [5, 5, 5, 5],
                'p' => [4, 5, 5, 4],
                'f' => [5, 4],
                'feedback' => 'Gerakan membawa tumbler di asrama Persada sangat efektif, bisa diterapkan di seluruh kampus.',
                'days_ago' => 2,
            ],
            // Responden 8 (Mahasiswa Psikologi Kampus 2 - Sedang)
            [
                'campus_code' => 'KAMPUS-2',
                'name' => 'Nadia Salma',
                'nim' => '2300030012',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Psikologi',
                'residence' => 'kos',
                'k' => [4, 4, 3, 3],
                'a' => [4, 4, 4, 3],
                'p' => [3, 4, 3, 3],
                'f' => [3, 3],
                'feedback' => 'Perlu lebih banyak poster infografis cara memilah sampah di mading kampus.',
                'days_ago' => 2,
            ],
            // Responden 9 (Dosen FTI Kampus 4 - Sangat Baik)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => 'Ir. M. Ridwan, M.T.',
                'nim' => '197903102008011015',
                'role' => 'dosen',
                'faculty' => 'Fakultas Teknologi Industri',
                'residence' => 'rumah_sendiri',
                'k' => [5, 5, 5, 5],
                'a' => [5, 5, 5, 5],
                'p' => [5, 5, 5, 5],
                'f' => [5, 5],
                'feedback' => 'Apresiasi untuk tim pengelola TPS3R UAD yang aktif mencatat dan mengolah timbulan sampah.',
                'days_ago' => 1,
            ],
            // Responden 10 (Mahasiswa FKIP Kampus 5 - Sedang)
            [
                'campus_code' => 'KAMPUS-5',
                'name' => 'Zahra Aulia',
                'nim' => '2200005078',
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Keguruan dan Ilmu Pendidikan',
                'residence' => 'kos',
                'k' => [4, 4, 3, 3],
                'a' => [4, 4, 4, 4],
                'p' => [3, 4, 3, 3],
                'f' => [3, 4],
                'feedback' => 'Pengangkutan sampah di Kampus 5 tepat waktu dan bersih.',
                'days_ago' => 1,
            ],
            // Responden 11 (Anonim Mahasiswa Kampus 4 - Sedang)
            [
                'campus_code' => 'KAMPUS-4',
                'name' => null,
                'nim' => null,
                'role' => 'mahasiswa',
                'faculty' => 'Fakultas Hukum',
                'residence' => 'kos',
                'k' => [3, 4, 3, 3],
                'a' => [4, 4, 3, 3],
                'p' => [3, 3, 2, 2],
                'f' => [3, 3],
                'feedback' => null,
                'days_ago' => 0,
            ],
            // Responden 12 (Tendik Kampus 2 - Sangat Baik)
            [
                'campus_code' => 'KAMPUS-2',
                'name' => 'Tri Wahyuni',
                'nim' => '199208242018022001',
                'role' => 'tendik',
                'faculty' => 'Biro Administrasi Akademik',
                'residence' => 'rumah_sendiri',
                'k' => [5, 5, 4, 4],
                'a' => [5, 5, 5, 4],
                'p' => [4, 5, 4, 4],
                'f' => [4, 4],
                'feedback' => 'Sangat setuju jika kampus bebas kantong plastik dan botol minum plastik sekali pakai.',
                'days_ago' => 0,
            ],
        ];

        foreach ($sampleRespondents as $item) {
            $campus = Campus::where('code', $item['campus_code'])->first() ?? $campuses->first();

            $kScore = (array_sum($item['k']) / (count($item['k']) * 5)) * 100;
            $aScore = (array_sum($item['a']) / (count($item['a']) * 5)) * 100;
            $pScore = (array_sum($item['p']) / (count($item['p']) * 5)) * 100;
            $overall = round(($kScore + $aScore + $pScore) / 3, 2);

            KapSurvey::create([
                'campus_id' => $campus->id,
                'respondent_name' => $item['name'],
                'respondent_identifier' => $item['nim'],
                'respondent_role' => $item['role'],
                'faculty_unit' => $item['faculty'],
                'residence_type' => $item['residence'],
                'knowledge_responses' => $item['k'],
                'attitude_responses' => $item['a'],
                'practice_responses' => $item['p'],
                'facility_responses' => $item['f'],
                'knowledge_score' => $kScore,
                'attitude_score' => $aScore,
                'practice_score' => $pScore,
                'overall_score' => $overall,
                'category' => KapSurvey::determineCategory($overall),
                'feedback' => $item['feedback'],
                'survey_date' => Carbon::today()->subDays($item['days_ago'])->format('Y-m-d'),
            ]);
        }
    }
}
