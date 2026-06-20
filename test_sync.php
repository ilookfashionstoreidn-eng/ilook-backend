<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $service = app()->make(\App\Services\GineeOrderService::class);
    echo "Running syncRecentOrders...\n";
    $result = $service->syncRecentOrders();
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    $orders = \App\Models\Order::whereNotNull('shipping_deadline')
                ->latest('updated_at')
                ->take(5)
                ->get(['order_number', 'shipping_deadline', 'label_print_status']);
    
    echo "Recent orders with shipping_deadline:\n";
    foreach ($orders as $o) {
        echo $o->order_number . " => " . $o->shipping_deadline . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
