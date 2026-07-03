<?php
require 'vendor/autoload.php';
require_once 'bootstrap/app.php';
$app = app();
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = new \Illuminate\Http\Request();
$request->merge(['spk_cutting_id' => 239]);
$controller = $app->make(\App\Http\Controllers\HasilCuttingController::class);
$response = $controller->getSpkCuttingDetail($request);

$content = json_decode($response->getContent(), true);

file_put_contents('test_api_res.json', json_encode([
    'dist_skus' => $content['distribusi_list'][0]['skus'],
    'item0_skus' => $content['detail'][0]['skus'],
    'item1_skus' => $content['detail'][1]['skus'],
    'item2_skus' => $content['detail'][2]['skus'],
], JSON_PRETTY_PRINT));
