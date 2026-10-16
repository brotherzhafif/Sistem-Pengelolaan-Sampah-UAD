<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Pickups\Index as PickupComponent;
use App\Models\ActivityLog;
use App\Models\BukuBesar;
use App\Models\FinancialTransaction;
use App\Models\GeneralLedger;
use App\Models\Keuangan;
use App\Models\Pickup;
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
            'volume_kg' => ['required', 'numeric', 'min:0.01', 'max:50000'],
            'cost_per_kg' => ['required', 'numeric', 'min:0'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_plate' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'vendor_id.required' => 'Pihak vendor pengangkut wajib dipilih.',
            'pickup_date.required' => 'Tanggal pengangkutan wajib diisi.',
            'volume_kg.required' => 'Bobot muatan residu wajib diisi.',
            'volume_kg.min' => 'Bobot muatan minimal 0.01 kg.',
            'cost_per_kg.required' => 'Biaya per kg wajib diisi.',
        ])->validate();

        $volume = floatval($data['volume_kg']);
        $costPerKg = floatval($data['cost_per_kg']);
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $campusId = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;
        $vendorId = (int) $data['vendor_id'];

        // Validasi ketersediaan stok residu di kampus bersangkutan
        $stockSummary = WasteType::getStockSummary($campusId);
        $availableResidual = $stockSummary['residual_stock_kg'];

        if ($volume > $availableResidual) {
            throw ValidationException::withMessages([
                'formVolumeKg' => "Muatan melebihi stok residu tersedia di kampus ini (Tersedia: " . number_format($availableResidual, 1, ',', '.') . " kg, Diminta: " . number_format($volume, 1, ',', '.') . " kg).",
            ]);
        }

        $totalCost = round($volume * $costPerKg, 2);

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
     * Memvalidasi dan memperbarui data transaksi pengangkutan residu.
     */
    public function update(int $pickupId, array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) && !$user->can('logistics.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah data pengangkutan.');
        }

        $pickup = Pickup::findOrFail($pickupId);

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
            'volume_kg' => ['required', 'numeric', 'min:0.01', 'max:50000'],
            'cost_per_kg' => ['required', 'numeric', 'min:0'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_plate' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'vendor_id.required' => 'Pihak vendor pengangkut wajib dipilih.',
            'pickup_date.required' => 'Tanggal pengangkutan wajib diisi.',
            'volume_kg.required' => 'Bobot muatan residu wajib diisi.',
            'volume_kg.min' => 'Bobot muatan minimal 0.01 kg.',
            'cost_per_kg.required' => 'Biaya per kg wajib diisi.',
        ])->validate();

        $bindingInfo = $pickup->getBindingInfo();
        $targetCampusId = (int) $data['campus_id'];

        if ($bindingInfo['is_bound'] && $targetCampusId !== $pickup->campus_id) {
            throw ValidationException::withMessages([
                'editCampusId' => 'Unit kampus tidak dapat diubah karena transaksi ini telah terikat dengan Jurnal Buku Kas.',
            ]);
        }

        $oldVolume = (float) $pickup->volume_kg;
        $newVolume = floatval($data['volume_kg']);
        $newCostPerKg = floatval($data['cost_per_kg']);
        $newTotalCost = round($newVolume * $newCostPerKg, 2);

        // Validasi stok jika volume bertambah
        $deltaVolume = $newVolume - $oldVolume;
        if ($deltaVolume > 0) {
            $stockSummary = WasteType::getStockSummary($pickup->campus_id);
            $availableResidual = (float) ($stockSummary['residual_stock_kg'] ?? 0.0);

            if ($deltaVolume > $availableResidual) {
                $maxAllowable = $oldVolume + $availableResidual;
                throw ValidationException::withMessages([
                    'editVolumeKg' => "Penambahan muatan (" . number_format($newVolume, 1, ',', '.') . " kg) melebihi sisa stok residu TPS saat ini (" . number_format($availableResidual, 1, ',', '.') . " kg). Maksimal muatan yang dapat diinput adalah " . number_format($maxAllowable, 1, ',', '.') . " kg.",
                ]);
            }
        }

        $oldDate = $pickup->pickup_date?->format('Y-m-d');
        $newDate = $data['pickup_date'];

        DB::transaction(function () use ($pickup, $data, $targetCampusId, $newVolume, $newCostPerKg, $newTotalCost, $oldDate, $newDate) {
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

            // Sinkronisasi Transaksi Keuangan Buku Kas
            $ft = FinancialTransaction::where('source', 'pickup')->where('ref_id', $pickup->id)->first();
            $vendorName = $pickup->vendor?->name ?? 'Mitra Pengangkut';

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
            ],
            campusId: $pickup->campus_id
        );

        $newBalance = BukuBesar::getLatestBalance($pickup->campus_id);

        return [
            'success' => true,
            'pickup' => $pickup,
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

        $pickup = Pickup::findOrFail($pickupId);

        // Otorisasi: hanya Super Admin atau user dari kampus bersangkutan
        if ($user->campus_id && $user->campus_id !== $pickup->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki otoritas untuk menghapus data pengangkutan kampus ini.',
            ];
        }

        // Validasi Keterikatan: Cek apakah data pengangkutan terikat dengan Buku Kas
        $bindingInfo = $pickup->getBindingInfo();
        if ($bindingInfo['is_bound']) {
            return [
                'success' => false,
                'message' => "Tidak dapat menghapus data pengangkutan (#{$pickup->id}): " . $bindingInfo['reason'],
            ];
        }

        ActivityLog::record(
            module: 'pickups',
            action: 'delete',
            description: "Menghapus data pengangkutan residu (#{$pickup->id}) seberat " . number_format((float) $pickup->volume_kg, 1, ',', '.') . " kg",
            properties: [
                'pickup_id' => $pickup->id,
                'campus_id' => $pickup->campus_id,
                'volume_kg' => (float) $pickup->volume_kg,
                'total_cost' => (float) $pickup->total_cost,
            ],
            campusId: $pickup->campus_id
        );

        $pickup->delete(); // Observer otomatis hapus jurnal debet keuangan & re-sync buku besar

        return [
            'success' => true,
            'message' => 'Data pengangkutan residu berhasil dihapus dan jurnal keuangan disesuaikan.',
        ];
    }
}
