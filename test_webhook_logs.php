<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new App\Http\Controllers\WebhookLogController();
$request = Illuminate\Http\Request::create('/api/webhook-logs', 'GET', ['date' => date('Y-m-d')]);
$res = $controller->index($request);

echo "HTTP Status: " . $res->getStatusCode() . "\n";
echo "Response Body: " . json_encode($res->getData(), JSON_PRETTY_PRINT) . "\n";
