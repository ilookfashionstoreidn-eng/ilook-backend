<?php
require 'vendor/autoload.php';
require_once 'bootstrap/app.php';
$app = app();
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = new \Illuminate\Http\Request();
$request->merge(['spk_cutting_id' => 239]);
$controller = $app->make(\App\Http\Controllers\HasilCuttingController::class);
$response = $controller->getSpkCuttingDetail($request);

file_put_contents('full_api_res.json', $response->getContent());
