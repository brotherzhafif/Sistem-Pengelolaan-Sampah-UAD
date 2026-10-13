<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membersihkan data awal transaksi dummy/mock sekali jalan sesuai arahan BSI,
     * dengan tetap mempertahankan akun pengguna (users) dan seluruh master referensi.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $transactionTables = [
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

        foreach ($transactionTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: Data transaksi yang telah dibersihkan tidak dapat di-rollback
    }
};
