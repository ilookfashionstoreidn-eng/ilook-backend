<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orders = \App\Models\Order::latest('id')->take(5)->get(['order_number', 'shipping_deadline']);
echo "Latest 5 orders in DB:\n";
foreach ($orders as $o) {
    echo $o->order_number . " => " . ($o->shipping_deadline ?? 'NULL') . "\n";
}

echo "\nCalling internal API method simulation:\n";
$report = \Illuminate\Support\Facades\DB::table('order')
            ->whereNotNull('label_print_time')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(label_print_time)'), [now()->subDays(7)->toDateString(), now()->toDateString()])
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(label_print_time) as print_date'),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_printed'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN is_packed = 1 THEN 1 ELSE 0 END) as total_packed'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN is_packed IS NULL OR is_packed = 0 THEN 1 ELSE 0 END) as total_unpacked'),
                \Illuminate\Support\Facades\DB::raw('MIN(CASE WHEN (is_packed IS NULL OR is_packed = 0) THEN shipping_deadline ELSE NULL END) as earliest_shipping_deadline')
            )
            ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(label_print_time)'))
            ->orderByDesc('print_date')
            ->get();

echo json_encode($report, JSON_PRETTY_PRINT);
