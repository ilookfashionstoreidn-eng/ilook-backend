<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Helpers\GineeSignature;

$accessKey = env('GINEE_ACCESS_KEY');
$secretKey = env('GINEE_SECRET_KEY');
$country = env('GINEE_COUNTRY', 'ID');
$host = env('GINEE_API_HOST', 'https://api.ginee.com');
$endpointList = '/openapi/order/v2/list-order';

$payload = [
    'size' => 5,
    'page' => 0,
    'createSince' => now()->subDays(7)->utc()->format('Y-m-d\TH:i:s\Z'),
    'createTo' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
];

$signatureList = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointList, $secretKey));
$headers = [
    'Content-Type' => 'application/json',
    'X-Advai-Country' => $country,
    'Authorization' => $accessKey . ':' . $signatureList
];

$client = new \GuzzleHttp\Client();
try {
    $response = $client->post($host . $endpointList, [
        'headers' => $headers,
        'json' => $payload
    ]);
    $data = json_decode($response->getBody()->getContents(), true);
    
    if (!empty($data['data']['content'])) {
        $order = $data['data']['content'][0];
        echo "RAW ORDER JSON:\n";
        echo json_encode($order, JSON_PRETTY_PRINT);
    } else {
        echo "No orders returned from API.\n";
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
