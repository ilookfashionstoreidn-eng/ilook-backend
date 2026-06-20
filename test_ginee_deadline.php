<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app()->make(\App\Services\GineeOrderService::class);
$client = new \GuzzleHttp\Client();

// First get one order id that exists in the database
$orderDb = \App\Models\Order::latest('id')->first();
if (!$orderDb) {
    echo "No order found in db\n";
    exit;
}

$url = config('services.ginee.api_url') . '/openapi/trade/v1/orders/get';
$query = [
    'orderId' => $orderDb->order_number, // or the actual ID
];
// Wait, ginee getOrderList is /openapi/trade/v2/orders
$url = config('services.ginee.api_url') . '/openapi/trade/v2/orders';
$body = [
    'size' => 5
];
$headers = $service->buildHeaders($url, 'POST', $body);

try {
    $response = $client->post($url, [
        'headers' => $headers,
        'json' => $body
    ]);

    $data = json_decode($response->getBody()->getContents(), true);
    
    if (!empty($data['data']['content'])) {
        foreach ($data['data']['content'] as $idx => $order) {
            echo "Order: " . ($order['orderId'] ?? '') . "\n";
            echo "Status: " . ($order['orderStatus'] ?? '') . "\n";
            echo "shipByDate: " . ($order['shipByDate'] ?? 'null') . "\n";
            echo "latestShipTime: " . ($order['latestShipTime'] ?? 'null') . "\n";
            
            if (isset($order['fulfillmentInfo'])) {
                echo "fulfillmentInfo.latestShipTime: " . ($order['fulfillmentInfo']['latestShipTime'] ?? 'null') . "\n";
            }
            if (isset($order['fulfillmentInfoList'][0])) {
                echo "fulfillmentInfoList[0].latestShipTime: " . ($order['fulfillmentInfoList'][0]['latestShipTime'] ?? 'null') . "\n";
            }
            if (isset($order['logisticsInfos'][0])) {
                echo "logisticsInfos[0].latestShipTime: " . ($order['logisticsInfos'][0]['latestShipTime'] ?? 'null') . "\n";
            }
            
            echo "----------------------------------------\n";
        }
    } else {
        echo "No orders found.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
