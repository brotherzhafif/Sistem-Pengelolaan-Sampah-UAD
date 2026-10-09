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
     * Run the database seeds for Realistic Residual Waste Pickups (SRS M4 & Prototype UI alignment).
     */
    public function run(): void
    {
        $campuses = Campus::orderBy('id')->get();
        $vendors = Vendor::where('is_active', true)->get();
        $user = User::first();

        if ($campuses->isEmpty() || $vendors->isEmpty() || !$user) {
            return;
        }

        // Clean previous pickups through Eloquent so PickupObserver cleans ledger entries properly
        foreach (Pickup::all() as $oldPickup) {
            $oldPickup->delete();
        }

        $drivers = ['Pak Mulyono', 'Pak Sukirno', 'Pak Agus Santoso', 'Mas Rian', 'Pak Budi Hartono'];
        $plates = ['AB 8234 QF', 'AB 9102 ZA', 'AB 7711 YK', 'AD 8122 EF', 'AB 6643 PN'];

        $today = Carbon::today();

        foreach ($campuses as $campus) {
            $isMainCampus = ($campus->id == 4);
            $pickupIntervals = $isMainCampus ? [28, 24, 20, 16, 12, 8, 4, 1] : [26, 18, 10, 2];

            foreach ($pickupIntervals as $idx => $dayOffset) {
                $pickupDate = $today->copy()->subDays($dayOffset)->format('Y-m-d');
                $vendor = $vendors->get(($campus->id + $idx) % $vendors->count());
                $costPerKg = (float) $vendor->cost_per_kg;

                $volumeKg = $isMainCampus ? (rand(280, 420) + (rand(0, 9) / 10)) : (rand(140, 260) + (rand(0, 9) / 10));
                $totalCost = round($volumeKg * $costPerKg, 2);

                Pickup::create([
                    'campus_id' => $campus->id,
                    'vendor_id' => $vendor->id,
                    'pickup_date' => $pickupDate,
                    'volume_kg' => $volumeKg,
                    'cost_per_kg' => $costPerKg,
                    'total_cost' => $totalCost,
                    'driver_name' => $drivers[$idx % count($drivers)],
                    'vehicle_plate' => $plates[$idx % count($plates)],
                    'created_by' => $user->id,
                    'notes' => 'Pengangkutan residu non-daur ulang ritase reguler ' . $campus->name . ' ke TPA Piyungan/Bawuran',
                ]);
            }
        }
    }
}
