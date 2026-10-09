<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\User;
use App\Models\WasteSource;
use App\Models\WasteType;
use App\Models\WeighingItem;
use App\Models\WeighingSession;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WeighingSeeder extends Seeder
{
    /**
     * Run the database seeds for realistic weighing data (SRS M2 & Prototype UI alignment).
     * Populates comprehensive daily records for the last 30 days across all 6 campuses.
     */
    public function run(): void
    {
        $campuses = Campus::orderBy('id')->get();
        $wasteTypes = WasteType::orderBy('id')->get();
        $user = User::first();

        if ($campuses->isEmpty() || $wasteTypes->isEmpty() || !$user) {
            return;
        }

        // Clean previous weighing records for idempotent, fresh realistic seeding
        WeighingItem::query()->delete();
        WeighingSession::query()->delete();

        // Seed 30 days of realistic history
        $today = Carbon::today();

        foreach ($campuses as $campus) {
            $sources = WasteSource::where('campus_id', $campus->id)->get();
            if ($sources->isEmpty()) {
                continue;
            }

            // Campus 4 has high volume (TPS3R Utama UAD), other campuses proportional
            $isMainCampus = ($campus->id == 4);

            for ($dayOffset = 29; $dayOffset >= 0; $dayOffset--) {
                $date = $today->copy()->subDays($dayOffset)->format('Y-m-d');

                // Frequency: Main campus has 1-2 sessions daily; others have 1 session on most days
                $sessionsToday = $isMainCampus ? (($dayOffset % 3 === 0) ? 2 : 1) : (($dayOffset % 4 === 3) ? 0 : 1);

                for ($s = 0; $s < $sessionsToday; $s++) {
                    $source = $sources->get(($dayOffset + $s) % $sources->count());

                    $session = WeighingSession::create([
                        'campus_id' => $campus->id,
                        'waste_source_id' => $source?->id,
                        'weigh_date' => $date,
                        'created_by' => $user->id,
                        'notes' => 'Penimbangan rutin ' . ($s === 0 ? 'pagi' : 'sore') . ' di ' . ($source?->name ?? 'TPS') . ' ' . $campus->name,
                    ]);

                    // Generate realistic item weights across all 9 waste types
                    foreach ($wasteTypes as $type) {
                        $multiplier = $isMainCampus ? 1.0 : (0.5 + (($campus->id % 3) * 0.2));

                        $weight = match ($type->name) {
                            'Sampah Taman' => round((rand(35, 75) + (rand(1, 9) / 10)) * $multiplier, 1),
                            'Organik Sisa Makanan' => round((rand(25, 55) + (rand(1, 9) / 10)) * $multiplier, 1),
                            'Kardus' => round((rand(15, 35) + (rand(1, 9) / 10)) * $multiplier, 1),
                            'Karton / Duplex' => round((rand(12, 28) + (rand(1, 9) / 10)) * $multiplier, 1),
                            'Kertas HVS / Putih' => round((rand(10, 22) + (rand(1, 9) / 10)) * $multiplier, 1),
                            'Plastik Keras (HDPE/PP/PET)' => round((rand(15, 38) + (rand(1, 9) / 10)) * $multiplier, 1),
                            'Plastik Multilayer / Kresek' => round((rand(10, 25) + (rand(1, 9) / 10)) * $multiplier, 1),
                            'Logam dan Kaca' => round((rand(6, 18) + (rand(1, 9) / 10)) * $multiplier, 1),
                            'Residu Campur' => round((rand(20, 50) + (rand(1, 9) / 10)) * $multiplier, 1),
                            default => round((rand(10, 30) + (rand(1, 9) / 10)) * $multiplier, 1),
                        };

                        $volume = round($weight * 0.0038, 3);

                        WeighingItem::create([
                            'weighing_session_id' => $session->id,
                            'waste_type_id' => $type->id,
                            'weight_kg' => $weight,
                            'volume_m3' => $volume,
                        ]);
                    }
                }
            }
        }
    }
}
