<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('financial_transactions')) {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                // Di MySQL/MariaDB, jika kolom sebelumnya adalah ENUM('K', 'D') atau CHAR(1)
                // dari skema legacy 'keuangan.jenis', ubah menjadi VARCHAR(20) agar dapat
                // menampung nilai 'credit' dan 'debit' tanpa memicu warning 1265 Data truncated.
                DB::statement("ALTER TABLE `financial_transactions` MODIFY `type` VARCHAR(20) NOT NULL");
                DB::statement("ALTER TABLE `financial_transactions` MODIFY `source` VARCHAR(50) NOT NULL");
            } else {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->string('type', 20)->change();
                    $table->string('source', 50)->change();
                });
            }

            // Normalisasi data jika masih ada nilai legacy 'K'/'D'
            DB::table('financial_transactions')->where('type', 'K')->update(['type' => 'credit']);
            DB::table('financial_transactions')->where('type', 'D')->update(['type' => 'debit']);
            DB::table('financial_transactions')->where('type', 'kredit')->update(['type' => 'credit']);
            DB::table('financial_transactions')->where('type', 'debet')->update(['type' => 'debit']);

            DB::table('financial_transactions')->where('source', 'penjualan')->update(['source' => 'sales']);
            DB::table('financial_transactions')->where('source', 'pengangkutan')->update(['source' => 'pickup']);
            DB::table('financial_transactions')->where('source', 'operasional')->update(['source' => 'operational']);
            DB::table('financial_transactions')->where('source', 'saldo_awal')->update(['source' => 'initial_balance']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('financial_transactions')) {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `financial_transactions` MODIFY `type` ENUM('credit', 'debit') NOT NULL");
                DB::statement("ALTER TABLE `financial_transactions` MODIFY `source` ENUM('sales', 'pickup', 'operational', 'initial_balance') NOT NULL");
            } else {
                Schema::table('financial_transactions', function (Blueprint $table) {
                    $table->enum('type', ['credit', 'debit'])->change();
                    $table->enum('source', ['sales', 'pickup', 'operational', 'initial_balance'])->change();
                });
            }
        }
    }
};
