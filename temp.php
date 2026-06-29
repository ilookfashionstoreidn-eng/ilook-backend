<?php
$user = \App\Models\User::where('email', 'superadmin@ilook.com')->first();
if($user) {
    $user->password = bcrypt('password');
    $user->save();
    echo "Password changed successfully for {$user->email}\n";
} else {
    echo "User not found\n";
}
