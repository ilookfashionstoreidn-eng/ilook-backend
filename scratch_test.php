<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$res = DB::select("
    SELECT 
        ndg.tracking_number, 
        ndg.created_at, 
        ndg.scanner_name,
        o.id as order_id,
        o.status,
        o.tracking_number as o_track 
    FROM no_data_ginee_logs ndg 
    LEFT JOIN `order` o ON TRIM(o.tracking_number) = TRIM(ndg.tracking_number) 
    WHERE ndg.created_at >= '2026-04-14 00:00:00' 
    LIMIT 20
");

print_r($res);
