<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Master\Index as MasterComponent;
use App\Models\Buyer;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use App\Models\WasteSource;
use App\Models\WasteType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MasterDataController extends Controller
{
    /**
     * Menampilkan halaman modul Master Data.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R']) && !$user->can('master.manage'))) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses Pengaturan Master Data.');
        }

        return app(MasterComponent::class)();
    }

    /**
     * Simpan / Perbarui Titik Sumber Sampah.
     */
    public function saveSource(array $data): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        if (!$isSuperAdmin && !$user->hasRole(['admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R']) && !$user->can('master.manage')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang mengelola titik sumber.');
        }

        Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'campus_id' => ['required', 'exists:campuses,id'],
        ], [
            'name.required' => 'Nama titik sumber wajib diisi.',
            'name.max' => 'Nama maksimal 100 karakter.',
        ])->validate();

        $editingId = !empty($data['id']) ? (int) $data['id'] : null;

        if ($editingId && !$isSuperAdmin) {
            $existing = WasteSource::findOrFail($editingId);
            if ($existing->campus_id !== $user->campus_id) {
                abort(403, 'Akses ditolak: Anda hanya dapat mengelola titik sumber di unit kampus Anda sendiri.');
            }
        }

        $targetCampusId = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;

        $source = WasteSource::updateOrCreate(
            ['id' => $editingId],
            [
                'campus_id' => $targetCampusId,
                'name' => strip_tags(trim($data['name'])),
                'description' => !empty($data['description']) ? strip_tags(trim($data['description'])) : null,
                'is_active' => true,
            ]
        );

        return [
            'success' => true,
            'source' => $source,
            'message' => 'Titik sumber sampah berhasil disimpan.',
        ];
    }

    /**
     * Simpan / Perbarui Jenis Sampah.
     */
    public function saveWasteType(array $data): array
    {
        if (!auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            abort(403, 'Akses ditolak: Hanya Super Administrator yang berhak mengonfigurasi master jenis sampah.');
        }

        Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'in:Organik,Anorganik,Residu'],
            'default_price_per_kg' => ['required', 'numeric', 'min:0'],
            'is_sellable' => ['boolean'],
        ])->validate();

        $editingId = !empty($data['id']) ? (int) $data['id'] : null;

        $type = WasteType::updateOrCreate(
            ['id' => $editingId],
            [
                'name' => strip_tags(trim($data['name'])),
                'category' => $data['category'],
                'default_price_per_kg' => (float) $data['default_price_per_kg'],
                'is_sellable' => (bool) ($data['is_sellable'] ?? false),
                'is_active' => true,
            ]
        );

        return [
            'success' => true,
            'waste_type' => $type,
            'message' => 'Jenis sampah berhasil disimpan.',
        ];
    }

    /**
     * Simpan / Perbarui Vendor Armada Pengangkut.
     */
    public function saveVendor(array $data): array
    {
        if (!auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            abort(403, 'Akses ditolak: Hanya Super Administrator yang berhak mengonfigurasi vendor armada angkut.');
        }

        Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'contact' => ['nullable', 'string', 'max:100'],
            'cost_per_kg' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $editingId = !empty($data['id']) ? (int) $data['id'] : null;

        $vendor = Vendor::updateOrCreate(
            ['id' => $editingId],
            [
                'name' => strip_tags(trim($data['name'])),
                'contact' => !empty($data['contact']) ? strip_tags(trim($data['contact'])) : null,
                'cost_per_kg' => (float) $data['cost_per_kg'],
                'is_active' => true,
            ]
        );

        return [
            'success' => true,
            'vendor' => $vendor,
            'message' => 'Vendor pengangkut berhasil disimpan.',
        ];
    }

    /**
     * Simpan / Perbarui Pembeli / Pengepul.
     */
    public function saveBuyer(array $data): array
    {
        if (!auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            abort(403, 'Akses ditolak: Hanya Super Administrator yang berhak mengonfigurasi pembeli/pengepul.');
        }

        Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'contact' => ['nullable', 'string', 'max:100'],
        ])->validate();

        $editingId = !empty($data['id']) ? (int) $data['id'] : null;

        $buyer = Buyer::updateOrCreate(
            ['id' => $editingId],
            [
                'name' => strip_tags(trim($data['name'])),
                'contact' => !empty($data['contact']) ? strip_tags(trim($data['contact'])) : null,
            ]
        );

        return [
            'success' => true,
            'buyer' => $buyer,
            'message' => 'Pembeli/Pengepul berhasil disimpan.',
        ];
    }

    /**
     * Simpan / Perbarui Kategori Pengeluaran.
     */
    public function saveExpenseCategory(array $data): array
    {
        if (!auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            abort(403, 'Akses ditolak: Hanya Super Administrator yang berhak mengonfigurasi kategori pengeluaran.');
        }

        Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
        ])->validate();

        $editingId = !empty($data['id']) ? (int) $data['id'] : null;

        $cat = ExpenseCategory::updateOrCreate(
            ['id' => $editingId],
            ['name' => strip_tags(trim($data['name']))]
        );

        return [
            'success' => true,
            'category' => $cat,
            'message' => 'Kategori pengeluaran berhasil disimpan.',
        ];
    }

    /**
     * Eksekusi penghapusan entitas master data.
     */
    public function executeDelete(string $type, int $id): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);

        if (!$isSuperAdmin && !$user->hasRole(['admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R']) && !$user->can('master.manage')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus data master.');
        }

        if (!$isSuperAdmin) {
            if ($type !== 'source') {
                return [
                    'success' => false,
                    'message' => 'Hanya Super Administrator yang berhak menghapus master data global sistem.',
                ];
            }
            $source = WasteSource::findOrFail($id);
            if ($source->campus_id !== $user->campus_id) {
                return [
                    'success' => false,
                    'message' => 'Anda hanya dapat menghapus titik sumber di unit kampus Anda sendiri.',
                ];
            }
        }

        switch ($type) {
            case 'source':
                WasteSource::findOrFail($id)->delete();
                $msg = 'Titik sumber sampah berhasil dihapus.';
                break;
            case 'type':
                WasteType::findOrFail($id)->delete();
                $msg = 'Jenis sampah berhasil dihapus.';
                break;
            case 'vendor':
                Vendor::findOrFail($id)->delete();
                $msg = 'Vendor pengangkut berhasil dihapus.';
                break;
            case 'buyer':
                Buyer::findOrFail($id)->delete();
                $msg = 'Pembeli/Pengepul berhasil dihapus.';
                break;
            case 'category':
                ExpenseCategory::findOrFail($id)->delete();
                $msg = 'Kategori pengeluaran berhasil dihapus.';
                break;
            default:
                return ['success' => false, 'message' => 'Tipe data master tidak valid.'];
        }

        return ['success' => true, 'message' => $msg];
    }
}
