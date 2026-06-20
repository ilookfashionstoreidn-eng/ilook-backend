<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Helpers\GineeSignature;
use Carbon\Carbon;

$accessKey = env('GINEE_ACCESS_KEY');
$secretKey = env('GINEE_SECRET_KEY');
$country = env('GINEE_COUNTRY', 'ID');
$host = env('GINEE_API_HOST', 'https://api.ginee.com');
$endpointBatch = '/openapi/order/v1/batch-get';

$orders = Order::whereNotNull('label_print_time')
    ->orderByDesc('label_print_time')
    ->take(100)
    ->get(['id', 'order_number']);

$orderNumbers = $orders->pluck('order_number')->toArray();
$chunks = array_chunk($orderNumbers, 20);

$client = new \GuzzleHttp\Client();
$updated = 0;

foreach ($chunks as $chunk) {
    $signatureBatch = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpointBatch, $secretKey));
    $headersBatch = [
        'Content-Type' => 'application/json',
        'X-Advai-Country' => $country,
        'Authorization' => $accessKey . ':' . $signatureBatch
    ];
    
    $bodyBatch = [
        'orderIds' => $chunk,
        'historicalData' => false
    ];

    try {
        $response = $client->post($host . $endpointBatch, [
            'headers' => $headersBatch,
            'json' => $bodyBatch
        ]);
        
        $data = json_decode($response->getBody()->getContents(), true);
        
        if (!empty($data['data'])) {
            foreach ($data['data'] as $apiOrder) {
                $shippingDeadlineStr = $apiOrder['promisedToShipBefore']
                    ?? ($apiOrder['shipByDate'] ?? null) 
                    ?? ($apiOrder['latestShipTime'] ?? null) 
                    ?? ($apiOrder['fulfillmentInfo']['latestShipTime'] ?? null)
                    ?? ($apiOrder['fulfillmentInfoList'][0]['latestShipTime'] ?? null)
                    ?? ($apiOrder['logisticsInfos'][0]['latestShipTime'] ?? null)
                    ?? null;
                
                if ($shippingDeadlineStr) {
                    $shippingDeadline = Carbon::parse($shippingDeadlineStr)->format('Y-m-d H:i:s');
                    $orderNo = $apiOrder['externalOrderSn'] ?? null;
                    if ($orderNo) {
                        Order::where('order_number', $orderNo)->update(['shipping_deadline' => $shippingDeadline]);
                        $updated++;
                    }
                }
            }
        }
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Updated $updated orders with shipping_deadline.\n";
