<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $renames = [
            'Pembelian Plastik / Bagor / Karung' => 'Bagor Karung',
            'Upah Pilah TPS' => 'Upah Pilah',
            'Pembelian Alat (Sekop, Cangkul, Sarung Tangan)' => 'Pembelian Alat',
            'Pakan Ternak / Maggot' => 'Pakan Maggot',
            'BBM Operasional Mesin Cacah' => 'BBM Operasional',
            'Makan Minum Tenaga TPS' => 'Makan Minum',
            'Material Kandang & Komposter' => 'Material Komposter',
            'Obat & P3K Petugas' => 'Obat P3K',
        ];

        foreach ($renames as $old => $new) {
            DB::table('expense_categories')
                ->where('name', $old)
                ->update(['name' => $new]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revert needed
    }
};
