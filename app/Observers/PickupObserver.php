<?php

namespace App\Observers;

use App\Models\Pickup;
use App\Services\LedgerService;

class PickupObserver
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Handle the Pickup "created" event.
     * Auto-record Debet (D) entry to Buku Kas & recalculate daily ledger.
     */
    public function created(Pickup $pickup): void
    {
        if ($pickup->total_cost > 0) {
            $vendorName = $pickup->vendor?->name ?? 'Mitra Pengangkut';
            $this->ledgerService->recordTransaction(
                campusId: $pickup->campus_id,
                date: $pickup->pickup_date,
                jenis: 'D', // Debet = Pengeluaran / Biaya
                sumber: 'pengangkutan',
                nominal: (float) $pickup->total_cost,
                keterangan: 'Biaya pengangkutan residu sampah oleh ' . $vendorName . ' (' . number_format($pickup->volume_kg, 1, ',', '.') . ' kg)',
                refId: $pickup->id,
                createdBy: $pickup->created_by
            );
        }
    }

    /**
     * Handle the Pickup "deleted" event.
     * Auto-remove financial record and recalculate daily ledger.
     */
    public function deleted(Pickup $pickup): void
    {
        $this->ledgerService->deleteTransactionByRef('pengangkutan', $pickup->id);
    }
}
