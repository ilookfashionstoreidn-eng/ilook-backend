<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    
    // Check and truncate any leftover logs/history tables
    $tablesToTruncate = [
        'hasil_cutting_bahan',
        'spk_cutting_distribusi_detail',
        'hasil_markeran',
        'spk_distribusi_histories', // Maybe?
        'log_status_spk_cmt',
        'spk_jasa_pengambilan_log',
        'spk_jasa_status_log',
        'log_distribusi_spk',
        'log_hasil_cutting'
    ];

    foreach ($tablesToTruncate as $table) {
        if (Schema::hasTable($table)) {
            DB::table($table)->truncate();
            echo "Truncated: " . $table . "\n";
        }
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    
    echo "DONE CLEANING";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
