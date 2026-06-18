<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeSpkSamplesColumnsToJson extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Try modifying using DB statement to avoid dbal json issues if table exists with old data types
        DB::statement('ALTER TABLE spk_samples MODIFY bahan_utama JSON');
        DB::statement('ALTER TABLE spk_samples MODIFY bahan_kombinasi JSON');
        DB::statement('ALTER TABLE spk_samples MODIFY aksesoris JSON');
        DB::statement('ALTER TABLE spk_samples MODIFY warna_yang_akan_dikeluarkan JSON');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE spk_samples MODIFY bahan_utama VARCHAR(255) NULL');
        DB::statement('ALTER TABLE spk_samples MODIFY bahan_kombinasi VARCHAR(255) NULL');
        DB::statement('ALTER TABLE spk_samples MODIFY aksesoris VARCHAR(255) NULL');
        DB::statement('ALTER TABLE spk_samples MODIFY warna_yang_akan_dikeluarkan VARCHAR(255) NULL');
    }
}
