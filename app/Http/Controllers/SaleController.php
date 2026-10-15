<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Sales\Index as SalesComponent;
use App\Models\ActivityLog;
use App\Models\BukuBesar;
use App\Models\Buyer;
use App\Models\FinancialTransaction;
use App\Models\GeneralLedger;
use App\Models\Keuangan;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\WasteType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Menampilkan halaman modul Penjualan Sampah Terpilah / Anorganik.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_penjualan', 'Petugas Penjualan', 'pengurus_bank_sampah', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan']) && !$user->can('bank_sampah.sale.view'))) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Penjualan.');
        }

        return app(SalesComponent::class)();
    }

    /**
     * Memvalidasi stok aktual dan menyimpan transaksi penjualan beserta item rinciannya.
     */
    public function store(array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_penjualan', 'Petugas Penjualan', 'pengurus_bank_sampah']) && !$user->can('bank_sampah.sale.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mencatat transaksi penjualan.');
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'buyer_id' => ['required', 'exists:buyers,id'],
            'sale_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'items.*.price_per_kg' => ['required', 'numeric', 'min:0'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'buyer_id.required' => 'Pihak pembeli / lapak wajib dipilih.',
            'sale_date.required' => 'Tanggal penjualan wajib diisi.',
            'items.required' => 'Item rincian sampah wajib disediakan.',
        ])->validate();

        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $targetCampusId = $isSuperAdmin ? (int) $data['campus_id'] : (int) $user->campus_id;

        $itemsToSell = [];
        foreach ($data['items'] as $index => $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            $price = floatval($item['price_per_kg'] ?? 0);

            if ($weight > 0) {
                // Validasi ketersediaan stok aktual di kampus bersangkutan
                $availableStock = WasteType::getAvailableStock((int) $item['waste_type_id'], $targetCampusId);

                if ($weight > $availableStock) {
                    $wasteName = $item['waste_name'] ?? 'Sampah';
                    throw ValidationException::withMessages([
                        "saleItems.{$index}.weight_kg" => "Stok '{$wasteName}' tidak mencukupi (Tersedia: " . number_format($availableStock, 1) . " kg, Diminta: " . number_format($weight, 1) . " kg).",
                    ]);
                }

                $itemsToSell[] = [
                    'waste_type_id' => (int) $item['waste_type_id'],
                    'weight_kg' => $weight,
                    'price_per_kg' => $price,
                    'subtotal' => round($weight * $price, 2),
                ];
            }
        }

        if (empty($itemsToSell)) {
            throw ValidationException::withMessages([
                'saleItems' => 'Harap isi bobot penjualan minimal untuk salah satu jenis sampah.',
            ]);
        }

        $totalAmount = array_sum(array_column($itemsToSell, 'subtotal'));

        $sale = Sale::create([
            'campus_id' => $targetCampusId,
            'buyer_id' => (int) $data['buyer_id'],
            'sale_date' => $data['sale_date'],
            'total_amount' => $totalAmount,
            'created_by' => auth()->id(),
            'notes' => !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null,
        ]);

        foreach ($itemsToSell as $item) {
            SaleItem::create([
                'sale_id' => $sale->id,
                'waste_type_id' => $item['waste_type_id'],
                'weight_kg' => $item['weight_kg'],
                'price_per_kg' => $item['price_per_kg'],
                'subtotal' => $item['subtotal'],
            ]);
        }

        ActivityLog::record(
            module: 'sales',
            action: 'create',
            description: "Mencatat transaksi penjualan sampah (#{$sale->id}) senilai Rp " . number_format($totalAmount, 0, ',', '.'),
            properties: [
                'sale_id' => $sale->id,
                'buyer_id' => $data['buyer_id'],
                'total_amount' => $totalAmount,
                'items_count' => count($itemsToSell),
            ],
            campusId: $targetCampusId
        );

        $newBalance = BukuBesar::getLatestBalance($targetCampusId);
        $toastMsg = "Penjualan Rp " . number_format($totalAmount, 0, ',', '.') . " tersimpan Saldo: Rp " . number_format($newBalance, 0, ',', '.');

        return [
            'success' => true,
            'sale' => $sale,
            'total_amount' => $totalAmount,
            'new_balance' => $newBalance,
            'message' => $toastMsg,
        ];
    }

    /**
     * Memvalidasi stok aktual dan memperbarui transaksi penjualan beserta item rinciannya.
     */
    public function update(int $saleId, array $data): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_penjualan', 'Petugas Penjualan', 'pengurus_bank_sampah']) && !$user->can('bank_sampah.sale.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengubah data transaksi penjualan.');
        }

        $sale = Sale::with('items.wasteType')->findOrFail($saleId);

        // Otorisasi kampus
        if ($user->campus_id && $user->campus_id !== $sale->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            throw ValidationException::withMessages([
                'editCampusId' => 'Anda tidak memiliki otoritas untuk mengubah data kampus ini.',
            ]);
        }

        Validator::make($data, [
            'campus_id' => ['required', 'exists:campuses,id'],
            'buyer_id' => ['required', 'exists:buyers,id'],
            'sale_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'exists:waste_types,id'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'items.*.price_per_kg' => ['required', 'numeric', 'min:0'],
        ], [
            'campus_id.required' => 'Unit kampus wajib dipilih.',
            'buyer_id.required' => 'Pihak pembeli / lapak wajib dipilih.',
            'sale_date.required' => 'Tanggal penjualan wajib diisi.',
            'items.required' => 'Item rincian sampah wajib disediakan.',
        ])->validate();

        $bindingInfo = $sale->getBindingInfo();
        $targetCampusId = (int) $data['campus_id'];

        if ($bindingInfo['is_bound'] && $targetCampusId !== $sale->campus_id) {
            throw ValidationException::withMessages([
                'editCampusId' => 'Unit kampus tidak dapat diubah karena transaksi ini telah terikat dengan Jurnal Buku Kas.',
            ]);
        }

        // Map bobot item eksisting pada transaksi ini: [waste_type_id => weight_kg]
        $existingItemsWeight = [];
        foreach ($sale->items as $it) {
            $existingItemsWeight[$it->waste_type_id] = (float) $it->weight_kg;
        }

        $itemsToSell = [];
        foreach ($data['items'] as $index => $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            $price = floatval($item['price_per_kg'] ?? 0);
            $wasteTypeId = (int) $item['waste_type_id'];

            if ($weight > 0) {
                $oldWeight = $existingItemsWeight[$wasteTypeId] ?? 0.0;
                $deltaWeight = $weight - $oldWeight;

                // Jika bobot bertambah dari yang sudah terjual sebelumnya, pastikan sisa stok mencukupi
                if ($deltaWeight > 0) {
                    $availableStock = WasteType::getAvailableStock($wasteTypeId, $targetCampusId);
                    if ($deltaWeight > $availableStock) {
                        $wasteName = $item['waste_name'] ?? ('Sampah #' . $wasteTypeId);
                        $maxAllowable = $oldWeight + $availableStock;
                        throw ValidationException::withMessages([
                            "editItems.{$index}.weight_kg" => "Stok '{$wasteName}' tidak mencukupi (Tersedia tambahan: " . number_format($availableStock, 1, ',', '.') . " kg, Diminta: " . number_format($weight, 1, ',', '.') . " kg, Maksimal: " . number_format($maxAllowable, 1, ',', '.') . " kg).",
                        ]);
                    }
                }

                $itemsToSell[] = [
                    'waste_type_id' => $wasteTypeId,
                    'weight_kg' => $weight,
                    'price_per_kg' => $price,
                    'subtotal' => round($weight * $price, 2),
                ];
            }
        }

        if (empty($itemsToSell)) {
            throw ValidationException::withMessages([
                'editItems' => 'Harap isi bobot penjualan minimal untuk salah satu jenis sampah.',
            ]);
        }

        $totalAmount = array_sum(array_column($itemsToSell, 'subtotal'));
        $oldDate = $sale->sale_date?->format('Y-m-d');
        $newDate = $data['sale_date'];

        DB::transaction(function () use ($sale, $data, $targetCampusId, $totalAmount, $itemsToSell, $oldDate, $newDate) {
            $sale->update([
                'campus_id' => $targetCampusId,
                'buyer_id' => (int) $data['buyer_id'],
                'sale_date' => $newDate,
                'total_amount' => $totalAmount,
                'notes' => !empty($data['notes']) ? strip_tags(trim($data['notes'])) : null,
            ]);

            // Hapus dan simpan ulang item penjualan
            $sale->items()->delete();
            foreach ($itemsToSell as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'waste_type_id' => $item['waste_type_id'],
                    'weight_kg' => $item['weight_kg'],
                    'price_per_kg' => $item['price_per_kg'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            // Sinkronisasi Transaksi Keuangan Buku Kas (Kredit)
            $ft = FinancialTransaction::where('source', 'sales')->where('ref_id', $sale->id)->first();
            $buyer = Buyer::find($data['buyer_id']);
            $buyerName = $buyer?->name ?? 'Mitra Pengepul';

            if ($ft) {
                $ft->update([
                    'transaction_date' => $newDate,
                    'amount' => $totalAmount,
                    'description' => 'Penjualan sampah terpilah kepada ' . $buyerName,
                ]);

                // Hitung ulang Buku Besar dari tanggal terdahulu
                $recalcDate = ($oldDate && $oldDate < $newDate) ? $oldDate : $newDate;
                GeneralLedger::recalculateLedgerFromDate($sale->campus_id, $recalcDate);
            } elseif ($totalAmount > 0) {
                Keuangan::recordTransaction(
                    campusId: $sale->campus_id,
                    date: $newDate,
                    jenis: 'K',
                    sumber: 'penjualan',
                    nominal: $totalAmount,
                    keterangan: 'Penjualan sampah terpilah kepada ' . $buyerName,
                    refId: $sale->id,
                    createdBy: auth()->id()
                );
            }
        });

        ActivityLog::record(
            module: 'sales',
            action: 'update',
            description: "Memperbarui transaksi penjualan sampah (#{$sale->id}) senilai Rp " . number_format($totalAmount, 0, ',', '.'),
            properties: [
                'sale_id' => $sale->id,
                'buyer_id' => $data['buyer_id'],
                'total_amount' => $totalAmount,
                'items_count' => count($itemsToSell),
            ],
            campusId: $targetCampusId
        );

        $newBalance = BukuBesar::getLatestBalance($targetCampusId);

        return [
            'success' => true,
            'sale' => $sale,
            'total_amount' => $totalAmount,
            'new_balance' => $newBalance,
            'message' => 'Transaksi penjualan berhasil diperbarui dan buku kas disinkronkan.',
        ];
    }

    /**
     * Memvalidasi dan menghapus transaksi penjualan.
     */
    public function destroy(int $saleId): array
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_penjualan', 'Petugas Penjualan', 'pengurus_bank_sampah']) && !$user->can('bank_sampah.sale.create')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk menghapus transaksi penjualan.');
        }

        $sale = Sale::findOrFail($saleId);

        // Otorisasi: hanya Super Admin atau user dari kampus bersangkutan
        if ($user->campus_id && $user->campus_id !== $sale->campus_id && !$user->hasRole(['super_admin', 'Super Admin'])) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki otoritas untuk menghapus data kampus ini.',
            ];
        }

        // Validasi Keterikatan: Cek apakah transaksi penjualan terikat dengan Buku Kas
        $bindingInfo = $sale->getBindingInfo();
        if ($bindingInfo['is_bound']) {
            return [
                'success' => false,
                'message' => "Tidak dapat menghapus transaksi penjualan (#{$sale->id}): " . $bindingInfo['reason'],
            ];
        }

        ActivityLog::record(
            module: 'sales',
            action: 'delete',
            description: "Menghapus transaksi penjualan sampah (#{$sale->id}) senilai Rp " . number_format((float) $sale->total_amount, 0, ',', '.'),
            properties: [
                'sale_id' => $sale->id,
                'campus_id' => $sale->campus_id,
                'total_amount' => (float) $sale->total_amount,
            ],
            campusId: $sale->campus_id
        );

        \Illuminate\Support\Facades\DB::transaction(function () use ($sale) {
            $sale->items()->delete();
            $sale->delete(); // Observer otomatis hapus jurnal keuangan & sinkronkan buku besar
        });

        return [
            'success' => true,
            'message' => 'Transaksi penjualan berhasil dihapus dan jurnal keuangan disesuaikan.',
        ];
    }
}

