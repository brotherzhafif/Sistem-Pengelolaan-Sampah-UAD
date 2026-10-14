<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Master\Index as MasterComponent;
use App\Models\ActivityLog;
use App\Models\Buyer;
use App\Models\Campus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Pickup;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Vendor;
use App\Models\WasteSource;
use App\Models\WasteType;
use App\Models\WeighingItem;
use App\Models\WeighingSession;
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

        ActivityLog::record(
            module: 'master_data',
            action: $editingId ? 'update' : 'create',
            description: ($editingId ? 'Memperbarui' : 'Menambahkan') . " titik sumber sampah '{$source->name}'",
            properties: ['source_id' => $source->id, 'campus_id' => $source->campus_id, 'name' => $source->name],
            campusId: $targetCampusId
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

        ActivityLog::record(
            module: 'master_data',
            action: $editingId ? 'update' : 'create',
            description: ($editingId ? 'Memperbarui' : 'Menambahkan') . " jenis sampah '{$type->name}' ({$type->category})",
            properties: ['waste_type_id' => $type->id, 'name' => $type->name, 'category' => $type->category, 'is_sellable' => $type->is_sellable]
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

        ActivityLog::record(
            module: 'master_data',
            action: $editingId ? 'update' : 'create',
            description: ($editingId ? 'Memperbarui' : 'Menambahkan') . " vendor armada pengangkut '{$vendor->name}'",
            properties: ['vendor_id' => $vendor->id, 'name' => $vendor->name, 'cost_per_kg' => $vendor->cost_per_kg]
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

        ActivityLog::record(
            module: 'master_data',
            action: $editingId ? 'update' : 'create',
            description: ($editingId ? 'Memperbarui' : 'Menambahkan') . " data pembeli/pengepul '{$buyer->name}'",
            properties: ['buyer_id' => $buyer->id, 'name' => $buyer->name]
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

        ActivityLog::record(
            module: 'master_data',
            action: $editingId ? 'update' : 'create',
            description: ($editingId ? 'Memperbarui' : 'Menambahkan') . " kategori pengeluaran '{$cat->name}'",
            properties: ['category_id' => $cat->id, 'name' => $cat->name]
        );

        return [
            'success' => true,
            'category' => $cat,
            'message' => 'Kategori pengeluaran berhasil disimpan.',
        ];
    }

    /**
     * Simpan / Perbarui Unit Kampus.
     */
    public function saveCampus(array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin'])) {
            abort(403, 'Akses ditolak: Hanya Super Administrator yang berhak mengelola data unit kampus.');
        }

        Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama kampus wajib diisi.',
            'code.required' => 'Kode kampus wajib diisi.',
        ])->validate();

        $editingId = !empty($data['id']) ? (int) $data['id'] : null;

        if ($editingId) {
            $campus = Campus::findOrFail($editingId);
            $campus->update([
                'name' => $data['name'],
                'code' => $data['code'],
                'address' => $data['address'] ?? null,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ]);
            $msg = "Unit kampus '{$campus->name}' berhasil diperbarui.";
        } else {
            $campus = Campus::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'address' => $data['address'] ?? null,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            ]);
            $msg = "Unit kampus '{$campus->name}' berhasil ditambahkan.";
        }

        ActivityLog::record(
            module: 'master_data',
            action: $editingId ? 'update' : 'create',
            description: ($editingId ? 'Memperbarui' : 'Menambahkan') . " unit kampus '{$campus->name}'",
            properties: $data
        );

        return [
            'success' => true,
            'campus' => $campus,
            'message' => $msg,
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
            case 'campus':
                $entity = Campus::findOrFail($id);
                $name = $entity->name;
                $binding = $entity->getBindingInfo();
                if ($binding['is_bound']) {
                    return [
                        'success' => false,
                        'message' => "Tidak dapat menghapus unit kampus '{$name}': " . implode('. ', $binding['reasons']),
                        'binding' => $binding,
                    ];
                }
                $entity->delete();
                $msg = 'Unit kampus berhasil dihapus.';
                break;
            case 'source':
                $entity = WasteSource::findOrFail($id);
                $name = $entity->name;
                $binding = $entity->getBindingInfo();
                if ($binding['is_bound']) {
                    return [
                        'success' => false,
                        'message' => "Tidak dapat menghapus titik sumber '{$name}': " . implode('. ', $binding['reasons']),
                        'binding' => $binding,
                    ];
                }
                $entity->delete();
                $msg = 'Titik sumber sampah berhasil dihapus.';
                break;
            case 'type':
                $entity = WasteType::findOrFail($id);
                $name = $entity->name;
                $binding = $entity->getBindingInfo();
                if ($binding['is_bound']) {
                    return [
                        'success' => false,
                        'message' => "Tidak dapat menghapus jenis sampah '{$name}': " . implode('. ', $binding['reasons']),
                        'binding' => $binding,
                    ];
                }
                $entity->delete();
                $msg = 'Jenis sampah berhasil dihapus.';
                break;
            case 'vendor':
                $entity = Vendor::findOrFail($id);
                $name = $entity->name;
                $binding = $entity->getBindingInfo();
                if ($binding['is_bound']) {
                    return [
                        'success' => false,
                        'message' => "Tidak dapat menghapus vendor '{$name}': " . implode('. ', $binding['reasons']),
                        'binding' => $binding,
                    ];
                }
                $entity->delete();
                $msg = 'Vendor pengangkut berhasil dihapus.';
                break;
            case 'buyer':
                $entity = Buyer::findOrFail($id);
                $name = $entity->name;
                $binding = $entity->getBindingInfo();
                if ($binding['is_bound']) {
                    return [
                        'success' => false,
                        'message' => "Tidak dapat menghapus pembeli/pengepul '{$name}': " . implode('. ', $binding['reasons']),
                        'binding' => $binding,
                    ];
                }
                $entity->delete();
                $msg = 'Pembeli/Pengepul berhasil dihapus.';
                break;
            case 'category':
                $entity = ExpenseCategory::findOrFail($id);
                $name = $entity->name;
                $binding = $entity->getBindingInfo();
                if ($binding['is_bound']) {
                    return [
                        'success' => false,
                        'message' => "Tidak dapat menghapus kategori pengeluaran '{$name}': " . implode('. ', $binding['reasons']),
                        'binding' => $binding,
                    ];
                }
                $entity->delete();
                $msg = 'Kategori pengeluaran berhasil dihapus.';
                break;
            default:
                return ['success' => false, 'message' => 'Tipe data master tidak valid.'];
        }

        ActivityLog::record(
            module: 'master_data',
            action: 'delete',
            description: "Menghapus master data {$type} '{$name}' (#{$id})",
            properties: ['type' => $type, 'id' => $id, 'name' => $name]
        );

        return ['success' => true, 'message' => $msg];
    }
}
