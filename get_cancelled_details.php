<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

$orders = Order::whereIn('tracking_number', ['JY1151131919', 'JY1248486770', 'JY1177897558'])
    ->get(['id', 'order_number', 'tracking_number', 'sku', 'total_qty', 'customer_name', 'status']);

echo json_encode($orders, JSON_PRETTY_PRINT);
