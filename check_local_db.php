<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

echo "Total order di DB lokal: " . Order::count() . "\n";
$latest = Order::where('tracking_number', '!=', '')->whereNotNull('tracking_number')->orderBy('id', 'desc')->take(5)->get(['id', 'order_number', 'tracking_number', 'status']);
echo "5 Tracking Number di DB LOKAL:\n";
foreach ($latest as $o) {
    echo "- ID: {$o->id} | Resi: {$o->tracking_number} | Status: {$o->status}\n";
}
