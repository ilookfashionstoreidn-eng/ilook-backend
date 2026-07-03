<?php
require 'vendor/autoload.php';
require_once 'bootstrap/app.php';
$app = app();
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$indexes = Illuminate\Support\Facades\DB::select('SHOW INDEX FROM spk_cutting_distribusi');
file_put_contents('test_indexes.json', json_encode(array_map(function($i) {
    return ['Key_name' => $i->Key_name, 'Column_name' => $i->Column_name];
}, $indexes), JSON_PRETTY_PRINT));
