<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Processing\Index as ProcessingComponent;
use App\Models\ActivityLog;
use App\Models\OrganicOutputCategory;
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
     * Memvalidasi stok organik dan menyimpan catatan pengolahan (input awal -> jadi apa & berat hasil).
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
            'organic_output_category_id' => ['required', 'exists:organic_output_categories,id'],
            'output_weight_kg' => ['required', 'numeric', 'min:0.01', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'process_date.required' => 'Tanggal pengolahan wajib diisi.',
            'organic_output_category_id.required' => 'Jenis hasil olahan (Master Data) wajib dipilih.',
            'output_weight_kg.required' => 'Berat hasil setelah diolah wajib diisi.',
            'output_weight_kg.min' => 'Berat hasil setelah diolah minimal 0.01 kg.',
            'items.required' => 'Rincian sampah organik yang diolah wajib diisi.',
        ])->validate();

        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $campusId = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;

        $outputCategoryId = (int) $data['organic_output_category_id'];
        $outputCategory = OrganicOutputCategory::findOrFail($outputCategoryId);
        $outputWeightKg = round(floatval($data['output_weight_kg']), 2);

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
                'processingItems' => 'Harap isi berat awal minimal untuk salah satu jenis sampah organik.',
            ]);
        }

        $inputWeightKg = round(array_sum($itemsToProcess), 2);
        $processing = null;

        DB::transaction(function () use ($campusId, $data, $inputWeightKg, $outputCategoryId, $outputCategory, $outputWeightKg, $itemsToProcess, &$processing) {
            $processing = OrganicProcessing::create([
                'campus_id' => $campusId,
                'process_date' => $data['process_date'],
                'input_weight_kg' => $inputWeightKg,
                'organic_output_category_id' => $outputCategoryId,
                'output_weight_kg' => $outputWeightKg,
                'compost_output_kg' => 0.00,
                'feed_output_kg' => 0.00,
                'method' => $outputCategory->name,
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
                notes: "Pengolahan organik #{$processing->id}: {$inputWeightKg} kg -> {$outputCategory->name} {$outputWeightKg} kg",
                processedDelta: $inputWeightKg,
                processedOutputDelta: $outputWeightKg
            );
        });

        ActivityLog::record(
            module: 'organic_processing',
            action: 'create',
            description: "Mencatat pengolahan organik (#{$processing->id}) seberat " . number_format($inputWeightKg, 1, ',', '.') . " kg menjadi {$outputCategory->name} (" . number_format($outputWeightKg, 1, ',', '.') . " kg)",
            properties: [
                'processing_id' => $processing->id,
                'campus_id' => $campusId,
                'input_weight_kg' => $inputWeightKg,
                'organic_output_category_id' => $outputCategoryId,
                'output_weight_kg' => $outputWeightKg,
            ],
            campusId: $campusId
        );

        return [
            'success' => true,
            'processing' => $processing->fresh(['outputCategory', 'items.wasteType']),
            'message' => 'Pengolahan ' . number_format($inputWeightKg, 1, ',', '.') . ' kg organik menjadi ' . $outputCategory->name . ' (' . number_format($outputWeightKg, 1, ',', '.') . ' kg) tersimpan.',
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
            'organic_output_category_id' => ['required', 'exists:organic_output_categories,id'],
            'output_weight_kg' => ['required', 'numeric', 'min:0.01', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'process_date.required' => 'Tanggal pengolahan wajib diisi.',
            'organic_output_category_id.required' => 'Jenis hasil olahan (Master Data) wajib dipilih.',
            'output_weight_kg.required' => 'Berat hasil setelah diolah wajib diisi.',
            'output_weight_kg.min' => 'Berat hasil setelah diolah minimal 0.01 kg.',
            'items.required' => 'Rincian sampah organik yang diolah wajib diisi.',
        ])->validate();

        $outputCategoryId = (int) $data['organic_output_category_id'];
        $outputCategory = OrganicOutputCategory::findOrFail($outputCategoryId);
        $outputWeightKg = round(floatval($data['output_weight_kg']), 2);

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
                'editItems' => 'Harap isi berat awal minimal untuk salah satu jenis sampah organik.',
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
        $deltaOutput = $outputWeightKg - (float) $processing->output_weight_kg;

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

        DB::transaction(function () use ($processing, $campusId, $data, $newInputWeight, $outputCategoryId, $outputCategory, $outputWeightKg, $itemsToProcess, $itemsDelta, $deltaInput, $deltaOutput) {
            $processing->update([
                'process_date' => $data['process_date'],
                'input_weight_kg' => $newInputWeight,
                'organic_output_category_id' => $outputCategoryId,
                'output_weight_kg' => $outputWeightKg,
                'method' => $outputCategory->name,
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
                notes: "Pembaruan pengolahan organik #{$processing->id}: {$newInputWeight} kg -> {$outputCategory->name} {$outputWeightKg} kg",
                processedDelta: $deltaInput,
                processedOutputDelta: $deltaOutput
            );
        });

        ActivityLog::record(
            module: 'organic_processing',
            action: 'update',
            description: "Memperbarui pengolahan organik (#{$processing->id}) seberat " . number_format($newInputWeight, 1, ',', '.') . " kg menjadi {$outputCategory->name} (" . number_format($outputWeightKg, 1, ',', '.') . " kg)",
            properties: [
                'processing_id' => $processing->id,
                'campus_id' => $campusId,
                'input_weight_kg' => $newInputWeight,
                'organic_output_category_id' => $outputCategoryId,
                'output_weight_kg' => $outputWeightKg,
            ],
            campusId: $campusId
        );

        return [
            'success' => true,
            'processing' => $processing->fresh(['outputCategory', 'items.wasteType']),
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
        $outputWeightKg = (float) $processing->output_weight_kg;
        $campusId = (int) $processing->campus_id;
        $id = (int) $processing->id;

        $itemsDelta = [];
        foreach ($processing->items as $it) {
            $itemsDelta[] = [
                'waste_type_id' => (int) $it->waste_type_id,
                'processed_delta' => -(float) $it->weight_kg,
            ];
        }

        DB::transaction(function () use ($processing, $campusId, $id, $inputWeightKg, $outputWeightKg, $itemsDelta) {
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
                processedOutputDelta: -$outputWeightKg
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
