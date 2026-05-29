<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$controllers = ['CashboanCuttingController.php', 'HutangCuttingController.php', 'PendapatanCuttingController.php'];

foreach ($controllers as $file) {
    echo $file . ":\n";
    $content = file_get_contents('app/Http/Controllers/' . $file);
    preg_match_all('/App\\\\Models\\\\([a-zA-Z0-9_]+)/', $content, $matches);
    $models = array_unique($matches[1]);
    
    foreach($models as $m) {
        $className = 'App\\Models\\' . $m;
        if (class_exists($className)) {
            try {
                $table = (new $className)->getTable();
                echo ' - ' . $m . " (" . $table . "): " . DB::table($table)->count() . " rows\n";
            } catch (\Exception $e) {
                // Ignore models without tables
            }
        }
    }
}
