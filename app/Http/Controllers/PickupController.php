<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Pickups\Index as PickupComponent;
use App\Models\ActivityLog;
use App\Models\BukuBesar;
use App\Models\FinancialTransaction;
use App\Models\GeneralLedger;
use App\Models\Keuangan;
use App\Models\Pickup;
use App\Models\PickupItem;
use App\Models\Vendor;
use App\Models\WasteStockSummary;
use App\Models\WasteType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PickupController extends Controller
{
    /**
     * Menampilkan halaman modul Pengangkutan Residu / Logistik TPA.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan']) && !$user->can('logistics.view'))) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Pengangkutan.');
        }

        return app(PickupComponent::class)();
    }

    /**
     * Memvalidasi stok residu dan menyimpan transaksi pengangkutan residu.
     */
    public function store(array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) && !$user->can('logistics.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mencatat data pengangkutan.');
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'pickup_date' => ['required', 'date'],
            'cost_per_kg' => ['required', 'numeric', 'min:0'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_plate' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'vendor_id.required' => 'Pihak vendor pengangkut wajib dipilih.',
            'pickup_date.required' => 'Tanggal pengangkutan wajib diisi.',
            'cost_per_kg.required' => 'Biaya per kg wajib diisi.',
            'items.required' => 'Detail jenis sampah residu yang diangkut wajib diisi.',
        ])->validate();

        $costPerKg = floatval($data['cost_per_kg']);
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $campusId = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;
        $vendorId = (int) $data['vendor_id'];

        $itemsToPick = [];
        foreach ($data['items'] as $index => $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            if ($weight <= 0) {
                continue;
            }

            $wasteTypeId = (int) $item['waste_type_id'];
            $wasteType = WasteType::findOrFail($wasteTypeId);

            if ($wasteType->is_sellable) {
                throw ValidationException::withMessages([
                    "pickupItems.{$index}.weight_kg" => "Jenis sampah '{$wasteType->name}' bernilai jual dan dikelola melalui Penjualan Sampah, bukan pengangkutan TPA.",
                ]);
            }

            $availableStock = WasteType::getAvailableStock($wasteTypeId, $campusId);
            if ($weight > $availableStock) {
                throw ValidationException::withMessages([
                    "pickupItems.{$index}.weight_kg" => "Stok '{$wasteType->name}' tidak mencukupi (Tersedia: " . number_format($availableStock, 1, ',', '.') . " kg, Diminta: " . number_format($weight, 1, ',', '.') . " kg).",
                ]);
            }

            $itemsToPick[$wasteTypeId] = ($itemsToPick[$wasteTypeId] ?? 0.0) + $weight;
        }

        if (empty($itemsToPick)) {
            throw ValidationException::withMessages([
                'pickupItems' => 'Harap isi bobot muatan minimal untuk salah satu jenis sampah residu.',
            ]);
        }

        $volume = round(array_sum($itemsToPick), 2);
        $totalCost = round($volume * $costPerKg, 2);
        $pickup = null;

        DB::transaction(function () use ($campusId, $vendorId, $data, $volume, $costPerKg, $totalCost, $itemsToPick, &$pickup) {
            $pickup = Pickup::create([
                'campus_id' => $campusId,
                'vendor_id' => $vendorId,
                'pickup_date' => $data['pickup_date'],
                'volume_kg' => $volume,
                'cost_per_kg' => $costPerKg,
                'total_cost' => $totalCost,
                'driver_name' => !empty($data['driver_name']) ? strip_tags(trim($data['driver_name'])) : null,
                'vehicle_plate' => !empty($data['vehicle_plate']) ? strip_tags(trim(strtoupper($data['vehicle_plate']))) : null,
                'created_by' => auth()->id(),
                'notes' => !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null,
            ]);

            $itemsDelta = [];
            foreach ($itemsToPick as $wasteTypeId => $weight) {
                PickupItem::create([
                    'pickup_id' => $pickup->id,
                    'waste_type_id' => $wasteTypeId,
                    'weight_kg' => $weight,
                ]);

                $itemsDelta[] = [
                    'waste_type_id' => $wasteTypeId,
                    'picked_up_delta' => (float) $weight,
                ];
            }

            // Update Tabel Rekapitulasi Stok Real-Time (O(1)) & Catat Mutasi
            WasteStockSummary::applyMutation(
                campusId: $campusId,
                referenceType: 'pickup',
                referenceId: $pickup->id,
                action: 'create',
                pickedUpDelta: $volume,
                pickupCostDelta: $totalCost,
                items: $itemsDelta,
                notes: "Pengangkutan residu ke TPA/Vendor #{$pickup->id}"
            );
        });

        ActivityLog::record(
            module: 'pickups',
            action: 'create',
            description: "Mencatat pengangkutan residu (#{$pickup->id}) seberat " . number_format($volume, 1, ',', '.') . " kg senilai Rp " . number_format($totalCost, 0, ',', '.'),
            properties: [
                'pickup_id' => $pickup->id,
                'vendor_id' => $vendorId,
                'volume_kg' => $volume,
                'cost_per_kg' => $costPerKg,
                'total_cost' => $totalCost,
                'items_count' => count($itemsToPick),
            ],
            campusId: $campusId
        );

        $newBalance = BukuBesar::getLatestBalance($campusId);
        $toastMsg = "Pengangkutan " . number_format($volume, 1, ',', '.') . " kg tersimpan Biaya Rp " . number_format($totalCost, 0, ',', '.') . " Saldo: Rp " . number_format($newBalance, 0, ',', '.');

        return [
            'success' => true,
            'pickup' => $pickup,
            'total_cost' => $totalCost,
            'new_balance' => $newBalance,
            'message' => $toastMsg,
        ];
    }

    /**
     * Memvalidasi dan memperbarui data transaksi pengangkutan residu beserta rincian jenis sampahnya.
     */
    public function update(int $pickupId, array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) && !$user->can('logistics.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah data pengangkutan.');
        }

        $pickup = Pickup::with(['items', 'vendor'])->findOrFail($pickupId);

        // Otorisasi kampus
        if ($user->campus_id && $user->campus_id !== $pickup->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            throw ValidationException::withMessages([
                'editCampusId' => 'Anda tidak memiliki otoritas untuk mengubah data kampus ini.',
            ]);
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'pickup_date' => ['required', 'date'],
            'cost_per_kg' => ['required', 'numeric', 'min:0'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_plate' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'vendor_id.required' => 'Pihak vendor pengangkut wajib dipilih.',
            'pickup_date.required' => 'Tanggal pengangkutan wajib diisi.',
            'cost_per_kg.required' => 'Biaya per kg wajib diisi.',
            'items.required' => 'Detail jenis sampah residu yang diangkut wajib diisi.',
        ])->validate();

        $bindingInfo = $pickup->getBindingInfo();
        $targetCampusId = (int) $data['campus_id'];

        if ($bindingInfo['is_bound'] && $targetCampusId !== $pickup->campus_id) {
            throw ValidationException::withMessages([
                'editCampusId' => 'Unit kampus tidak dapat diubah karena transaksi ini telah terikat dengan Jurnal Buku Kas.',
            ]);
        }

        $existingItemsWeight = $pickup->items->pluck('weight_kg', 'waste_type_id')->map(fn ($w) => (float) $w)->toArray();

        $itemsToPick = [];
        foreach ($data['items'] as $index => $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            if ($weight <= 0) {
                continue;
            }

            $wasteTypeId = (int) $item['waste_type_id'];
            $wasteType = WasteType::findOrFail($wasteTypeId);

            if ($wasteType->is_sellable) {
                throw ValidationException::withMessages([
                    "editItems.{$index}.weight_kg" => "Jenis sampah '{$wasteType->name}' bernilai jual dan tidak dapat diangkut sebagai residu ke TPA.",
                ]);
            }

            $itemsToPick[$wasteTypeId] = ($itemsToPick[$wasteTypeId] ?? 0.0) + $weight;
        }

        if (empty($itemsToPick)) {
            throw ValidationException::withMessages([
                'editItems' => 'Harap isi bobot muatan minimal untuk salah satu jenis sampah residu.',
            ]);
        }

        foreach ($data['items'] as $index => $item) {
            $wasteTypeId = (int) $item['waste_type_id'];
            if (empty($itemsToPick[$wasteTypeId])) {
                continue;
            }
            $newItemWeight = $itemsToPick[$wasteTypeId];
            $oldItemWeight = $existingItemsWeight[$wasteTypeId] ?? 0.0;
            $deltaItem = $newItemWeight - $oldItemWeight;

            if ($deltaItem > 0) {
                $availableStock = WasteType::getAvailableStock($wasteTypeId, $pickup->campus_id);
                if ($deltaItem > $availableStock) {
                    $wasteType = WasteType::find($wasteTypeId);
                    $maxAllowable = $oldItemWeight + $availableStock;
                    throw ValidationException::withMessages([
                        "editItems.{$index}.weight_kg" => "Penambahan muatan '" . ($wasteType?->name ?? 'Residu') . "' melebihi sisa stok TPS (Maksimal: " . number_format($maxAllowable, 1, ',', '.') . " kg).",
                    ]);
                }
            }
        }

        $oldVolume = (float) $pickup->volume_kg;
        $newVolume = round(array_sum($itemsToPick), 2);
        $newCostPerKg = floatval($data['cost_per_kg']);
        $newTotalCost = round($newVolume * $newCostPerKg, 2);

        $oldDate = $pickup->pickup_date?->format('Y-m-d');
        $newDate = $data['pickup_date'];
        $deltaPickedUp = $newVolume - $oldVolume;
        $oldCost = (float) $pickup->total_cost;
        $deltaCost = $newTotalCost - $oldCost;

        $allTypeIds = array_unique(array_merge(array_keys($existingItemsWeight), array_keys($itemsToPick)));
        $itemsDelta = [];
        foreach ($allTypeIds as $tId) {
            $diff = ($itemsToPick[$tId] ?? 0.0) - ($existingItemsWeight[$tId] ?? 0.0);
            if (abs($diff) > 0.0001) {
                $itemsDelta[] = [
                    'waste_type_id' => $tId,
                    'picked_up_delta' => $diff,
                ];
            }
        }

        $vendorName = Vendor::find((int) $data['vendor_id'])?->name ?? 'Mitra Pengangkut';

        DB::transaction(function () use ($pickup, $data, $targetCampusId, $newVolume, $newCostPerKg, $newTotalCost, $oldDate, $newDate, $deltaPickedUp, $deltaCost, $itemsToPick, $itemsDelta, $vendorName) {
            $pickup->update([
                'campus_id' => $targetCampusId,
                'vendor_id' => (int) $data['vendor_id'],
                'pickup_date' => $newDate,
                'volume_kg' => $newVolume,
                'cost_per_kg' => $newCostPerKg,
                'total_cost' => $newTotalCost,
                'driver_name' => !empty($data['driver_name']) ? strip_tags(trim($data['driver_name'])) : null,
                'vehicle_plate' => !empty($data['vehicle_plate']) ? strip_tags(trim(strtoupper($data['vehicle_plate']))) : null,
                'notes' => !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null,
            ]);

            $pickup->items()->delete();
            foreach ($itemsToPick as $wasteTypeId => $weight) {
                PickupItem::create([
                    'pickup_id' => $pickup->id,
                    'waste_type_id' => $wasteTypeId,
                    'weight_kg' => $weight,
                ]);
            }

            // Update Tabel Rekapitulasi Stok Real-Time (O(1)) & Catat Mutasi
            WasteStockSummary::applyMutation(
                campusId: $targetCampusId,
                referenceType: 'pickup',
                referenceId: $pickup->id,
                action: 'update',
                pickedUpDelta: $deltaPickedUp,
                pickupCostDelta: $deltaCost,
                items: $itemsDelta,
                notes: "Pembaruan rincian pengangkutan residu #{$pickup->id}"
            );

            // Sinkronisasi Transaksi Keuangan Buku Kas
            $ft = FinancialTransaction::where('source', 'pickup')->where('ref_id', $pickup->id)->first();

            if ($ft) {
                $ft->update([
                    'transaction_date' => $newDate,
                    'amount' => $newTotalCost,
                    'description' => 'Biaya pengangkutan residu sampah oleh ' . $vendorName . ' (' . number_format($newVolume, 1, ',', '.') . ' kg)',
                ]);

                // Hitung ulang Buku Besar dari tanggal terdahulu
                $recalcDate = ($oldDate && $oldDate < $newDate) ? $oldDate : $newDate;
                GeneralLedger::recalculateLedgerFromDate($pickup->campus_id, $recalcDate);
            } elseif ($newTotalCost > 0) {
                Keuangan::recordTransaction(
                    campusId: $pickup->campus_id,
                    date: $newDate,
                    jenis: 'D',
                    sumber: 'pengangkutan',
                    nominal: $newTotalCost,
                    keterangan: 'Biaya pengangkutan residu sampah oleh ' . $vendorName . ' (' . number_format($newVolume, 1, ',', '.') . ' kg)',
                    refId: $pickup->id,
                    createdBy: auth()->id()
                );
            }
        });

        ActivityLog::record(
            module: 'pickups',
            action: 'update',
            description: "Memperbarui data pengangkutan residu (#{$pickup->id}) seberat " . number_format($newVolume, 1, ',', '.') . " kg senilai Rp " . number_format($newTotalCost, 0, ',', '.'),
            properties: [
                'pickup_id' => $pickup->id,
                'campus_id' => $pickup->campus_id,
                'volume_kg' => $newVolume,
                'cost_per_kg' => $newCostPerKg,
                'total_cost' => $newTotalCost,
                'items_count' => count($itemsToPick),
            ],
            campusId: $pickup->campus_id
        );

        $newBalance = BukuBesar::getLatestBalance($pickup->campus_id);

        return [
            'success' => true,
            'pickup' => $pickup->fresh(['items.wasteType', 'vendor']),
            'total_cost' => $newTotalCost,
            'new_balance' => $newBalance,
            'message' => 'Data pengangkutan residu berhasil diperbarui dan buku kas disinkronkan.',
        ];
    }

    /**
     * Memvalidasi dan menghapus data pengangkutan residu.
     */
    public function destroy(int $pickupId): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) && !$user->can('logistics.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus data pengangkutan.');
        }

        $pickup = Pickup::with('items')->findOrFail($pickupId);

        // Otorisasi: hanya Super Admin atau user dari kampus bersangkutan
        if ($user->campus_id && $user->campus_id !== $pickup->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki otoritas untuk menghapus data pengangkutan kampus ini.',
            ];
        }

        $volumeKg = (float) $pickup->volume_kg;
        $campusId = (int) $pickup->campus_id;
        $pickupId = (int) $pickup->id;
        $totalCost = (float) $pickup->total_cost;

        $itemsDelta = [];
        foreach ($pickup->items as $it) {
            $itemsDelta[] = [
                'waste_type_id' => (int) $it->waste_type_id,
                'picked_up_delta' => -(float) $it->weight_kg,
            ];
        }

        DB::transaction(function () use ($pickup, $campusId, $pickupId, $volumeKg, $totalCost, $itemsDelta) {
            $pickup->items()->delete();
            $pickup->delete(); // Observer otomatis hapus jurnal debet keuangan & re-sync buku besar

            WasteStockSummary::applyMutation(
                campusId: $campusId,
                referenceType: 'pickup',
                referenceId: $pickupId,
                action: 'delete',
                pickedUpDelta: -$volumeKg,
                pickupCostDelta: -$totalCost,
                items: $itemsDelta,
                notes: "Penghapusan data pengangkutan residu #{$pickupId} (stok residu dikembalikan)"
            );
        });

        ActivityLog::record(
            module: 'pickups',
            action: 'delete',
            description: "Menghapus data pengangkutan residu (#{$pickupId}) seberat " . number_format($volumeKg, 1, ',', '.') . " kg senilai Rp " . number_format($totalCost, 0, ',', '.'),
            properties: [
                'pickup_id' => $pickupId,
                'campus_id' => $campusId,
                'volume_kg' => $volumeKg,
                'total_cost' => $totalCost,
            ],
            campusId: $campusId
        );

        return [
            'success' => true,
            'message' => 'Data pengangkutan residu berhasil dihapus. Catatan kas keuangan diselaraskan dan stok residu sebesar ' . number_format($volumeKg, 1, ',', '.') . ' kg kembali tersedia.',
        ];
    }
}
