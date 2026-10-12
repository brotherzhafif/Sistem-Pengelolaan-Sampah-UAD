<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Finance\Index as FinanceComponent;
use App\Services\LedgerService;
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
    public function saveInitialBalance(array $data, LedgerService $ledgerService): array
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

        $ledgerService->recordTransaction(
            campusId: $targetCampusId,
            date: $data['initialDate'],
            jenis: 'K',
            sumber: 'saldo_awal',
            nominal: (float) $data['initialAmount'],
            keterangan: !empty($data['initialNotes']) ? strip_tags(trim($data['initialNotes'])) : 'Saldo kas awal pembukuan TPS',
            refId: null,
            createdBy: auth()->id()
        );

        return [
            'success' => true,
            'message' => 'Saldo kas awal berhasil ditetapkan dan disinkronkan ke Buku Besar.',
        ];
    }
}
