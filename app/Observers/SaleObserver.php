<?php

namespace App\Observers;

use App\Models\Sale;
use App\Services\LedgerService;

class SaleObserver
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Handle the Sale "created" event.
     * Auto-record Kredit (K) entry to Buku Kas & recalculate ledger.
     */
    public function created(Sale $sale): void
    {
        if ($sale->total_amount > 0) {
            $buyerName = $sale->buyer?->name ?? 'Mitra Pengepul';
            $this->ledgerService->recordTransaction(
                campusId: $sale->campus_id,
                date: $sale->sale_date,
                jenis: 'K', // Kredit = Pemasukan
                sumber: 'penjualan',
                nominal: (float) $sale->total_amount,
                keterangan: 'Penjualan sampah terpilah kepada ' . $buyerName,
                refId: $sale->id,
                createdBy: $sale->created_by
            );
        }
    }

    /**
     * Handle the Sale "deleted" event.
     * Auto-remove financial record and recalculate ledger.
     */
    public function deleted(Sale $sale): void
    {
        $this->ledgerService->deleteTransactionByRef('penjualan', $sale->id);
    }
}
