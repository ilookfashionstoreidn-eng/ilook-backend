<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE pembelian_bahan MODIFY gramasi VARCHAR(100) NOT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE pembelian_bahan MODIFY gramasi DECIMAL(10, 2) NOT NULL');
    }
};
