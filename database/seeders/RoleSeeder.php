<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // Buat role
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'penjahit', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'staff_bawah', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'kasir', 'guard_name' =>'api']);
        Role::firstOrCreate(['name' => 'gudang', 'guard_name' => 'api']);
      
        // Buat akun super-admin default
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@ilook.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('superadminilook'),
                'id_penjahit' => null,
                'menus' => []
            ]
        );
        $superAdmin->syncRoles(['super-admin']);
    }
}
