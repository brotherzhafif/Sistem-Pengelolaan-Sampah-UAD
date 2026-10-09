<?php

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Campus;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WasteType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    /**
     * Run the database seeds for realistic sales transactions (SRS M3 & Prototype UI alignment).
     */
    public function run(): void
    {
        $campuses = Campus::orderBy('id')->get();
        $buyers = Buyer::all();
        $sellableTypes = WasteType::where('is_sellable', true)->get();
        $user = User::first();

        if ($campuses->isEmpty() || $buyers->isEmpty() || $sellableTypes->isEmpty() || !$user) {
            return;
        }

        // Delete existing sales through Eloquent so SaleObserver cleans ledger entries properly
        SaleItem::query()->delete();
        foreach (Sale::all() as $oldSale) {
            $oldSale->delete();
        }

        $today = Carbon::today();

        // Seed across campuses over the past 30 days
        foreach ($campuses as $campus) {
            $isMainCampus = ($campus->id == 4);
            $saleIntervals = $isMainCampus ? [28, 25, 21, 18, 14, 11, 7, 4, 1] : [26, 19, 12, 5];

            foreach ($saleIntervals as $idx => $dayOffset) {
                $date = $today->copy()->subDays($dayOffset)->format('Y-m-d');
                $buyer = $buyers->get(($campus->id + $idx) % $buyers->count());

                // Select 2 to 5 sellable waste types
                $itemsToCreate = [];
                $totalAmount = 0.0;
                $typesToSell = $sellableTypes->shuffle()->take(rand(3, min(5, $sellableTypes->count())));

                foreach ($typesToSell as $type) {
                    $weight = $isMainCampus ? rand(45, 120) : rand(25, 60);
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

                $sale = Sale::create([
                    'campus_id' => $campus->id,
                    'buyer_id' => $buyer->id,
                    'sale_date' => $date,
                    'total_amount' => $totalAmount,
                    'created_by' => $user->id,
                    'notes' => 'Penyaluran berkala sampah anorganik terpilah ke mitra pengepul ' . $buyer->name . ' (' . $campus->name . ')',
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
