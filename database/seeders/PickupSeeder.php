<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Pickup;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PickupSeeder extends Seeder
{
    /**
     * Run the database seeds for Realistic Residual Waste Pickups (SRS M4).
     */
    public function run(): void
    {
        $campuses = Campus::all();
        $vendors = Vendor::where('is_active', true)->get();
        $user = User::first();

        if ($campuses->isEmpty() || $vendors->isEmpty() || !$user) {
            return;
        }

        $drivers = ['Pak Mulyono', 'Pak Sukirno', 'Pak Agus Santoso', 'Mas Rian'];
        $plates = ['AB 8234 QF', 'AB 9102 ZA', 'AB 7711 YK', 'AD 8122 EF'];

        // Seed realistis untuk 3 kampus dalam beberapa hari terakhir
        foreach ($campuses->take(3) as $cIndex => $campus) {
            $vendor = $vendors->get($cIndex % $vendors->count());
            $costPerKg = (float) $vendor->cost_per_kg;

            for ($i = 3; $i >= 1; $i--) {
                $pickupDate = Carbon::now()->subDays($i)->format('Y-m-d');
                $volumeKg = rand(150, 350) + (rand(0, 9) / 10);
                $totalCost = round($volumeKg * $costPerKg, 2);

                Pickup::create([
                    'campus_id' => $campus->id,
                    'vendor_id' => $vendor->id,
                    'pickup_date' => $pickupDate,
                    'volume_kg' => $volumeKg,
                    'cost_per_kg' => $costPerKg,
                    'total_cost' => $totalCost,
                    'driver_name' => $drivers[array_rand($drivers)],
                    'vehicle_plate' => $plates[array_rand($plates)],
                    'created_by' => $user->id,
                    'notes' => 'Pengangkutan residu non-daur ulang ritase reguler ' . $campus->name . ' ke TPA Piyungan/Bawuran',
                ]);
            }
        }
    }
}
