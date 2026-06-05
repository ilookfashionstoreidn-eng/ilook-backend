<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$export = new \App\Exports\SpkCuttingExport();
$data = $export->collection();
$headings = $export->headings();

echo implode("\t", $headings) . "\n";
$i = 0;
foreach ($data as $row) {
    if ($i++ >= 5) break;
    $mapped = $export->map($row);
    foreach ($mapped as &$val) {
        if (is_array($val) || is_object($val)) $val = json_encode($val);
    }
    echo implode("\t", $mapped) . "\n";
}

