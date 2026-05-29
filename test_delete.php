<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Cari SPK yang agak lama
    $spk = App\Models\SpkCutting::find(4);
    if ($spk) {
        $spk->delete();
        echo "Deleted successfully";
    } else {
        echo "No SPK found";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
