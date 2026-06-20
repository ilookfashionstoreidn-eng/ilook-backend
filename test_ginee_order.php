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

$endpointList = '/openapi/order/v2/list-order';
$signatureList = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointList, $secretKey));

$headers = [
    'Content-Type' => 'application/json',
    'X-Advai-Country' => $country,
    'Authorization' => $accessKey . ':' . $signatureList
];

$body = [
    'createSince' => now()->subDays(90)->utc()->format('Y-m-d\TH:i:s\Z'),
    'size' => 1
];

$response = Http::withHeaders($headers)->post($host . $endpointList, $body);
$json = $response->json();
$orders = $json['data']['content'] ?? [];

if (!empty($orders)) {
    $orderId = $orders[0]['orderId'];
    
    $endpointBatch = '/openapi/order/v1/batch-get';
    $signatureBatch = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointBatch, $secretKey));
    
    $headersBatch = [
        'Content-Type' => 'application/json',
        'X-Advai-Country' => $country,
        'Authorization' => $accessKey . ':' . $signatureBatch
    ];
    
    $bodyBatch = [
        'orderIds' => [$orderId],
        'historicalData' => false
    ];
    
    $batchResponse = Http::withHeaders($headersBatch)->post($host . $endpointBatch, $bodyBatch);
    $batchJson = $batchResponse->json();
    
    echo json_encode($batchJson['data'][0] ?? null, JSON_PRETTY_PRINT);
} else {
    echo "No orders found.";
}
