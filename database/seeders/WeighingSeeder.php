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
     * Run the database seeds for initial realistic weighing data.
     */
    public function run(): void
    {
        $campuses = Campus::all();
        $wasteTypes = WasteType::all();
        $user = User::first();

        if ($campuses->isEmpty() || $wasteTypes->isEmpty() || !$user) {
            return;
        }

        // Seed sampling data for the last 5 days
        foreach ($campuses->take(3) as $campus) {
            $sources = WasteSource::where('campus_id', $campus->id)->get();
            $sourceId = $sources->first()?->id;

            for ($i = 4; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');

                $session = WeighingSession::create([
                    'campus_id' => $campus->id,
                    'waste_source_id' => $sourceId,
                    'weigh_date' => $date,
                    'created_by' => $user->id,
                    'notes' => 'Penimbangan rutin harian shift pagi ' . $campus->name,
                ]);

                // Create entries for each waste type
                foreach ($wasteTypes as $type) {
                    $weight = match ($type->category) {
                        'Organik' => rand(25, 60) + (rand(1, 9) / 10),
                        'Anorganik' => rand(10, 35) + (rand(1, 9) / 10),
                        default => rand(30, 80) + (rand(1, 9) / 10), // Residu
                    };

                    $volume = round($weight * 0.0035, 3);

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

