<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Ambil jumlah per hari
$counts = DB::select("SELECT DATE(created_at) as tgl, COUNT(*) as ginee_count FROM no_data_ginee_logs GROUP BY DATE(created_at) ORDER BY tgl DESC LIMIT 5");

echo "=== Total No Data Ginee per hari ===\n";
print_r($counts);

echo "\n=== Mengecek 10 resi terbaru apakah ada di tabel order ===\n";
$latestLogs = DB::select("SELECT tracking_number as resi FROM no_data_ginee_logs ORDER BY id DESC LIMIT 10");

foreach ($latestLogs as $log) {
    if (!$log->resi) continue;
    $cleanResi = trim($log->resi);
    echo "Cek: " . $cleanResi . " -> ";
    
    $inDB = \App\Models\Order::whereRaw('TRIM(tracking_number) = ?', [$cleanResi])->exists();
    if ($inDB) {
        echo "ADA DI DB!\n";
    } else {
        echo "TIDAK ADA\n";
    }
}
