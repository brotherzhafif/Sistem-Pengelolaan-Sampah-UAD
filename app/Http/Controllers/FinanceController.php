<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Finance\Index as FinanceComponent;
use App\Models\ActivityLog;
use App\Models\GeneralLedger;
use App\Models\Keuangan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinanceController extends Controller
{
    /**
     * Menampilkan halaman modul Buku Kas & Buku Besar Keuangan.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan']) && !$user->can('finance.view'))) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Keuangan.');
        }

        return app(FinanceComponent::class)();
    }

    /**
     * Menetapkan dan mencatat saldo kas awal operasional TPS kampus.
     */
    public function saveInitialBalance(array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan']) && !$user->can('finance.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menetapkan saldo awal.');
        }

        Validator::make($data, [
            'initialCampusId' => ['required', 'exists:campuses,id'],
            'initialDate' => ['required', 'date'],
            'initialAmount' => ['required', 'numeric', 'min:0'],
            'initialNotes' => ['nullable', 'string', 'max:255'],
        ], [
            'initialCampusId.required' => 'Unit kampus wajib dipilih.',
            'initialDate.required' => 'Tanggal saldo awal wajib diisi.',
            'initialAmount.required' => 'Nominal saldo awal wajib diisi.',
        ])->validate();

        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $targetCampusId = $isSuperAdmin ? (int) $data['initialCampusId'] : (int) $user->campus_id;
        $amount = (float) $data['initialAmount'];

        Keuangan::recordTransaction(
            campusId: $targetCampusId,
            date: $data['initialDate'],
            jenis: 'K',
            sumber: 'saldo_awal',
            nominal: $amount,
            keterangan: !empty($data['initialNotes']) ? strip_tags(trim($data['initialNotes'])) : 'Saldo kas awal pembukuan TPS',
            refId: null,
            createdBy: auth()->id()
        );

        ActivityLog::record(
            module: 'finance',
            action: 'create',
            description: "Menetapkan saldo kas awal TPS Kampus senilai Rp " . number_format($amount, 0, ',', '.'),
            properties: [
                'campus_id' => $targetCampusId,
                'amount' => $amount,
                'date' => $data['initialDate'],
            ],
            campusId: $targetCampusId
        );

        return [
            'success' => true,
            'message' => 'Saldo kas awal berhasil ditetapkan dan disinkronkan ke Buku Besar.',
        ];
    }

    /**
     * Memperbarui transaksi kas / saldo awal dan menyinkronkan Buku Besar.
     */
    public function update(int $id, array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan']) && !$user->can('finance.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah data keuangan.');
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'amount.required' => 'Nominal wajib diisi.',
            'amount.min' => 'Nominal tidak boleh kurang dari 0.',
        ])->validate();

        $transaction = Keuangan::findOrFail($id);
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);

        if (!$isSuperAdmin && $transaction->campus_id !== $user->campus_id) {
            abort(403, 'Akses ditolak: Anda hanya dapat mengubah transaksi di unit kampus Anda sendiri.');
        }

        $binding = $transaction->getBindingInfo();
        if ($binding['is_bound']) {
            return [
                'success' => false,
                'message' => 'Transaksi ini terikat dengan modul ' . implode(', ', $binding['features']) . '. Silakan lakukan pengubahan melalui modul asalnya agar data tetap sinkron.',
                'binding' => $binding,
            ];
        }

        $targetCampusId = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;
        $oldDate = $transaction->transaction_date->format('Y-m-d');
        $newDate = Carbon::parse($data['transaction_date'])->format('Y-m-d');
        $oldCampusId = $transaction->campus_id;
        $amount = (float) $data['amount'];

        $transaction->update([
            'campus_id' => $targetCampusId,
            'transaction_date' => $newDate,
            'amount' => $amount,
            'description' => !empty($data['description']) ? strip_tags(trim($data['description'])) : $transaction->description,
        ]);

        // Rekalkulasi Buku Besar dari tanggal terdahulu
        $recalcDate = min($oldDate, $newDate);
        GeneralLedger::recalculateLedgerFromDate($targetCampusId, $recalcDate);
        if ($oldCampusId !== $targetCampusId) {
            GeneralLedger::recalculateLedgerFromDate($oldCampusId, $oldDate);
        }

        ActivityLog::record(
            module: 'finance',
            action: 'update',
            description: "Memperbarui transaksi kas '{$transaction->description}' senilai Rp " . number_format($amount, 0, ',', '.'),
            properties: [
                'transaction_id' => $transaction->id,
                'campus_id' => $targetCampusId,
                'amount' => $amount,
                'transaction_date' => $newDate,
            ],
            campusId: $targetCampusId
        );

        return [
            'success' => true,
            'message' => 'Transaksi kas berhasil diperbarui dan Buku Besar telah disinkronkan.',
        ];
    }

    /**
     * Menghapus transaksi kas / saldo awal dan menyinkronkan Buku Besar.
     */
    public function destroy(int $id): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan']) && !$user->can('finance.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus data keuangan.');
        }

        $transaction = Keuangan::findOrFail($id);
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);

        if (!$isSuperAdmin && $transaction->campus_id !== $user->campus_id) {
            abort(403, 'Akses ditolak: Anda hanya dapat menghapus transaksi di unit kampus Anda sendiri.');
        }

        $binding = $transaction->getBindingInfo();
        if ($binding['is_bound']) {
            return [
                'success' => false,
                'message' => 'Transaksi ini terikat dengan modul ' . implode(', ', $binding['features']) . ' dan tidak dapat dihapus langsung dari Buku Kas.',
                'binding' => $binding,
            ];
        }

        $campusId = $transaction->campus_id;
        $date = $transaction->transaction_date->format('Y-m-d');
        $desc = $transaction->description;
        $amount = (float) $transaction->amount;

        $transaction->delete();

        // Rekalkulasi Buku Besar
        GeneralLedger::recalculateLedgerFromDate($campusId, $date);

        ActivityLog::record(
            module: 'finance',
            action: 'delete',
            description: "Menghapus transaksi kas '{$desc}' senilai Rp " . number_format($amount, 0, ',', '.'),
            properties: [
                'transaction_id' => $id,
                'campus_id' => $campusId,
                'amount' => $amount,
                'date' => $date,
            ],
            campusId: $campusId
        );

        return [
            'success' => true,
            'message' => 'Transaksi kas berhasil dihapus dan saldo Buku Besar telah disinkronkan.',
        ];
    }
}
