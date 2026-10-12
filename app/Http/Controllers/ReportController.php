<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Reports\Index as ReportComponent;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Menampilkan modul Laporan Komprehensif dan Analitik Sistem.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole([
            'super_admin', 'Super Admin',
            'admin_kampus', 'Admin Kampus',
            'koordinator_tps3r', 'Koordinator TPS3R',
            'keuangan', 'Keuangan',
            'viewer', 'Viewer',
            'auditor_pimpinan', 'Auditor / Pimpinan'
        ])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Laporan & Ekspor.');
        }

        return app(ReportComponent::class)();
    }
}
