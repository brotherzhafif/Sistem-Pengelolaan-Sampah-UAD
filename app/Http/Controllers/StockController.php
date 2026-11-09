<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Stock\Index as StockComponent;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * Menampilkan halaman modul Rekapitulasi & Monitoring Stok Sampah.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole([
            'super_admin', 'Super Admin', 
            'admin_kampus', 'Admin Kampus', 
            'koordinator_tps3r', 'Koordinator TPS3R', 
            'petugas_tps', 'Petugas TPS', 
            'operator_timbangan', 'Operator Timbangan',
            'petugas_penjualan', 'Petugas Penjualan',
            'pengurus_bank_sampah',
            'keuangan', 'Keuangan',
            'viewer', 'Viewer', 
            'auditor_pimpinan', 'Auditor / Pimpinan'
        ]) && !$user->can('weighing.view') && !$user->can('sales.view'))) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Rekapitulasi Stok.');
        }

        return app(StockComponent::class)();
    }
}
