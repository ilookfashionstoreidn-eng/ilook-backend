<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');

    $tables = [
        'stok_bahan_keluar',
        'stok_bahan',
        'spk_cutting_bahan',
        'spk_bahan_warna',
        'spk_bahan',
        'pembelian_bahan',
        'return_bahan',
        'bahan'
    ];

    foreach ($tables as $table) {
        if (Schema::hasTable($table)) {
            DB::table($table)->truncate();
            echo "Truncated table: $table\n";
        } else {
            echo "Table not found (skipped): $table\n";
        }
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "\nBERHASIL KOSONGKAN DATA BAHAN\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
