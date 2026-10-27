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
     * Memvalidasi dan memperbarui sesi penimbangan sampah harian beserta item rinciannya.
     */
    public function update(int $sessionId, array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) && !$user->can('weighing.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk memperbarui data penimbangan.');
        }

        $session = WeighingSession::with(['items.wasteType'])->findOrFail($sessionId);

        // Otorisasi kampus
        if ($user->campus_id && $user->campus_id !== $session->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki otoritas untuk memperbarui data kampus ini.',
            ];
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
                'editItems' => 'Harap isi bobot timbangan minimal untuk salah satu jenis sampah.',
            ]);
        }

        $bindingInfo = $session->getBindingInfo();
        $targetCampusId = (int) $data['campus_id'];

        if ($bindingInfo['is_bound'] && $targetCampusId !== $session->campus_id) {
            throw ValidationException::withMessages([
                'editCampusId' => 'Unit kampus tidak dapat diubah karena sesi ini telah terikat dengan transaksi penjualan atau pengangkutan.',
            ]);
        }

        // Validasi pengurangan bobot terhadap sisa stok tersedia di kampus
        $oldWeights = [];
        foreach ($session->items as $oldItem) {
            $oldWeights[$oldItem->waste_type_id] = (float) $oldItem->weight_kg;
        }

        foreach ($items as $item) {
            $typeId = (int) $item['waste_type_id'];
            $newWeight = !empty($item['weight_kg']) ? floatval($item['weight_kg']) : 0.0;
            $oldWeight = $oldWeights[$typeId] ?? 0.0;

            if ($newWeight < $oldWeight) {
                $reduction = $oldWeight - $newWeight;
                $wasteType = WasteType::find($typeId);
                
                if ($wasteType && $wasteType->is_sellable) {
                    $availableStock = WasteType::getAvailableStock($typeId, $session->campus_id);
                    if ($reduction > $availableStock) {
                        $minAllowed = max(0, $oldWeight - $availableStock);
                        throw ValidationException::withMessages([
                            'editItems' => "Bobot '{$wasteType->name}' tidak dapat dikurangi menjadi {$newWeight} kg karena sisa stok di TPS saat ini hanya " . number_format($availableStock, 1, ',', '.') . " kg (sebagian telah terjual). Bobot minimal yang diperbolehkan adalah " . number_format($minAllowed, 1, ',', '.') . " kg.",
                        ]);
                    }
                } elseif ($wasteType && !$wasteType->is_sellable) {
                    $stockSummary = WasteType::getStockSummary($session->campus_id);
                    $availableResidual = (float) ($stockSummary['residual_stock_kg'] ?? 0.0);
                    if ($reduction > $availableResidual) {
                        $minAllowed = max(0, $oldWeight - $availableResidual);
                        throw ValidationException::withMessages([
                            'editItems' => "Bobot residu '{$wasteType->name}' tidak dapat dikurangi menjadi {$newWeight} kg karena sisa residu di TPS hanya " . number_format($availableResidual, 1, ',', '.') . " kg (sebagian telah diangkut). Bobot minimal yang diperbolehkan adalah " . number_format($minAllowed, 1, ',', '.') . " kg.",
                        ]);
                    }
                }
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($session, $data, $items, $targetCampusId) {
            $session->update([
                'campus_id' => $targetCampusId,
                'waste_source_id' => !empty($data['waste_source_id']) ? (int) $data['waste_source_id'] : null,
                'weigh_date' => $data['weigh_date'],
                'notes' => !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null,
            ]);

            // Re-sync items
            $session->items()->delete();

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
        });

        ActivityLog::record(
            module: 'weighing',
            action: 'update',
            description: "Memperbarui sesi penimbangan sampah harian (#{$session->id}) tanggal {$session->weigh_date}",
            properties: [
                'session_id' => $session->id,
                'campus_id' => $targetCampusId,
                'weigh_date' => $session->weigh_date,
                'items_count' => count($items),
            ],
            campusId: $targetCampusId
        );

        return [
            'success' => true,
            'session' => $session->fresh(['items.wasteType']),
            'message' => 'Data sesi penimbangan berhasil diperbarui.',
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

        $deductionInfo = $session->getDeductionSummary();

        // 1. Kasus C: Seluruh sampah telah habis terjual / diangkut (0 kg sisa)
        if ($deductionInfo['is_fully_locked']) {
            $featuresStr = !empty($deductionInfo['features']) ? implode(' & ', $deductionInfo['features']) : 'Penjualan / Pengangkutan';
            return [
                'success' => false,
                'message' => "Tidak dapat menghapus penimbangan (#{$session->id}): Seluruh sampah (" . number_format($deductionInfo['total_session_weight'], 1, ',', '.') . " kg) telah habis tercatat pada {$featuresStr}. Silakan hapus transaksi terkait terlebih dahulu jika data penimbangan ini keliru.",
            ];
        }

        // 2. Kasus A: Belum ada yang terjual/diangkut sama sekali -> Hapus bersih 100%
        if ($deductionInfo['can_fully_delete']) {
            $totalWeight = $deductionInfo['total_session_weight'];

            ActivityLog::record(
                module: 'weighing',
                action: 'delete',
                description: "Menghapus seluruh sesi penimbangan sampah (#{$session->id}) seberat " . number_format($totalWeight, 1, ',', '.') . " kg",
                properties: [
                    'session_id' => $session->id,
                    'campus_id' => $session->campus_id,
                    'weigh_date' => $session->weigh_date,
                    'total_weight' => $totalWeight,
                ],
                campusId: $session->campus_id
            );

            \Illuminate\Support\Facades\DB::transaction(function () use ($session) {
                $session->items()->delete();
                $session->delete();
            });

            return [
                'success' => true,
                'message' => 'Data sesi penimbangan berhasil dihapus seluruhnya (' . number_format($totalWeight, 1, ',', '.') . ' kg).',
            ];
        }

        // 3. Kasus B: Sebagian sudah terjual/diangkut -> Potong bagian yang belum terjual/diangkut
        $deductedKg = $deductionInfo['total_deductible'];
        $retainedKg = $deductionInfo['total_retained'];

        ActivityLog::record(
            module: 'weighing',
            action: 'update',
            description: "Menyesuaikan penimbangan sampah (#{$session->id}): Memotong " . number_format($deductedKg, 1, ',', '.') . " kg (belum terjual/diangkut) dan menyisakan " . number_format($retainedKg, 1, ',', '.') . " kg (sudah terikat transaksi)",
            properties: [
                'session_id' => $session->id,
                'campus_id' => $session->campus_id,
                'deducted_kg' => $deductedKg,
                'retained_kg' => $retainedKg,
            ],
            campusId: $session->campus_id
        );

        \Illuminate\Support\Facades\DB::transaction(function () use ($session, $deductionInfo) {
            foreach ($deductionInfo['items_breakdown'] as $b) {
                $item = \App\Models\WeighingItem::find($b['item_id']);
                if (!$item) continue;

                if ($b['retained_weight'] <= 0.0001) {
                    $item->delete();
                } else {
                    $item->update(['weight_kg' => $b['retained_weight']]);
                }
            }

            // Jika semua item terhapus, hapus juga sesinya
            if ($session->items()->count() === 0) {
                $session->delete();
            }
        });

        return [
            'success' => true,
            'message' => "Data penimbangan berhasil disesuaikan: Berat dipotong sebesar " . number_format($deductedKg, 1, ',', '.') . " kg (yang belum terjual/diangkut) dan menyisakan " . number_format($retainedKg, 1, ',', '.') . " kg karena telah tercatat pada transaksi penjualan/pengangkutan.",
        ];
    }
}

