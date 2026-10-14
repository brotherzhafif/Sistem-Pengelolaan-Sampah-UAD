<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Users\Index as UserComponent;
use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Menampilkan halaman manajemen pengguna sistem.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses untuk mengelola data pengguna.');
        }

        return app(UserComponent::class)();
    }

    /**
     * Menyimpan data pengguna baru atau memperbarui pengguna yang ada.
     */
    public function saveUser(array $data): array
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->hasRole(['super_admin', 'Super Admin']);

        if (!$isSuperAdmin && !$currentUser->hasRole(['admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menyimpan atau memperbarui akun pengguna.');
        }

        $editUserId = !empty($data['id']) ? (int) $data['id'] : null;
        $selectedRole = $data['selectedRole'] ?? 'petugas_tps';
        $selectedCampusId = !empty($data['selectedCampusId']) ? (int) $data['selectedCampusId'] : null;

        // Validasi otorisasi non-super admin
        if (!$isSuperAdmin) {
            if (!in_array($selectedRole, ['petugas_tps', 'petugas_penjualan', 'keuangan'])) {
                abort(403, 'Akses ditolak: Koordinator TPS hanya diizinkan mengelola akun Petugas TPS, Petugas Penjualan, dan Keuangan.');
            }
            $selectedCampusId = (int) $currentUser->campus_id;

            if ($editUserId) {
                $target = User::findOrFail($editUserId);
                if ($target->hasRole(['super_admin', 'Super Admin']) || $target->hasRole(['viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan'])) {
                    abort(403, 'Akses ditolak: Anda tidak diizinkan mengubah akun tingkat pusat.');
                }
                if ($target->campus_id !== $currentUser->campus_id && $target->id !== $currentUser->id) {
                    abort(403, 'Akses ditolak: Anda hanya dapat mengubah akun di unit kampus Anda sendiri.');
                }
            }
        }

        $isCreate = $editUserId === null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($editUserId),
            ],
            'selectedRole' => ['required', 'string', 'exists:roles,name'],
            'selectedCampusId' => [
                $selectedRole === 'super_admin' ? 'nullable' : 'required',
                'nullable',
                'exists:campuses,id',
            ],
        ];

        if ($isCreate) {
            $rules['password'] = ['required', 'string', 'min:6'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:6'];
        }

        Validator::make(array_merge($data, [
            'selectedRole' => $selectedRole,
            'selectedCampusId' => $selectedCampusId,
        ]), $rules, [
            'name.required' => 'Nama lengkap pengguna wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique' => 'Alamat email ini sudah terdaftar oleh pengguna lain.',
            'password.required' => 'Kata sandi akun wajib diisi minimal 6 karakter.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
            'selectedRole.required' => 'Pilih peran / role akses pengguna.',
            'selectedCampusId.required' => 'Pilih unit kampus penugasan pengguna.',
        ])->validate();

        $campusToSave = $selectedRole === 'super_admin' ? null : ($isSuperAdmin ? $selectedCampusId : $currentUser->campus_id);

        if ($isCreate) {
            $user = User::create([
                'name' => strip_tags(trim($data['name'])),
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'campus_id' => $campusToSave,
            ]);

            $user->syncRoles([$selectedRole]);

            ActivityLog::record(
                module: 'users',
                action: 'create',
                description: "Menambahkan pengguna baru '{$user->name}' ({$user->email}) dengan peran {$selectedRole}",
                properties: ['user_id' => $user->id, 'role' => $selectedRole, 'campus_id' => $campusToSave],
                campusId: $campusToSave
            );

            return [
                'success' => true,
                'user' => $user,
                'message' => "Pengguna '{$user->name}' berhasil ditambahkan!",
            ];
        } else {
            $user = User::findOrFail($editUserId);

            $updateData = [
                'name' => strip_tags(trim($data['name'])),
                'email' => strtolower(trim($data['email'])),
                'campus_id' => $campusToSave,
            ];

            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $user->update($updateData);

            if ($isSuperAdmin || in_array($selectedRole, ['petugas_tps', 'petugas_penjualan', 'keuangan'])) {
                $user->syncRoles([$selectedRole]);
            }

            ActivityLog::record(
                module: 'users',
                action: 'update',
                description: "Memperbarui data pengguna '{$user->name}' ({$user->email})",
                properties: ['user_id' => $user->id, 'role' => $selectedRole, 'campus_id' => $campusToSave],
                campusId: $campusToSave
            );

            return [
                'success' => true,
                'user' => $user,
                'message' => "Data pengguna '{$user->name}' berhasil diperbarui!",
            ];
        }
    }

    /**
     * Menghapus pengguna dari sistem.
     */
    public function deleteUser(int $id): array
    {
        $currentUser = auth()->user();
        if ($id === $currentUser->id) {
            return [
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!',
            ];
        }

        $isSuperAdmin = $currentUser->hasRole(['super_admin', 'Super Admin']);
        $user = User::findOrFail($id);

        if ($user->hasRole(['super_admin', 'Super Admin'])) {
            abort(403, 'Akses ditolak: Akun Super Administrator tidak dapat dihapus.');
        }

        if ($user->hasRole(['viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan']) && !$isSuperAdmin) {
            abort(403, 'Akses ditolak: Akun Pimpinan & Auditor hanya dapat dikelola oleh Super Administrator.');
        }

        if (!$isSuperAdmin) {
            if ($user->hasRole(['admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R'])) {
                abort(403, 'Akses ditolak: Anda tidak dapat menghapus sesama akun Koordinator / Admin Kampus.');
            }
            if ($user->campus_id !== $currentUser->campus_id) {
                abort(403, 'Akses ditolak: Anda hanya dapat menghapus akun di unit kampus Anda sendiri.');
            }
        }

        $userName = $user->name;
        $userEmail = $user->email;
        $campusId = $user->campus_id;

        ActivityLog::record(
            module: 'users',
            action: 'delete',
            description: "Menghapus akun pengguna '{$userName}' ({$userEmail})",
            properties: ['deleted_user_id' => $user->id, 'name' => $userName, 'email' => $userEmail],
            campusId: $campusId
        );

        $user->delete();

        return [
            'success' => true,
            'message' => "Pengguna '{$userName}' berhasil dihapus dari sistem.",
        ];
    }

    /**
     * Mereset kata sandi pengguna ke default: password123.
     */
    public function resetPassword(int $id): array
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->hasRole(['super_admin', 'Super Admin']);
        $user = User::findOrFail($id);

        if (!$isSuperAdmin) {
            if ($user->hasRole(['super_admin', 'Super Admin'])) {
                return [
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak untuk mereset kata sandi Super Administrator!',
                ];
            }
            if ($user->hasRole(['viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan'])) {
                return [
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak untuk mereset kata sandi Pimpinan & Auditor!',
                ];
            }
            if ($user->campus_id !== $currentUser->campus_id) {
                return [
                    'success' => false,
                    'message' => 'Anda hanya dapat mereset kata sandi akun di unit kampus Anda sendiri.',
                ];
            }
        }

        $user->update([
            'password' => Hash::make('password123'),
        ]);

        return [
            'success' => true,
            'message' => "Kata sandi untuk '{$user->name}' direset menjadi: password123",
        ];
    }
}
