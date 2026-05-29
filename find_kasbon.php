<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select('SHOW TABLES');
foreach ($tables as $t) {
    $table = array_values((array)$t)[0];
    if (strpos($table, 'kasbon') !== false || 
        strpos($table, 'cashboan') !== false) {
        
        $count = DB::table($table)->count();
        echo str_pad($table, 30) . " : " . $count . " rows\n";
    }
}
