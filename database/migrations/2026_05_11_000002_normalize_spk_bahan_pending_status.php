<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('spk_bahan')
            ->where('status', 'pending')
            ->update(['status' => 'proses']);
    }

    public function down(): void
    {
        //
    }
};
