<?php

namespace App\Http\Controllers;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Livewire\Volt\LivewireManager;

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

        // Resolusi komponen Livewire Volt untuk View layer
        $container = Container::getInstance();
        return $container->call([
            $container->make(LivewireManager::class)->new('pages.reports.index'),
            '__invoke',
        ]);
    }
}
