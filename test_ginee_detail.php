<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Helpers\GineeSignature;

$accessKey = env('GINEE_ACCESS_KEY');
$secretKey = env('GINEE_SECRET_KEY');
$country = env('GINEE_COUNTRY', 'ID');
$host = env('GINEE_API_HOST', 'https://api.ginee.com');

// 1. Get a product ID from List
$endpoint = '/openapi/product/master/v1/list';
$signature = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpoint, $secretKey));
$headers = [
    'Content-Type' => 'application/json',
    'X-Advai-Country' => $country,
    'Authorization' => $accessKey . ':' . $signature
];
$payload = ['page' => 0, 'size' => 1];
$response = Http::withHeaders($headers)->post($host . $endpoint, $payload);
$list = $response->json();
$products = $list['data']['content'] ?? $list['data']['list'] ?? $list['data'] ?? [];
$productId = $products[0]['id'] ?? null;

if (!$productId) {
    echo "No product found in list.\n";
    echo json_encode($list, JSON_PRETTY_PRINT);
    exit;
}

echo "Testing Detail for Product ID: $productId\n";

// 2. Try Detail API
$detailEndpoints = [
    '/openapi/product/master/v1/detail',
    '/openapi/product/master/v1/get',
    '/openapi/product/master/v1/info',
    '/openapi/product/v1/detail'
];

foreach ($detailEndpoints as $ep) {
    echo "\nTrying endpoint: $ep\n";
    $signature = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $ep, $secretKey));
    $headers['Authorization'] = $accessKey . ':' . $signature;
    
    // Some APIs take ID in URL, some in payload
    $payload = ['id' => $productId, 'productId' => $productId];
    $res = Http::withHeaders($headers)->post($host . $ep, $payload);
    
    if ($res->successful() && isset($res['data'])) {
        echo "SUCCESS!\n";
        echo json_encode($res->json(), JSON_PRETTY_PRINT);
        exit;
    } else {
        echo "Failed: " . $res->body() . "\n";
    }
    
    // Try GET
    $signatureGet = str_replace(["\r", "\n"], '', GineeSignature::generate('GET', $ep . '?id=' . $productId, $secretKey));
    $headers['Authorization'] = $accessKey . ':' . $signatureGet;
    $resGet = Http::withHeaders($headers)->get($host . $ep . '?id=' . $productId);
    if ($resGet->successful() && isset($resGet['data'])) {
        echo "SUCCESS (GET)!\n";
        echo json_encode($resGet->json(), JSON_PRETTY_PRINT);
        exit;
    } else {
        echo "Failed GET: " . $resGet->body() . "\n";
    }
}

echo "\nCould not find a working detail endpoint.\n";
