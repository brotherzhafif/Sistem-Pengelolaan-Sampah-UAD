<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Sales\Index as SalesComponent;
use App\Models\BukuBesar;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\WasteType;
use Illuminate\Http\Request;
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

        $newBalance = BukuBesar::getLatestBalance($targetCampusId);
        $toastMsg = "Penjualan Rp " . number_format($totalAmount, 0, ',', '.') . " tersimpan → Saldo: Rp " . number_format($newBalance, 0, ',', '.');

        return [
            'success' => true,
            'sale' => $sale,
            'total_amount' => $totalAmount,
            'new_balance' => $newBalance,
            'message' => $toastMsg,
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

        $sale->delete(); // Observer otomatis hapus jurnal keuangan & sinkronkan buku besar

        return [
            'success' => true,
            'message' => 'Transaksi penjualan berhasil dihapus dan jurnal keuangan disesuaikan.',
        ];
    }
}

