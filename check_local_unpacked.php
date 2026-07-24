<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

$orders = Order::where('tracking_number', '!=', '')
    ->whereNotNull('tracking_number')
    ->where(function($q) {
        $q->whereNull('is_packed')->orWhere('is_packed', 0);
    })
    ->where('status', 'READY_TO_SHIP')
    ->orderBy('id', 'desc')
    ->take(10)
    ->get(['id', 'order_number', 'tracking_number', 'sku', 'total_qty', 'customer_name', 'status']);

echo json_encode($orders, JSON_PRETTY_PRINT);
