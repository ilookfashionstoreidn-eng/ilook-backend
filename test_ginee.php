<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app()->make('App\Services\GineeApiService');
try {
    $res = $service->getProducts(0, 100);
    $products = $res['data']['content'] ?? $res['data']['list'] ?? $res['data'] ?? [];

    $found = false;
    foreach ($products as $p) {
        if (!empty($p['variations'])) {
            echo "Found variations for SKU: " . ($p['sku'] ?? 'N/A') . "\n";
            echo json_encode($p['variations'], JSON_PRETTY_PRINT) . "\n";
            $found = true;
            break;
        }
        if (!empty($p['variantOptions'])) {
            foreach ($p['variantOptions'] as $opt) {
                if (!empty($opt['image']) || !empty($opt['images'])) {
                    echo "Found variantOptions images for SKU: " . ($p['sku'] ?? 'N/A') . "\n";
                    echo json_encode($p['variantOptions'], JSON_PRETTY_PRINT) . "\n";
                    $found = true;
                    break 2;
                }
            }
        }
        foreach ($p['variationBriefs'] ?? [] as $vb) {
            if (!empty($vb['image']) || !empty($vb['imageUrl']) || !empty($vb['images'])) {
                echo "Found variationBriefs image for SKU: " . ($p['sku'] ?? 'N/A') . "\n";
                echo json_encode($vb, JSON_PRETTY_PRINT) . "\n";
                $found = true;
                break 2;
            }
        }
    }
    if (!$found) {
        echo "No variation images found in the first 100 products.";
    }
} catch (\Exception $e) {
    echo $e->getMessage();
}
