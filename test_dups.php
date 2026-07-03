<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$spk = App\Models\SpkCutting::where('id_spk_cutting', '3286')->first();
if ($spk) {
    $res = App\Models\HasilCutting::where('spk_cutting_id', $spk->id)->get();
    echo "Count for SPK 3286: " . $res->count() . "\n";
    foreach ($res as $row) {
        echo "ID: " . $row->id . "\n";
    }
}
