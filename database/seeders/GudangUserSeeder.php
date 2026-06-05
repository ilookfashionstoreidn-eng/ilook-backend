<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GudangUserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            [
                'name' => 'Gudang 1',
                'email' => 'gudang1@gmail.com',
                'password' => 'gudang1ilook',
            ],
            [
                'name' => 'Gudang 2',
                'email' => 'gudang2@gmail.com',
                'password' => 'gudang2ilook',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'id_penjahit' => null,
                ]
            );

            $user->syncRoles(['gudang']);
        }
    }
}
