<?php

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Campus;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WasteType;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    /**
     * Run the database seeds for initial realistic sales transactions.
     */
    public function run(): void
    {
        $campuses = Campus::all();
        $buyers = Buyer::all();
        $sellableTypes = WasteType::where('is_sellable', true)->get();
        $user = User::first();

        if ($campuses->isEmpty() || $buyers->isEmpty() || $sellableTypes->isEmpty() || !$user) {
            return;
        }

        // Buat data transaksi contoh untuk 2 hari terakhir di 2 kampus pertama
        foreach ($campuses->take(2) as $campus) {
            $buyer = $buyers->first();

            for ($i = 2; $i >= 1; $i--) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');

                // Siapkan item penjualan
                $itemsToCreate = [];
                $totalAmount = 0.0;

                foreach ($sellableTypes->take(3) as $type) {
                    $weight = rand(15, 30);
                    $price = (float) $type->default_price_per_kg;
                    $subtotal = round($weight * $price, 2);
                    $totalAmount += $subtotal;

                    $itemsToCreate[] = [
                        'waste_type_id' => $type->id,
                        'weight_kg' => $weight,
                        'price_per_kg' => $price,
                        'subtotal' => $subtotal,
                    ];
                }

                // Create Sale (Trigger SaleObserver -> insert Keuangan Kredit & Buku Besar)
                $sale = Sale::create([
                    'campus_id' => $campus->id,
                    'buyer_id' => $buyer->id,
                    'sale_date' => $date,
                    'total_amount' => $totalAmount,
                    'created_by' => $user->id,
                    'notes' => 'Penyaluran berkala sampah anorganik terpilah ke mitra pengepul ' . $buyer->name,
                ]);

                foreach ($itemsToCreate as $itemData) {
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'waste_type_id' => $itemData['waste_type_id'],
                        'weight_kg' => $itemData['weight_kg'],
                        'price_per_kg' => $itemData['price_per_kg'],
                        'subtotal' => $itemData['subtotal'],
                    ]);
                }
            }
        }
    }
}
