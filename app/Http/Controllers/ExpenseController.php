<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Expenses\Index as ExpenseComponent;
use App\Models\ActivityLog;
use App\Models\BukuBesar;
use App\Models\Campus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialTransaction;
use App\Models\GeneralLedger;
use App\Models\Keuangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    /**
     * Menampilkan halaman modul Pengeluaran Operasional TPS.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Pengeluaran.');
        }

        return app(ExpenseComponent::class)();
    }

    /**
     * Memvalidasi dan menyimpan transaksi pengeluaran operasional baru.
     */
    public function store(array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan', 'pengurus_bank_sampah', 'Pengurus Bank Sampah']) && !$user->can('expenses.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mencatat pengeluaran.');
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:100', 'max:100000000'],
            'description' => ['required', 'string', 'max:255'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'expense_category_id.required' => 'Kategori pengeluaran wajib dipilih.',
            'expense_date.required' => 'Tanggal pengeluaran wajib diisi.',
            'amount.required' => 'Nominal biaya wajib diisi.',
            'amount.min' => 'Nominal biaya minimal Rp 100.',
            'description.required' => 'Keterangan pengeluaran wajib diisi.',
        ])->validate();

        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $campusId = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;
        $amount = (float) $data['amount'];

        $expense = Expense::create([
            'campus_id' => $campusId,
            'expense_category_id' => (int) $data['expense_category_id'],
            'expense_date' => $data['expense_date'],
            'amount' => $amount,
            'description' => strip_tags(trim($data['description'])),
            'created_by' => auth()->id(),
        ]);

        ActivityLog::record(
            module: 'expenses',
            action: 'create',
            description: "Mencatat pengeluaran operasional (#{$expense->id}) senilai Rp " . number_format($amount, 0, ',', '.') . " - {$expense->description}",
            properties: [
                'expense_id' => $expense->id,
                'amount' => $amount,
                'expense_category_id' => (int) $data['expense_category_id'],
                'description' => $expense->description,
            ],
            campusId: $campusId
        );

        $newBalance = BukuBesar::getLatestBalance($campusId);
        $message = "Pengeluaran Rp " . number_format($amount, 0, ',', '.') . " tersimpan Saldo: Rp " . number_format($newBalance, 0, ',', '.');

        return [
            'success' => true,
            'expense' => $expense,
            'new_balance' => $newBalance,
            'message' => $message,
        ];
    }

    /**
     * Memvalidasi dan memperbarui data transaksi pengeluaran operasional.
     */
    public function update(int $expenseId, array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan', 'pengurus_bank_sampah', 'Pengurus Bank Sampah']) && !$user->can('expenses.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah data pengeluaran.');
        }

        $expense = Expense::findOrFail($expenseId);

        // Otorisasi kampus
        if ($user->campus_id && $user->campus_id !== $expense->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            throw ValidationException::withMessages([
                'editCampusId' => 'Anda tidak memiliki otoritas untuk mengubah data kampus ini.',
            ]);
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:100', 'max:100000000'],
            'description' => ['required', 'string', 'max:255'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'expense_category_id.required' => 'Kategori pengeluaran wajib dipilih.',
            'expense_date.required' => 'Tanggal pengeluaran wajib diisi.',
            'amount.required' => 'Nominal biaya wajib diisi.',
            'amount.min' => 'Nominal biaya minimal Rp 100.',
            'description.required' => 'Keterangan pengeluaran wajib diisi.',
        ])->validate();

        $bindingInfo = $expense->getBindingInfo();
        $targetCampusId = (int) $data['campus_id'];

        if ($bindingInfo['is_bound'] && $targetCampusId !== $expense->campus_id) {
            throw ValidationException::withMessages([
                'editCampusId' => 'Unit kampus tidak dapat diubah karena transaksi ini telah terikat dengan Jurnal Buku Kas.',
            ]);
        }

        $amount = (float) $data['amount'];
        $oldDate = $expense->expense_date?->format('Y-m-d');
        $newDate = $data['expense_date'];
        $cleanDesc = strip_tags(trim($data['description']));
        $categoryId = (int) $data['expense_category_id'];

        DB::transaction(function () use ($expense, $targetCampusId, $categoryId, $newDate, $amount, $cleanDesc, $oldDate) {
            $expense->update([
                'campus_id' => $targetCampusId,
                'expense_category_id' => $categoryId,
                'expense_date' => $newDate,
                'amount' => $amount,
                'description' => $cleanDesc,
            ]);

            // Sinkronisasi Transaksi Keuangan Buku Kas (Debet)
            $ft = FinancialTransaction::where('source', 'operational')->where('ref_id', $expense->id)->first();
            $categoryName = ExpenseCategory::find($categoryId)?->name ?? 'Operasional Umum';
            $ftDesc = 'Pengeluaran Operasional [' . $categoryName . ']: ' . ($cleanDesc ?: 'Tanpa keterangan');

            if ($ft) {
                $ft->update([
                    'transaction_date' => $newDate,
                    'amount' => $amount,
                    'description' => $ftDesc,
                ]);

                // Hitung ulang Buku Besar dari tanggal terdahulu
                $recalcDate = ($oldDate && $oldDate < $newDate) ? $oldDate : $newDate;
                GeneralLedger::recalculateLedgerFromDate($expense->campus_id, $recalcDate);
            } elseif ($amount > 0) {
                Keuangan::recordTransaction(
                    campusId: $expense->campus_id,
                    date: $newDate,
                    jenis: 'D',
                    sumber: 'operasional',
                    nominal: $amount,
                    keterangan: $ftDesc,
                    refId: $expense->id,
                    createdBy: auth()->id()
                );
            }
        });

        ActivityLog::record(
            module: 'expenses',
            action: 'update',
            description: "Memperbarui pengeluaran operasional (#{$expense->id}) senilai Rp " . number_format($amount, 0, ',', '.') . " - {$expense->description}",
            properties: [
                'expense_id' => $expense->id,
                'campus_id' => $targetCampusId,
                'amount' => $amount,
                'expense_category_id' => $categoryId,
                'description' => $cleanDesc,
            ],
            campusId: $targetCampusId
        );

        $newBalance = BukuBesar::getLatestBalance($targetCampusId);

        return [
            'success' => true,
            'expense' => $expense,
            'new_balance' => $newBalance,
            'message' => 'Data pengeluaran operasional berhasil diperbarui dan buku kas disinkronkan.',
        ];
    }

    /**
     * Memvalidasi dan menghapus transaksi pengeluaran operasional.
     */
    public function destroy(int $expenseId): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan', 'pengurus_bank_sampah', 'Pengurus Bank Sampah']) && !$user->can('expenses.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus pengeluaran.');
        }

        $expense = Expense::findOrFail($expenseId);

        if ($user->campus_id && $user->campus_id !== $expense->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki otoritas untuk menghapus data pengeluaran kampus ini.',
            ];
        }

        // Validasi Keterikatan: Cek apakah catatan pengeluaran terikat dengan Buku Kas
        $bindingInfo = $expense->getBindingInfo();
        if ($bindingInfo['is_bound']) {
            return [
                'success' => false,
                'message' => "Tidak dapat menghapus data pengeluaran (#{$expense->id}): " . $bindingInfo['reason'],
            ];
        }

        ActivityLog::record(
            module: 'expenses',
            action: 'delete',
            description: "Menghapus pengeluaran operasional (#{$expense->id}) senilai Rp " . number_format((float) $expense->amount, 0, ',', '.') . " - {$expense->description}",
            properties: [
                'expense_id' => $expense->id,
                'campus_id' => $expense->campus_id,
                'amount' => (float) $expense->amount,
                'description' => $expense->description,
            ],
            campusId: $expense->campus_id
        );

        $expense->delete(); // ExpenseObserver auto deletes from keuangan & updates buku_besar

        return [
            'success' => true,
            'message' => 'Data pengeluaran operasional berhasil dihapus.',
        ];
    }
}

