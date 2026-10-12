<?php

namespace App\Livewire\Pages\Users;

use App\Http\Controllers\UserController;
use App\Models\Campus;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterRole = '';
    public ?int $filterCampusId = null;

    // Modal Form State
    public bool $isFormModalOpen = false;
    public ?int $editUserId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $selectedRole = 'petugas_tps';
    public ?int $selectedCampusId = null;

    // Delete Confirmation Modal
    public bool $isDeleteModalOpen = false;
    public ?int $deleteUserId = null;
    public string $deleteUserName = '';

    public function getRoleLabel(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super Admin',
            'admin_kampus', 'koordinator_tps3r' => 'Admin Kampus',
            'petugas_tps', 'operator_timbangan' => 'Petugas TPS',
            'petugas_penjualan', 'pengurus_bank_sampah' => 'Petugas Penjualan',
            'keuangan' => 'Keuangan',
            'viewer', 'auditor_pimpinan' => 'Viewer',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }

    public function getRoleDescription(string $role): string
    {
        return match ($role) {
            'super_admin' => 'Akses penuh ke semua modul dan seluruh unit kampus UAD tanpa batasan.',
            'admin_kampus', 'koordinator_tps3r' => 'Pengelola operasional kampus: mengelola penimbangan, pengangkutan, penjualan, pengeluaran, buku kas, dan laporan di kampusnya.',
            'petugas_tps', 'operator_timbangan' => 'Petugas lapangan TPS: mencatat penimbangan harian dan pengangkutan residu di kampusnya.',
            'petugas_penjualan' => 'Petugas transaksi penjualan: mencatat penjualan sampah terpilah dan daur ulang ke pembeli/pengepul.',
            'keuangan' => 'Petugas keuangan: mencatat pengeluaran operasional serta memantau buku kas dan buku besar kampus.',
            'viewer', 'auditor_pimpinan' => 'Hak akses audit (Read-Only): memantau dashboard, laporan, survei KAP, dan riwayat transaksi tanpa hak input atau ubah.',
            default => 'Hak akses pengguna sesuai penugasan peran.',
        };
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses untuk mengelola data pengguna.');
        }

        // Jika bukan Super Admin, filter kampus terkunci wajib ke kampus user
        if ($user->hasRole(['super_admin', 'Super Admin'])) {
            $sessionCampus = session('active_campus_id');
            if ($sessionCampus) {
                $this->filterCampusId = (int) $sessionCampus;
            }
        } else {
            $this->filterCampusId = (int) $user->campus_id;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCampusId(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->isFormModalOpen = true;
    }

    public function openEditModal(int $id): void
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->hasRole(['super_admin', 'Super Admin']);
        $user = User::with('roles')->findOrFail($id);

        if (!$isSuperAdmin && $user->id !== $currentUser->id) {
            if ($user->hasRole(['super_admin', 'Super Admin'])) {
                $this->dispatch('toast', message: 'Anda tidak memiliki hak untuk mengubah data akun Super Administrator.', type: 'error');
                return;
            }
            if ($user->hasRole(['viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan'])) {
                $this->dispatch('toast', message: 'Anda tidak memiliki hak untuk mengubah data akun Pimpinan & Auditor.', type: 'error');
                return;
            }
            if ($user->campus_id !== $currentUser->campus_id) {
                $this->dispatch('toast', message: 'Anda hanya dapat mengelola data pengguna di unit kampus Anda sendiri.', type: 'error');
                return;
            }
        }

        $this->editUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->selectedRole = $user->roles->first()?->name ?? 'petugas_tps';
        $this->selectedCampusId = $user->campus_id;
        $this->isFormModalOpen = true;
    }

    public function closeFormModal(): void
    {
        $this->isFormModalOpen = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $currentUser = auth()->user();
        $this->editUserId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->selectedRole = 'petugas_tps';
        $this->selectedCampusId = $currentUser->campus_id ? (int) $currentUser->campus_id : Campus::first()?->id;
        $this->resetValidation();
    }

    public function saveUser(): void
    {
        $result = app(UserController::class)->saveUser([
            'id' => $this->editUserId,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'selectedRole' => $this->selectedRole,
            'selectedCampusId' => $this->selectedCampusId,
        ]);

        $this->dispatch('toast', message: $result['message'], type: 'success');
        $this->closeFormModal();
    }

    public function confirmDelete(int $id): void
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->hasRole(['super_admin', 'Super Admin']);

        if ($id === $currentUser->id) {
            $this->dispatch('toast', message: 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!', type: 'error');
            return;
        }

        $user = User::findOrFail($id);

        if ($user->hasRole(['super_admin', 'Super Admin'])) {
            $this->dispatch('toast', message: 'Akun Super Administrator tidak dapat dihapus oleh peran mana pun!', type: 'error');
            return;
        }

        if ($user->hasRole(['viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan'])) {
            $this->dispatch('toast', message: 'Akun Pimpinan & Auditor hanya dapat dikelola oleh Super Administrator!', type: 'error');
            return;
        }

        if (!$isSuperAdmin) {
            if ($user->hasRole(['admin_kampus', 'koordinator_tps3r'])) {
                $this->dispatch('toast', message: 'Anda tidak dapat menghapus akun sesama Koordinator / Admin Kampus.', type: 'error');
                return;
            }
            if ($user->campus_id !== $currentUser->campus_id) {
                $this->dispatch('toast', message: 'Anda hanya dapat mengelola akun di unit kampus Anda sendiri.', type: 'error');
                return;
            }
        }

        $this->deleteUserId = $user->id;
        $this->deleteUserName = $user->name;
        $this->isDeleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->isDeleteModalOpen = false;
        $this->deleteUserId = null;
        $this->deleteUserName = '';
    }

    public function deleteUser(): void
    {
        if (!$this->deleteUserId || $this->deleteUserId === auth()->id()) {
            return;
        }

        $result = app(UserController::class)->deleteUser($this->deleteUserId);
        $this->closeDeleteModal();
        if ($result['success']) {
            $this->dispatch('toast', message: $result['message'], type: 'success');
        } else {
            $this->dispatch('toast', message: $result['message'], type: 'error');
        }
    }

    public function resetPasswordDefault(int $id): void
    {
        $result = app(UserController::class)->resetPassword($id);
        if ($result['success']) {
            $this->dispatch('toast', message: $result['message'], type: 'success');
        } else {
            $this->dispatch('toast', message: $result['message'], type: 'error');
        }
    }

    public function render()
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->hasRole(['super_admin', 'Super Admin']);

        $effectiveCampusId = $isSuperAdmin ? $this->filterCampusId : (int) $currentUser->campus_id;
        $users = User::filtered($effectiveCampusId, $this->filterRole, $this->search)->paginate(8);

        $roles = User::getOrderedRoles(false);
        $formRoles = $isSuperAdmin ? $roles : User::getOrderedRoles(true);
        $campuses = Campus::getActiveCampuses();

        return view('livewire.pages.users.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'users' => $users,
            'roles' => $roles,
            'formRoles' => $formRoles,
            'campuses' => $campuses,
        ]);
    }
}
