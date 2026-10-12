<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Expenses\Index as ExpenseComponent;
use App\Models\Campus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
    public function store(array $data, LedgerService $ledgerService): array
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

        $newBalance = $ledgerService->getLatestBalance($campusId);
        $message = "Pengeluaran Rp " . number_format($amount, 0, ',', '.') . " tersimpan → Saldo: Rp " . number_format($newBalance, 0, ',', '.');

        return [
            'success' => true,
            'expense' => $expense,
            'new_balance' => $newBalance,
            'message' => $message,
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

        $expense->delete(); // ExpenseObserver auto deletes from keuangan & updates buku_besar

        return [
            'success' => true,
            'message' => 'Data pengeluaran operasional berhasil dihapus.',
        ];
    }
}

