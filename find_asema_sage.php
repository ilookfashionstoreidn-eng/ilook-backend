<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

$orders = Order::where('sku', 'LIKE', '%SET ASEMA - SAGE%')
    ->orderBy('id', 'desc')
    ->take(10)
    ->get(['id', 'order_number', 'tracking_number', 'sku', 'total_qty', 'customer_name', 'status', 'is_packed']);

echo json_encode($orders, JSON_PRETTY_PRINT);
