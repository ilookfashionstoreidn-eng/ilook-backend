<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

$cancelledOrders = Order::where('status', 'CANCELLED')
    ->where(function($q) {
        $q->whereNull('is_packed')->orWhere('is_packed', 0);
    })
    ->whereNotNull('tracking_number')
    ->where('tracking_number', '!=', '')
    ->orderBy('id', 'desc')
    ->take(10)
    ->get(['id', 'order_number', 'tracking_number', 'sku', 'total_qty', 'customer_name', 'status']);

echo json_encode($cancelledOrders, JSON_PRETTY_PRINT);
