<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dist = \App\Models\SpkCuttingDistribusi::with('detail.productListSku', 'detail.produkSku')->find(3396);
if(!$dist) {
    $dist = \App\Models\SpkCuttingDistribusi::with('detail.productListSku', 'detail.produkSku')->where('kode_seri', '3396')->first();
}

if($dist) {
    echo 'Found! ID: ' . $dist->id . ' Kode Seri: ' . $dist->kode_seri . PHP_EOL;
    foreach($dist->detail as $d) {
        $skuId = $d->productListSku ? $d->productListSku->id : ($d->produkSku ? $d->produkSku->id : 'none');
        $skuName = $d->productListSku ? $d->productListSku->sku_name : ($d->produkSku ? $d->produkSku->sku : 'none');
        echo 'Detail ID: ' . $d->id . ' | SKU ID: ' . $skuId . ' | SKU Name: ' . $skuName . ' | Qty: ' . $d->jumlah_produk . PHP_EOL;
    }
} else {
    echo 'not found';
}
