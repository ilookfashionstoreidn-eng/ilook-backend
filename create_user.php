<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

try {
    $user = User::updateOrCreate(
        ['email' => 'root'], // some systems use email field for username
        [
            'name' => 'Root Admin',
            'password' => Hash::make('toor')
        ]
    );

    // Give it super-admin or admin role if exists
    $roles = Role::pluck('name')->toArray();
    echo "Available roles: " . implode(', ', $roles) . "\n";
    
    if (in_array('Super Admin', $roles)) {
        $user->assignRole('Super Admin');
        echo "Assigned Super Admin role.\n";
    } elseif (in_array('admin', $roles)) {
        $user->assignRole('admin');
        echo "Assigned admin role.\n";
    }
    
    echo "User created successfully! Email: root, Pass: toor\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
