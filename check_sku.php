<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dist = \App\Models\SpkCuttingDistribusi::with('detail.productListSku')->where('kode_seri', '3269')->first();
if ($dist) {
    foreach ($dist->detail as $detail) {
        echo 'SKU ID: ' . $detail->product_list_sku_id . ' - Size: ' . ($detail->productListSku->product_size ?? 'NULL') . PHP_EOL;
    }
    if ($dist->detail->isEmpty()) {
        echo "NO DETAILS FOR 3269\n";
    }
} else {
    echo "Not found 3269\n";
}
