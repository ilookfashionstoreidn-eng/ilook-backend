<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    Illuminate\Http\Request::capture()
);

use App\Models\Sku;
$skus = Sku::where('sku', 'like', '%XL%')->get();
foreach ($skus as $sku) {
    echo "ID: {$sku->id} - SKU: {$sku->sku} - Active: {$sku->is_active}\n";
}
