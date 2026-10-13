<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. DATA WAJIB: Master Referensi & Akun Login (TETAP DIJALANKAN)
        $this->call([
            CampusSeeder::class,           // Master Kampus 1 - 6 UAD
            RolePermissionSeeder::class,   // Roles, Permissions & Akun Pengguna Demo/Staf
            MasterDataSeeder::class,       // 9 Jenis Sampah, Titik Sumber, Vendor, Pembeli, Kategori Biaya
        ]);

        // 2. DATA DUMMY TRANSAKSI (DINONAKTIFKAN SESUAI ARAHAN BSI / PAK AHMAD LUTHFI)
        // Aktifkan kembali baris di bawah ini HANYA jika ingin mengisi data dummy untuk keperluan testing/mock:
        /*
        $this->call([
            WeighingSeeder::class,     // Transaksi timbangan sampah
            SaleSeeder::class,         // Transaksi penjualan sampah
            PickupSeeder::class,       // Transaksi pengangkutan residu
            ExpenseSeeder::class,      // Transaksi pengeluaran operasional
            KapSurveySeeder::class,    // Data kuesioner survei KAP
        ]);
        */
    }
}
