<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('D:/WINDU/NEXT/template_spk_cmt.xlsx');
$rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
print_r(array_slice($rows, 0, 4));
