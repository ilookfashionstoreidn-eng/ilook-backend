<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    // Check if root or root@root.com already exists
    $user = User::where('email', 'root')->orWhere('email', 'root@root.com')->first();
    
    if (!$user) {
        $user = new User();
    }
    
    $user->name = 'Root Admin';
    $user->email = 'root@root.com';
    $user->password = Hash::make('toor');
    $user->save();

    // Assign role again
    $user->assignRole('super-admin');
    
    echo "User created/updated successfully! Email: root@root.com, Pass: toor\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
