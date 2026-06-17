<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app()->make('App\Services\GineeApiService');
try {
    $res = $service->getProducts(0, 10);
    // Recursively find all URLs in the response
    $urls = [];
    array_walk_recursive($res['data'], function($item, $key) use (&$urls) {
        if (is_string($item) && str_contains($item, 'http')) {
            $urls[] = ['key' => $key, 'url' => $item];
        }
    });
    print_r($urls);
} catch (\Exception $e) {
    echo $e->getMessage();
}
