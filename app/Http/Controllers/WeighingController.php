<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\WasteType;
use App\Models\WeighingItem;
use App\Models\WeighingSession;
use App\Livewire\Pages\Weighing\Index as WeighingComponent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WeighingController extends Controller
{
    /**
     * Menampilkan halaman modul Penimbangan Sampah Harian.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan']) && !$user->can('weighing.view'))) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Penimbangan.');
        }

        return app(WeighingComponent::class)();
    }

    /**
     * Memvalidasi dan menyimpan sesi penimbangan sampah harian beserta item rinciannya.
     */
    public function store(array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) && !$user->can('weighing.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mencatat data penimbangan.');
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'waste_source_id' => ['nullable', 'exists:waste_sources,id'],
            'weigh_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'items.*.volume_m3' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'weigh_date.required' => 'Tanggal penimbangan wajib diisi.',
            'items.required' => 'Item penimbangan wajib disediakan.',
        ])->validate();

        // Validasi minimal ada satu jenis sampah yang diisi bobot > 0
        $items = $data['items'] ?? [];
        $hasFilledItem = false;
        foreach ($items as $item) {
            if (!empty($item['weight_kg']) && floatval($item['weight_kg']) > 0) {
                $hasFilledItem = true;
                break;
            }
        }

        if (!$hasFilledItem) {
            throw ValidationException::withMessages([
                'formItems' => 'Harap isi bobot timbangan minimal untuk salah satu jenis sampah.',
            ]);
        }

        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $campusToSave = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;

        $session = WeighingSession::create([
            'campus_id' => $campusToSave,
            'waste_source_id' => !empty($data['waste_source_id']) ? (int) $data['waste_source_id'] : null,
            'weigh_date' => $data['weigh_date'],
            'created_by' => auth()->id(),
            'notes' => !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null,
        ]);

        foreach ($items as $item) {
            $weight = !empty($item['weight_kg']) ? floatval($item['weight_kg']) : 0;
            $volume = !empty($item['volume_m3']) ? floatval($item['volume_m3']) : null;

            if ($weight > 0) {
                WeighingItem::create([
                    'weighing_session_id' => $session->id,
                    'waste_type_id' => (int) $item['waste_type_id'],
                    'weight_kg' => $weight,
                    'volume_m3' => $volume,
                ]);
            }
        }

        ActivityLog::record(
            module: 'weighing',
            action: 'create',
            description: "Mencatat sesi penimbangan sampah harian (#{$session->id}) tanggal {$session->weigh_date}",
            properties: [
                'session_id' => $session->id,
                'campus_id' => $campusToSave,
                'weigh_date' => $session->weigh_date,
                'items_count' => count($items),
            ],
            campusId: $campusToSave
        );

        return [
            'success' => true,
            'session' => $session,
            'message' => 'Data penimbangan harian berhasil disimpan dengan rapi.',
        ];
    }

    /**
     * Memvalidasi dan menghapus sesi penimbangan sampah.
     */
    public function destroy(int $sessionId): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) && !$user->can('weighing.delete')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus data penimbangan.');
        }

        $session = WeighingSession::with(['items.wasteType'])->findOrFail($sessionId);

        // Otorisasi: hanya Super Admin atau user dari kampus bersangkutan
        if ($user->campus_id && $user->campus_id !== $session->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki otoritas untuk menghapus data kampus ini.',
            ];
        }

        // Validasi Integritas: Cek apakah sampah dari sesi ini sudah diserap penjualan atau pengangkutan
        // 1. Cek sampah bernilai jual (is_sellable = true)
        foreach ($session->items as $item) {
            $weight = (float) $item->weight_kg;
            if ($weight <= 0) {
                continue;
            }

            if ($item->wasteType?->is_sellable) {
                $availableStock = WasteType::getAvailableStock($item->waste_type_id, $session->campus_id);
                // Jika bobot sesi ini lebih besar dari sisa stok tersedia saat ini, berarti sebagian sudah terjual!
                if ($weight > $availableStock) {
                    $soldWeight = $weight - $availableStock;
                    $wasteName = $item->wasteType->name;
                    return [
                        'success' => false,
                        'message' => "Tidak dapat menghapus penimbangan: Sampah '{$wasteName}' sudah terjual " . number_format($soldWeight, 1, ',', '.') . " kg (sisa stok saat ini hanya " . number_format($availableStock, 1, ',', '.') . " kg). Harap batalkan transaksi penjualan terkait terlebih dahulu.",
                    ];
                }
            }
        }

        // 2. Cek sampah tidak bernilai jual / residu (is_sellable = false)
        $nonSellableWeight = (float) $session->items
            ->filter(fn($i) => !empty($i->wasteType) && !$i->wasteType->is_sellable)
            ->sum('weight_kg');

        if ($nonSellableWeight > 0) {
            $stockSummary = WasteType::getStockSummary($session->campus_id);
            $availableResidual = (float) ($stockSummary['residual_stock_kg'] ?? 0.0);
            if ($nonSellableWeight > $availableResidual) {
                $pickedUpWeight = $nonSellableWeight - $availableResidual;
                return [
                    'success' => false,
                    'message' => "Tidak dapat menghapus penimbangan: Sampah residu/organik dari sesi ini sudah terangkut oleh vendor sebanyak " . number_format($pickedUpWeight, 1, ',', '.') . " kg (sisa residu saat ini hanya " . number_format($availableResidual, 1, ',', '.') . " kg). Harap batalkan transaksi pengangkutan terkait terlebih dahulu.",
                ];
            }
        }

        ActivityLog::record(
            module: 'weighing',
            action: 'delete',
            description: "Menghapus sesi penimbangan sampah harian (#{$session->id}) tanggal {$session->weigh_date}",
            properties: [
                'session_id' => $session->id,
                'campus_id' => $session->campus_id,
                'weigh_date' => $session->weigh_date,
            ],
            campusId: $session->campus_id
        );

        $session->delete();

        return [
            'success' => true,
            'message' => 'Data sesi penimbangan berhasil dihapus.',
        ];
    }
}

