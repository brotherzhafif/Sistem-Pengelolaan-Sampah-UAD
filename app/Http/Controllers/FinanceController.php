<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Finance\Index as FinanceComponent;
use App\Models\ActivityLog;
use App\Models\Keuangan;
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
}
