<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WipeTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:wipe-transactions {--force : Eksekusi tanpa dialog konfirmasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghapus seluruh data transaksi sampah, keuangan, dan survei KAP tanpa menghapus user dan master data referensi';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force')) {
            $confirmed = $this->confirm(
                'PERINGATAN: Perintah ini akan MENGHAPUS SEMUA DATA TRANSAKSI (penimbangan, penjualan, pengangkutan residu, pengeluaran, buku kas, buku besar, dan survei KAP).' . PHP_EOL .
                'Data akun login (users) dan master referensi (campuses, waste_types, vendors, dll.) TIDAK AKAN DIHAPUS.' . PHP_EOL .
                'Apakah Anda yakin ingin melanjutkan?',
                false
            );

            if (!$confirmed) {
                $this->warn('Operasi pembersihan dibatalkan.');
                return 0;
            }
        }

        $this->info('Memulai pembersihan data transaksi sampah & keuangan...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $tables = [
            'weighing_items',
            'weighing_sessions',
            'sale_items',
            'sales',
            'pickups',
            'expenses',
            'keuangan',
            'buku_besar',
            'kap_surveys',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  ✓ Tabel <comment>{$table}</comment> berhasil dikosongkan.");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->newLine();
        $this->info('✅ SELURUH DATA TRANSAKSI BERHASIL DIHAPUS!');
        $this->info('🔒 Akun Pengguna (users), Peran/Hak Akses (roles/permissions), dan Master Referensi (campuses, waste_types, vendors, buyers, expense_categories, waste_sources) TETAP AMAN.');

        return 0;
    }
}

