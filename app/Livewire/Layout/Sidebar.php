<?php

namespace App\Livewire\Layout;

use App\Livewire\Actions\Logout;
use App\Models\Campus;
use Livewire\Component;

class Sidebar extends Component
{
    public ?int $activeCampusId = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->activeCampusId = session('active_campus_id', $user->campus_id ?? null);
    }

    public function switchCampus(?int $campusId): void
    {
        // Hanya Super Admin / Auditor yang bisa berpindah kampus global
        $user = auth()->user();
        $canSwitch = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        if (!$canSwitch) {
            return;
        }

        $this->activeCampusId = $campusId ?: null;
        session(['active_campus_id' => $this->activeCampusId]);

        // Refresh halaman saat ini agar data tabel/metrik ter-filter otomatis sesuai kampus yang dipilih
        $this->redirect(request()->header('Referer') ?? route('dashboard'), navigate: false);
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']) || !$user->campus_id;
        $isAuditor = $user->hasRole(['viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan']);
        $isAdminKampus = $user->hasRole(['admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R']);
        $isPetugasTps = $user->hasRole(['petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']);
        $isPetugasPenjualan = $user->hasRole(['petugas_penjualan', 'Petugas Penjualan', 'pengurus_bank_sampah']);
        $isKeuangan = $user->hasRole(['keuangan', 'Keuangan']);

        // Hak Akses Modul Berdasarkan Logika Peran RBAC
        $canWeighing = $isSuperAdmin || $isAdminKampus || $isPetugasTps || $isAuditor;
        $canSales = $isSuperAdmin || $isAdminKampus || $isPetugasPenjualan || $isAuditor;
        $canPickups = $isSuperAdmin || $isAdminKampus || $isPetugasTps || $isAuditor;
        $canExpenses = $isSuperAdmin || $isAdminKampus || $isKeuangan;
        $canFinance = $isSuperAdmin || $isAdminKampus || $isKeuangan || $isAuditor;
        $canKap = $isSuperAdmin || $isAdminKampus || $isAuditor;
        $canReports = $isSuperAdmin || $isAdminKampus || $isKeuangan || $isAuditor;
        $canMaster = $isSuperAdmin || $isAdminKampus;
        $canUsers = $isSuperAdmin || $isAdminKampus;

        $activeCampus = $this->activeCampusId ? Campus::find($this->activeCampusId) : $user->campus;

        return view('livewire.layout.sidebar', [
            'isSuperAdmin' => $isSuperAdmin || $isAuditor,
            'canWeighing' => $canWeighing,
            'canSales' => $canSales,
            'canPickups' => $canPickups,
            'canExpenses' => $canExpenses,
            'canFinance' => $canFinance,
            'canKap' => $canKap,
            'canReports' => $canReports,
            'canMaster' => $canMaster,
            'canUsers' => $canUsers,
            'activeCampus' => $activeCampus,
            'campuses' => Campus::where('is_active', true)->orderBy('id')->get(),
        ]);
    }
}
