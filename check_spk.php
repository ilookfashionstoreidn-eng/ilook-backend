<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $request = Request::create('/kode-seri-belum-dikerjakan', 'GET', [
        'page' => 1,
        'per_page' => 50,
        'type' => 'cutting',
        'potong' => 'belum',
    ]);
    
    $controller = new \App\Http\Controllers\KodeSeriBelumDikerjakanOptimizedController();
    $response = $controller->index($request);
    
    echo "Response status: " . $response->status() . "\n";
    $content = json_decode($response->getContent());
    if (isset($content->error)) {
        echo "Response error: " . $content->error . "\n";
    } else {
        echo "Data count: " . count($content->data) . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
