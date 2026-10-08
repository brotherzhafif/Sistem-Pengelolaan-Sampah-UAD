<?php

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Campus;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use App\Models\WasteSource;
use App\Models\WasteType;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Seed initial master data based on SRS v2 & prototype ref.
     */
    public function run(): void
    {
        // 1. Seed 9 Kategori Granular Jenis Sampah (SRS M2 & M7)
        $wasteTypes = [
            [
                'name' => 'Organik Sisa Makanan',
                'category' => 'Organik',
                'default_price_per_kg' => 0.00,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Sampah Taman',
                'category' => 'Organik',
                'default_price_per_kg' => 0.00,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Kardus',
                'category' => 'Anorganik',
                'default_price_per_kg' => 1800.00,
                'is_sellable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Karton / Duplex',
                'category' => 'Anorganik',
                'default_price_per_kg' => 900.00,
                'is_sellable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Kertas HVS / Putih',
                'category' => 'Anorganik',
                'default_price_per_kg' => 2200.00,
                'is_sellable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Plastik Keras (HDPE/PP/PET)',
                'category' => 'Anorganik',
                'default_price_per_kg' => 3500.00,
                'is_sellable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Plastik Multilayer / Kresek',
                'category' => 'Anorganik',
                'default_price_per_kg' => 600.00,
                'is_sellable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Logam dan Kaca',
                'category' => 'Anorganik',
                'default_price_per_kg' => 4000.00,
                'is_sellable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Residu Campur',
                'category' => 'Residu',
                'default_price_per_kg' => 0.00,
                'is_sellable' => false,
                'is_active' => true,
            ],
        ];

        foreach ($wasteTypes as $type) {
            WasteType::updateOrCreate(['name' => $type['name']], $type);
        }

        // 2. Seed Vendor Pengangkut Residu (SRS M4)
        $vendors = [
            [
                'name' => 'Pasti Angkut Mitra Sleman',
                'contact' => '0812-3456-7890',
                'cost_per_kg' => 150.00,
                'is_active' => true,
            ],
            [
                'name' => 'DLH Kota Yogyakarta (TPS 3R)',
                'contact' => '0274-515865',
                'cost_per_kg' => 175.00,
                'is_active' => true,
            ],
        ];

        foreach ($vendors as $vendor) {
            Vendor::updateOrCreate(['name' => $vendor['name']], $vendor);
        }

        // 3. Seed Pembeli / Pengepul (SRS M3)
        $buyers = [
            [
                'name' => 'Pak Tono (Pengepul Kardus & Plastik)',
                'contact' => '0878-1122-3344',
            ],
            [
                'name' => 'UD Jaya Makmur Rongsok',
                'contact' => '0813-9988-7766',
            ],
            [
                'name' => 'CV Daur Sejahtera Yogyakarta',
                'contact' => '0857-4455-6677',
            ],
        ];

        foreach ($buyers as $buyer) {
            Buyer::updateOrCreate(['name' => $buyer['name']], $buyer);
        }

        // 4. Seed Kategori Pengeluaran Operasional (SRS M5)
        $categories = [
            'Upah Pilah TPS',
            'Makan Minum Tenaga TPS',
            'Pembelian Plastik / Bagor / Karung',
            'Pembelian Alat (Sekop, Cangkul, Sarung Tangan)',
            'Pakan Ternak / Maggot',
            'Material Kandang & Komposter',
            'Obat & P3K Petugas',
            'BBM Operasional Mesin Cacah',
        ];

        foreach ($categories as $categoryName) {
            ExpenseCategory::firstOrCreate(['name' => $categoryName]);
        }

        // 5. Seed Sumber Sampah Standar per Kampus (SRS M7)
        $campuses = Campus::all();
        $defaultSources = [
            'Area Taman & Halaman',
            'Kantin Utama',
            'Gedung Rektorat & Administrasi',
            'Fakultas / Ruang Kelas',
            'Laboratorium & Workshop',
            'Asrama Mahasiswa / Pesantren KH Ahmad Dahlan',
            'TPS3R Kampus',
        ];

        foreach ($campuses as $campus) {
            foreach ($defaultSources as $sourceName) {
                WasteSource::firstOrCreate([
                    'campus_id' => $campus->id,
                    'name' => $sourceName,
                ], [
                    'description' => "Sumber sampah di lingkungan {$campus->name}",
                    'is_active' => true,
                ]);
            }
        }
    }
}

