<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\WasteStockSummary;
use Illuminate\Console\Command;

class SyncWasteStockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'waste:sync-stock {--campus= : ID unit kampus tertentu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghitung ulang dan menyinkronkan tabel rekapitulasi stok sampah (waste_stock_summaries) dari data transaksi historis';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $campusId = $this->option('campus');

        if ($campusId) {
            $campus = Campus::find($campusId);
            if (!$campus) {
                $this->error("Unit kampus dengan ID {$campusId} tidak ditemukan.");
                return 1;
            }

            $this->info("Menyinkronkan rekap stok untuk kampus: {$campus->name} (ID: {$campus->id})...");
            WasteStockSummary::recalculateForCampus($campus->id);
            $this->info("✓ Berhasil disinkronkan.");
        } else {
            $this->info("Menyinkronkan rekap stok untuk seluruh unit kampus...");
            WasteStockSummary::syncAllCampuses();
            $this->info("✓ Seluruh unit kampus berhasil disinkronkan.");
        }

        $this->newLine();
        $this->displaySummaryTable();

        return 0;
    }

    /**
     * Menampilkan tabel hasil rekapitulasi stok seluruh kampus.
     */
    protected function displaySummaryTable(): void
    {
        $summaries = WasteStockSummary::with('campus')->get();

        if ($summaries->isEmpty()) {
            $this->warn('Belum ada data rekapitulasi stok.');
            return;
        }

        $headers = [
            'ID',
            'Unit Kampus',
            'Total Masuk (Kg)',
            'Terjual (Kg)',
            'Pendapatan Jual (Rp)',
            'Residu Diangkut (Kg)',
            'Biaya Angkut (Rp)',
            'Stok Siap Jual (Kg)',
            'Potensi Jual (Rp)',
            'Sisa Residu (Kg)',
            'Total Sisa (Kg)'
        ];
        $rows = [];

        foreach ($summaries as $s) {
            $rows[] = [
                $s->id,
                $s->campus?->name ?? "Kampus #{$s->campus_id}",
                number_format((float) $s->total_weighed_kg, 1, ',', '.'),
                number_format((float) $s->total_sold_kg, 1, ',', '.'),
                'Rp ' . number_format((float) $s->total_sales_revenue, 0, ',', '.'),
                number_format((float) $s->total_picked_up_kg, 1, ',', '.'),
                'Rp ' . number_format((float) $s->total_pickup_cost, 0, ',', '.'),
                number_format((float) $s->sellable_stock_kg, 1, ',', '.'),
                'Rp ' . number_format((float) $s->potential_revenue, 0, ',', '.'),
                number_format((float) $s->residual_stock_kg, 1, ',', '.'),
                number_format((float) $s->current_stock_kg, 1, ',', '.'),
            ];
        }

        $this->table($headers, $rows);
    }
}
