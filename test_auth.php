<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = \App\Models\User::where('email', 'superadmin@ilook.com')->first();
$token = auth('api')->login($user);

$request = Illuminate\Http\Request::create('/api/product-list/spk-options', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
$content = $response->getContent();
echo "Content length: " . strlen($content) . "\n";
if ($response->getStatusCode() != 200) {
    echo "Content: " . substr($content, 0, 500) . "\n";
}
