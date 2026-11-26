<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Processing\Index as ProcessingComponent;
use App\Models\ActivityLog;
use App\Models\OrganicProcessing;
use App\Models\OrganicProcessingItem;
use App\Models\WasteStockSummary;
use App\Models\WasteType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrganicProcessingController extends Controller
{
    /**
     * Menampilkan halaman modul Pengolahan Sampah Organik Mandiri.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole([
            'super_admin', 'Super Admin',
            'admin_kampus', 'Admin Kampus',
            'koordinator_tps3r', 'Koordinator TPS3R',
            'petugas_tps', 'Petugas TPS',
            'operator_timbangan', 'Operator Timbangan',
            'viewer', 'Viewer',
            'auditor_pimpinan', 'Auditor / Pimpinan',
        ]) && !$user->can('weighing.view') && !$user->can('logistics.view'))) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Pengolahan Organik.');
        }

        return app(ProcessingComponent::class)();
    }

    /**
     * Memvalidasi stok organik dan menyimpan catatan pengolahan mandiri beserta hasil olah (pupuk & pakan).
     */
    public function store(array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole([
            'super_admin', 'Super Admin',
            'admin_kampus', 'Admin Kampus',
            'koordinator_tps3r', 'Koordinator TPS3R',
            'petugas_tps', 'Petugas TPS',
            'operator_timbangan', 'Operator Timbangan',
        ]) && !$user->can('weighing.create') && !$user->can('logistics.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mencatat pengolahan organik.');
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'process_date' => ['required', 'date'],
            'compost_output_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'feed_output_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'process_date.required' => 'Tanggal pengolahan wajib diisi.',
            'items.required' => 'Rincian sampah organik yang diolah wajib diisi.',
        ])->validate();

        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $campusId = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;

        $compostKg = round(floatval($data['compost_output_kg'] ?? 0), 2);
        $feedKg = round(floatval($data['feed_output_kg'] ?? 0), 2);

        if (($compostKg + $feedKg) <= 0) {
            throw ValidationException::withMessages([
                'formOutputs' => 'Harap isi berat hasil olah (pupuk dan/atau pakan) minimal > 0 kg.',
            ]);
        }

        $itemsToProcess = [];
        foreach ($data['items'] as $index => $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            if ($weight <= 0) {
                continue;
            }

            $wasteTypeId = (int) $item['waste_type_id'];
            $wasteType = WasteType::findOrFail($wasteTypeId);

            if (!$wasteType->isOrganicCategory()) {
                throw ValidationException::withMessages([
                    "processingItems.{$index}.weight_kg" => "Jenis sampah '{$wasteType->name}' bukan kategori Organik dan tidak dapat diolah pada menu ini.",
                ]);
            }

            $availableStock = WasteType::getAvailableStock($wasteTypeId, $campusId);
            if ($weight > $availableStock) {
                throw ValidationException::withMessages([
                    "processingItems.{$index}.weight_kg" => "Stok '{$wasteType->name}' tidak mencukupi (Tersedia: " . number_format($availableStock, 1, ',', '.') . " kg, Diminta: " . number_format($weight, 1, ',', '.') . " kg).",
                ]);
            }

            $itemsToProcess[$wasteTypeId] = ($itemsToProcess[$wasteTypeId] ?? 0.0) + $weight;
        }

        if (empty($itemsToProcess)) {
            throw ValidationException::withMessages([
                'processingItems' => 'Harap isi berat bahan sampah organik minimal untuk salah satu jenis.',
            ]);
        }

        $inputWeightKg = round(array_sum($itemsToProcess), 2);
        $processing = null;

        DB::transaction(function () use ($campusId, $data, $inputWeightKg, $compostKg, $feedKg, $itemsToProcess, &$processing) {
            $processing = OrganicProcessing::create([
                'campus_id' => $campusId,
                'process_date' => $data['process_date'],
                'input_weight_kg' => $inputWeightKg,
                'compost_output_kg' => $compostKg,
                'feed_output_kg' => $feedKg,
                'method' => !empty($data['method']) ? strip_tags(trim($data['method'])) : null,
                'created_by' => auth()->id(),
                'notes' => !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null,
            ]);

            $itemsDelta = [];
            foreach ($itemsToProcess as $wasteTypeId => $weight) {
                OrganicProcessingItem::create([
                    'organic_processing_id' => $processing->id,
                    'waste_type_id' => $wasteTypeId,
                    'weight_kg' => $weight,
                ]);

                $itemsDelta[] = [
                    'waste_type_id' => $wasteTypeId,
                    'processed_delta' => (float) $weight,
                ];
            }

            WasteStockSummary::applyMutation(
                campusId: $campusId,
                referenceType: 'organic_processing',
                referenceId: $processing->id,
                action: 'create',
                items: $itemsDelta,
                notes: "Pengolahan organik mandiri #{$processing->id} (Pupuk {$compostKg} kg, Pakan {$feedKg} kg)",
                processedDelta: $inputWeightKg,
                compostDelta: $compostKg,
                feedDelta: $feedKg
            );
        });

        ActivityLog::record(
            module: 'organic_processing',
            action: 'create',
            description: "Mencatat pengolahan organik (#{$processing->id}) seberat " . number_format($inputWeightKg, 1, ',', '.') . " kg menjadi pupuk " . number_format($compostKg, 1, ',', '.') . " kg & pakan " . number_format($feedKg, 1, ',', '.') . " kg",
            properties: [
                'processing_id' => $processing->id,
                'campus_id' => $campusId,
                'input_weight_kg' => $inputWeightKg,
                'compost_output_kg' => $compostKg,
                'feed_output_kg' => $feedKg,
            ],
            campusId: $campusId
        );

        return [
            'success' => true,
            'processing' => $processing,
            'message' => 'Pengolahan ' . number_format($inputWeightKg, 1, ',', '.') . ' kg organik tersimpan (Pupuk: ' . number_format($compostKg, 1, ',', '.') . ' kg, Pakan: ' . number_format($feedKg, 1, ',', '.') . ' kg).',
        ];
    }

    /**
     * Memperbarui catatan pengolahan sampah organik beserta penyesuaian stok.
     */
    public function update(int $processingId, array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole([
            'super_admin', 'Super Admin',
            'admin_kampus', 'Admin Kampus',
            'koordinator_tps3r', 'Koordinator TPS3R',
            'petugas_tps', 'Petugas TPS',
            'operator_timbangan', 'Operator Timbangan',
        ]) && !$user->can('weighing.create') && !$user->can('logistics.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah data pengolahan organik.');
        }

        $processing = OrganicProcessing::with('items')->findOrFail($processingId);

        if ($user->campus_id && $user->campus_id !== $processing->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            throw ValidationException::withMessages([
                'editCampusId' => 'Anda tidak memiliki otoritas untuk mengubah data kampus ini.',
            ]);
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'process_date' => ['required', 'date'],
            'compost_output_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'feed_output_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'process_date.required' => 'Tanggal pengolahan wajib diisi.',
            'items.required' => 'Rincian sampah organik yang diolah wajib diisi.',
        ])->validate();

        $compostKg = round(floatval($data['compost_output_kg'] ?? 0), 2);
        $feedKg = round(floatval($data['feed_output_kg'] ?? 0), 2);

        if (($compostKg + $feedKg) <= 0) {
            throw ValidationException::withMessages([
                'editOutputs' => 'Harap isi berat hasil olah (pupuk dan/atau pakan) minimal > 0 kg.',
            ]);
        }

        $campusId = (int) $processing->campus_id;
        $existingWeights = $processing->items->pluck('weight_kg', 'waste_type_id')->map(fn ($w) => (float) $w)->toArray();

        $itemsToProcess = [];
        foreach ($data['items'] as $index => $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            if ($weight <= 0) {
                continue;
            }

            $wasteTypeId = (int) $item['waste_type_id'];
            $wasteType = WasteType::findOrFail($wasteTypeId);

            if (!$wasteType->isOrganicCategory()) {
                throw ValidationException::withMessages([
                    "editItems.{$index}.weight_kg" => "Jenis sampah '{$wasteType->name}' bukan kategori Organik.",
                ]);
            }

            $itemsToProcess[$wasteTypeId] = ($itemsToProcess[$wasteTypeId] ?? 0.0) + $weight;
        }

        if (empty($itemsToProcess)) {
            throw ValidationException::withMessages([
                'editItems' => 'Harap isi berat bahan sampah organik minimal untuk salah satu jenis.',
            ]);
        }

        foreach ($data['items'] as $index => $item) {
            $wasteTypeId = (int) $item['waste_type_id'];
            if (empty($itemsToProcess[$wasteTypeId])) {
                continue;
            }
            $newWeight = $itemsToProcess[$wasteTypeId];
            $oldWeight = $existingWeights[$wasteTypeId] ?? 0.0;
            $delta = $newWeight - $oldWeight;

            if ($delta > 0) {
                $availableStock = WasteType::getAvailableStock($wasteTypeId, $campusId);
                if ($delta > $availableStock) {
                    $wasteType = WasteType::find($wasteTypeId);
                    $maxAllowable = $oldWeight + $availableStock;
                    throw ValidationException::withMessages([
                        "editItems.{$index}.weight_kg" => "Penambahan bahan '" . ($wasteType?->name ?? 'Organik') . "' melebihi stok TPS (Maksimal: " . number_format($maxAllowable, 1, ',', '.') . " kg).",
                    ]);
                }
            }
        }

        $oldInputWeight = (float) $processing->input_weight_kg;
        $newInputWeight = round(array_sum($itemsToProcess), 2);
        $deltaInput = $newInputWeight - $oldInputWeight;
        $deltaCompost = $compostKg - (float) $processing->compost_output_kg;
        $deltaFeed = $feedKg - (float) $processing->feed_output_kg;

        $allTypeIds = array_unique(array_merge(array_keys($existingWeights), array_keys($itemsToProcess)));
        $itemsDelta = [];
        foreach ($allTypeIds as $tId) {
            $diff = ($itemsToProcess[$tId] ?? 0.0) - ($existingWeights[$tId] ?? 0.0);
            if (abs($diff) > 0.0001) {
                $itemsDelta[] = [
                    'waste_type_id' => $tId,
                    'processed_delta' => $diff,
                ];
            }
        }

        DB::transaction(function () use ($processing, $campusId, $data, $newInputWeight, $compostKg, $feedKg, $itemsToProcess, $itemsDelta, $deltaInput, $deltaCompost, $deltaFeed) {
            $processing->update([
                'process_date' => $data['process_date'],
                'input_weight_kg' => $newInputWeight,
                'compost_output_kg' => $compostKg,
                'feed_output_kg' => $feedKg,
                'method' => !empty($data['method']) ? strip_tags(trim($data['method'])) : null,
                'notes' => !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null,
            ]);

            $processing->items()->delete();
            foreach ($itemsToProcess as $wasteTypeId => $weight) {
                OrganicProcessingItem::create([
                    'organic_processing_id' => $processing->id,
                    'waste_type_id' => $wasteTypeId,
                    'weight_kg' => $weight,
                ]);
            }

            WasteStockSummary::applyMutation(
                campusId: $campusId,
                referenceType: 'organic_processing',
                referenceId: $processing->id,
                action: 'update',
                items: $itemsDelta,
                notes: "Pembaruan pengolahan organik #{$processing->id}",
                processedDelta: $deltaInput,
                compostDelta: $deltaCompost,
                feedDelta: $deltaFeed
            );
        });

        ActivityLog::record(
            module: 'organic_processing',
            action: 'update',
            description: "Memperbarui pengolahan organik (#{$processing->id}) seberat " . number_format($newInputWeight, 1, ',', '.') . " kg (Pupuk " . number_format($compostKg, 1, ',', '.') . " kg, Pakan " . number_format($feedKg, 1, ',', '.') . " kg)",
            properties: [
                'processing_id' => $processing->id,
                'campus_id' => $campusId,
                'input_weight_kg' => $newInputWeight,
                'compost_output_kg' => $compostKg,
                'feed_output_kg' => $feedKg,
            ],
            campusId: $campusId
        );

        return [
            'success' => true,
            'processing' => $processing,
            'message' => 'Data pengolahan sampah organik berhasil diperbarui.',
        ];
    }

    /**
     * Menghapus catatan pengolahan organik dan mengembalikan stok organik ke TPS.
     */
    public function destroy(int $processingId): array
    {
        $user = auth()->user();
        if (!$user->hasRole([
            'super_admin', 'Super Admin',
            'admin_kampus', 'Admin Kampus',
            'koordinator_tps3r', 'Koordinator TPS3R',
            'petugas_tps', 'Petugas TPS',
            'operator_timbangan', 'Operator Timbangan',
        ]) && !$user->can('weighing.create') && !$user->can('logistics.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus data pengolahan organik.');
        }

        $processing = OrganicProcessing::with('items')->findOrFail($processingId);

        if ($user->campus_id && $user->campus_id !== $processing->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki otoritas untuk menghapus data pengolahan kampus ini.',
            ];
        }

        $inputWeightKg = (float) $processing->input_weight_kg;
        $compostKg = (float) $processing->compost_output_kg;
        $feedKg = (float) $processing->feed_output_kg;
        $campusId = (int) $processing->campus_id;
        $id = (int) $processing->id;

        $itemsDelta = [];
        foreach ($processing->items as $it) {
            $itemsDelta[] = [
                'waste_type_id' => (int) $it->waste_type_id,
                'processed_delta' => -(float) $it->weight_kg,
            ];
        }

        DB::transaction(function () use ($processing, $campusId, $id, $inputWeightKg, $compostKg, $feedKg, $itemsDelta) {
            $processing->items()->delete();
            $processing->delete();

            WasteStockSummary::applyMutation(
                campusId: $campusId,
                referenceType: 'organic_processing',
                referenceId: $id,
                action: 'delete',
                items: $itemsDelta,
                notes: "Penghapusan pengolahan organik #{$id} (stok organik dikembalikan)",
                processedDelta: -$inputWeightKg,
                compostDelta: -$compostKg,
                feedDelta: -$feedKg
            );
        });

        ActivityLog::record(
            module: 'organic_processing',
            action: 'delete',
            description: "Menghapus pengolahan organik (#{$id}) seberat " . number_format($inputWeightKg, 1, ',', '.') . " kg",
            properties: [
                'processing_id' => $id,
                'campus_id' => $campusId,
                'input_weight_kg' => $inputWeightKg,
            ],
            campusId: $campusId
        );

        return [
            'success' => true,
            'message' => 'Data pengolahan organik dihapus. Stok organik ' . number_format($inputWeightKg, 1, ',', '.') . ' kg dikembalikan ke TPS.',
        ];
    }
}
