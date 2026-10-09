<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define Permissions
        $permissions = [
            // Penimbangan (SRS M2)
            'weighing.view',
            'weighing.create',
            'weighing.update',
            'weighing.delete',

            // Logistik & Pengangkutan Residu (SRS M3)
            'logistics.view',
            'logistics.create',
            'logistics.update',

            // Bank Sampah & Penjualan (SRS M4 & M5)
            'bank_sampah.view',
            'bank_sampah.sale.create',
            'bank_sampah.expense.create',

            // Survei KAP (SRS M6)
            'kap.survey.view',
            'kap.survey.manage',
            'kap.survey.participate',

            // Master Data (SRS M7)
            'master.view',
            'master.manage',

            // Laporan & Audit (SRS M8 & M9)
            'report.view',
            'report.export',
            'audit.view',

            // User Management (SRS M10)
            'user.view',
            'user.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Define Roles and Assign Permissions
        // Role: Super Admin (Lembaga/Pusat UAD - SRS M9)
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Role: Admin Kampus (SRS M9)
        $adminKampus = Role::firstOrCreate(['name' => 'admin_kampus']);
        $adminKampus->syncPermissions([
            'weighing.view',
            'weighing.create',
            'weighing.update',
            'logistics.view',
            'logistics.create',
            'logistics.update',
            'bank_sampah.view',
            'bank_sampah.sale.create',
            'bank_sampah.expense.create',
            'kap.survey.view',
            'master.view',
            'report.view',
            'report.export',
            'user.view',
        ]);

        // Role: Petugas TPS (Timbang & Angkut - SRS M9)
        $petugasTps = Role::firstOrCreate(['name' => 'petugas_tps']);
        $petugasTps->syncPermissions([
            'weighing.view',
            'weighing.create',
            'weighing.update',
            'logistics.view',
            'logistics.create',
            'logistics.update',
            'master.view',
        ]);

        // Role: Petugas Penjualan (SRS M9)
        $petugasPenjualan = Role::firstOrCreate(['name' => 'petugas_penjualan']);
        $petugasPenjualan->syncPermissions([
            'bank_sampah.view',
            'bank_sampah.sale.create',
            'weighing.view',
            'master.view',
        ]);

        // Role: Keuangan (Pengeluaran - SRS M9)
        $keuanganRole = Role::firstOrCreate(['name' => 'keuangan']);
        $keuanganRole->syncPermissions([
            'bank_sampah.view',
            'bank_sampah.expense.create',
            'report.view',
            'master.view',
        ]);

        // Role: Viewer (Audit & Monitoring - SRS M9)
        $viewerRole = Role::firstOrCreate(['name' => 'viewer']);
        $viewerRole->syncPermissions([
            'weighing.view',
            'logistics.view',
            'bank_sampah.view',
            'kap.survey.view',
            'report.view',
            'audit.view',
        ]);

        // Aliases untuk kompatibilitas data existing
        $operator = Role::firstOrCreate(['name' => 'operator_timbangan']);
        $operator->syncPermissions(['weighing.view', 'weighing.create', 'master.view']);

        $koordinator = Role::firstOrCreate(['name' => 'koordinator_tps3r']);
        $koordinator->syncPermissions($adminKampus->permissions);

        $bankSampah = Role::firstOrCreate(['name' => 'pengurus_bank_sampah']);
        $bankSampah->syncPermissions(['weighing.view', 'bank_sampah.view', 'bank_sampah.sale.create', 'bank_sampah.expense.create', 'master.view', 'report.view', 'report.export']);

        $auditor = Role::firstOrCreate(['name' => 'auditor_pimpinan']);
        $auditor->syncPermissions($viewerRole->permissions);

        // 3. Create Default Demo Users
        $kampus4 = Campus::where('code', 'KAMPUS-4')->first();

        // Super Admin (Tanpa terikat kampus tertentu / global)
        $userSuperAdmin = User::firstOrCreate(
            ['email' => 'superadmin@uad.ac.id'],
            [
                'name' => 'Super Administrator UAD',
                'password' => Hash::make('password123'),
                'campus_id' => null,
            ]
        );
        $userSuperAdmin->assignRole('super_admin');

        // Operator Timbangan Kampus 4
        $userOperator = User::firstOrCreate(
            ['email' => 'operator@uad.ac.id'],
            [
                'name' => 'Operator Timbangan Kampus 4',
                'password' => Hash::make('password123'),
                'campus_id' => $kampus4?->id,
            ]
        );
        $userOperator->assignRole('operator_timbangan');

        // Koordinator TPS3R Kampus 4
        $userKoordinator = User::firstOrCreate(
            ['email' => 'koordinator@uad.ac.id'],
            [
                'name' => 'Koordinator TPS3R Kampus 4',
                'password' => Hash::make('password123'),
                'campus_id' => $kampus4?->id,
            ]
        );
        $userKoordinator->assignRole('koordinator_tps3r');

        // Pengurus Bank Sampah Kampus 4
        $userBankSampah = User::firstOrCreate(
            ['email' => 'banksampah@uad.ac.id'],
            [
                'name' => 'Pengurus Bank Sampah Kampus 4',
                'password' => Hash::make('password123'),
                'campus_id' => $kampus4?->id,
            ]
        );
        $userBankSampah->assignRole('pengurus_bank_sampah');

        // Auditor / Pimpinan UAD
        $userAuditor = User::firstOrCreate(
            ['email' => 'pimpinan@uad.ac.id'],
            [
                'name' => 'Pimpinan & Auditor UAD',
                'password' => Hash::make('password123'),
                'campus_id' => null,
            ]
        );
        $userAuditor->assignRole('auditor_pimpinan');
    }
}

