<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateGudangUsers extends Migration
{
    public function up()
    {
        $now = now();

        if (! DB::table('roles')->where('name', 'gudang')->where('guard_name', 'api')->exists()) {
            DB::table('roles')->insert([
                'name' => 'gudang',
                'guard_name' => 'api',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleId = DB::table('roles')
            ->where('name', 'gudang')
            ->where('guard_name', 'api')
            ->value('id');

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

        foreach ($users as $user) {
            $existingUser = DB::table('users')->where('email', $user['email'])->first();

            if ($existingUser) {
                DB::table('users')->where('id', $existingUser->id)->update([
                    'name' => $user['name'],
                    'password' => Hash::make($user['password']),
                    'id_penjahit' => null,
                    'updated_at' => $now,
                ]);

                $userId = $existingUser->id;
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => Hash::make($user['password']),
                    'id_penjahit' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
            ]);
        }
    }

    public function down()
    {
        $emails = ['gudang1@gmail.com', 'gudang2@gmail.com'];
        $userIds = DB::table('users')->whereIn('email', $emails)->pluck('id');

        DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('model_id', $userIds)
            ->delete();

        DB::table('users')->whereIn('email', $emails)->delete();

        $roleId = DB::table('roles')
            ->where('name', 'gudang')
            ->where('guard_name', 'api')
            ->value('id');

        if ($roleId && ! DB::table('model_has_roles')->where('role_id', $roleId)->exists()) {
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
}
