<?php

namespace Database\Seeders;

use App\Models\Campus;
use Illuminate\Database\Seeder;

class CampusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $campuses = [
            [
                'name' => 'Kampus 1 UAD',
                'code' => 'KAMPUS-1',
                'address' => 'Jl. Kapas 9, Semaki, Umbulharjo, Kota Yogyakarta, D.I. Yogyakarta 55166',
                'is_active' => true,
            ],
            [
                'name' => 'Kampus 2 UAD',
                'code' => 'KAMPUS-2',
                'address' => 'Jl. Pramuka 42, Sidikan, Umbulharjo, Kota Yogyakarta, D.I. Yogyakarta 55161',
                'is_active' => true,
            ],
            [
                'name' => 'Kampus 3 UAD',
                'code' => 'KAMPUS-3',
                'address' => 'Jl. Prof. Dr. Soepomo, S.H., Janturan, Warungboto, Umbulharjo, Kota Yogyakarta 55164',
                'is_active' => true,
            ],
            [
                'name' => 'Kampus 4 UAD (Utama)',
                'code' => 'KAMPUS-4',
                'address' => 'Jl. Ringroad Selatan, Tamanan, Banguntapan, Bantul, D.I. Yogyakarta 55191',
                'is_active' => true,
            ],
            [
                'name' => 'Kampus 5 UAD',
                'code' => 'KAMPUS-5',
                'address' => 'Jl. Ki Ageng Pemanahan No. 19, Sorosutan, Umbulharjo, Kota Yogyakarta 55162',
                'is_active' => true,
            ],
            [
                'name' => 'Kampus 6 UAD',
                'code' => 'KAMPUS-6',
                'address' => 'Jl. Wates Km. 9.5, Argomulyo, Sedayu, Bantul, D.I. Yogyakarta 55752',
                'is_active' => true,
            ],
        ];

        foreach ($campuses as $campus) {
            Campus::updateOrCreate(
                ['code' => $campus['code']],
                $campus
            );
        }
    }
}

