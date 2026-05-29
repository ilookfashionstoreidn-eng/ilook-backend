<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    // Set all TukangCutting balances to 0
    DB::table('tukang_cutting')->update([
        'sisa_hutang' => 0,
        'sisa_cashboan' => 0,
        'total_pendapatan' => 0,
        'sisa_pendapatan' => 0
    ]);
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    
    echo "RESET TUKANG CUTTING BALANCES TO 0\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
