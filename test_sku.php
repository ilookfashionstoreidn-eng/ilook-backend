<?php
require 'vendor/autoload.php';
require_once 'bootstrap/app.php';
$app = app();
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$spk = \App\Models\SpkCutting::find(239);
$dist = $spk->spkCuttingDistribusi()->with('detail.productListSku')->first();

$data = [
    'dist_id' => $dist->id,
    'skus' => $dist->detail->map(function($d) {
        return $d->productListSku->sku_name;
    })->toArray()
];

echo json_encode($data, JSON_PRETTY_PRINT);
