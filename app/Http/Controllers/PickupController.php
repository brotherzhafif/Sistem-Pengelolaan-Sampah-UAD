<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Pickups\Index as PickupComponent;
use App\Models\Pickup;
use App\Services\LedgerService;
use App\Services\StockService;
use Illuminate\Http\Request;
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
    public function store(array $data, StockService $stockService, LedgerService $ledgerService): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) && !$user->can('logistics.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mencatat data pengangkutan.');
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'pickup_date' => ['required', 'date'],
            'volume_kg' => ['required', 'numeric', 'min:0.1', 'max:50000'],
            'cost_per_kg' => ['required', 'numeric', 'min:0'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle_plate' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'vendor_id.required' => 'Pihak vendor pengangkut wajib dipilih.',
            'pickup_date.required' => 'Tanggal pengangkutan wajib diisi.',
            'volume_kg.required' => 'Bobot muatan residu wajib diisi.',
            'volume_kg.min' => 'Bobot muatan minimal 0.1 kg.',
            'cost_per_kg.required' => 'Biaya per kg wajib diisi.',
        ])->validate();

        $volume = floatval($data['volume_kg']);
        $costPerKg = floatval($data['cost_per_kg']);
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $campusId = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;
        $vendorId = (int) $data['vendor_id'];

        // Validasi ketersediaan stok residu di kampus bersangkutan
        $stockSummary = $stockService->getStockSummary($campusId);
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

        $newBalance = $ledgerService->getLatestBalance($campusId);
        $toastMsg = "Pengangkutan " . number_format($volume, 1, ',', '.') . " kg tersimpan → Biaya Rp " . number_format($totalCost, 0, ',', '.') . " → Saldo: Rp " . number_format($newBalance, 0, ',', '.');

        return [
            'success' => true,
            'pickup' => $pickup,
            'total_cost' => $totalCost,
            'new_balance' => $newBalance,
            'message' => $toastMsg,
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

        $pickup->delete(); // Observer otomatis hapus jurnal debet keuangan & re-sync buku besar

        return [
            'success' => true,
            'message' => 'Data pengangkutan residu berhasil dihapus dan jurnal keuangan disesuaikan.',
        ];
    }
}
