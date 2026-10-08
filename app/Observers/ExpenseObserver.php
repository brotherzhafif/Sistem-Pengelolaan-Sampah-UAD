<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\LedgerService;

class ExpenseObserver
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Handle the Expense "created" event.
     * Auto-record Debet (D) entry to Buku Kas & recalculate daily ledger.
     */
    public function created(Expense $expense): void
    {
        if ($expense->amount > 0) {
            $categoryName = $expense->category?->name ?? 'Operasional Umum';
            $this->ledgerService->recordTransaction(
                campusId: $expense->campus_id,
                date: $expense->expense_date,
                jenis: 'D', // Debet = Pengeluaran / Biaya
                sumber: 'operasional',
                nominal: (float) $expense->amount,
                keterangan: 'Pengeluaran Operasional [' . $categoryName . ']: ' . ($expense->description ?: 'Tanpa keterangan'),
                refId: $expense->id,
                createdBy: $expense->created_by
            );
        }
    }

    /**
     * Handle the Expense "deleted" event.
     * Auto-remove financial record and recalculate daily ledger.
     */
    public function deleted(Expense $expense): void
    {
        $this->ledgerService->deleteTransactionByRef('operasional', $expense->id);
    }
}
