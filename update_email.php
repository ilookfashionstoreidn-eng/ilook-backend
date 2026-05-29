<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

try {
    $user = User::where('email', 'root')->first();
    if ($user) {
        $user->email = 'root@root.com';
        $user->save();
        echo "Email updated to root@root.com\n";
    } else {
        echo "User root not found.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
