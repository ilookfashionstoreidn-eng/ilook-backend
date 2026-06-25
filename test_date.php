<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dt1 = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)46063);
$dt2 = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)46078);
$dt3 = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)46074);

echo "Tgl SPK (46063): " . \Carbon\Carbon::instance($dt1)->toDateString() . "\n";
echo "Deadline (46078): " . \Carbon\Carbon::instance($dt2)->toDateString() . "\n";
echo "Tanggal Ambil (46074): " . \Carbon\Carbon::instance($dt3)->toDateString() . "\n";
