<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WeighingSession;

for ($m = 1; $m <= 10; $m++) {
    $sess = WeighingSession::with('items.wasteType')->whereMonth('weigh_date', $m)->get();
    $tot = 0; $res = 0;
    foreach($sess as $s) {
        foreach($s->items as $it) {
            $tot += (float) $it->weight_kg;
            if (str_contains(strtolower($it->wasteType?->name ?? ''), 'residu')) $res += (float) $it->weight_kg;
        }
    }
    echo "Bulan $m: sessions=" . $sess->count() . ", total=" . round($tot, 1) . "kg, residu=" . round($res, 1) . "kg, pct=" . ($tot > 0 ? round(($res/$tot)*100, 1) : 0) . "%\n";
}

