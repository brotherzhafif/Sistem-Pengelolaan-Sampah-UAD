<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\ActivityLogs\Index as ActivityLogsComponent;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Menampilkan halaman riwayat log aktivitas dan audit trail sistem.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'auditor_pimpinan', 'Auditor / Pimpinan']) && !$user->can('logs.view'))) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk melihat Log Aktivitas Sistem.');
        }

        return app(ActivityLogsComponent::class)();
    }
}
