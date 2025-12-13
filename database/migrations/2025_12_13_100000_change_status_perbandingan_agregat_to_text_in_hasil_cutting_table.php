<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeStatusPerbandinganAgregatToTextInHasilCuttingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Mengubah kolom dari VARCHAR ke TEXT untuk menyimpan JSON yang lebih panjang
        // Menggunakan DB::statement untuk kompatibilitas yang lebih baik
        DB::statement('ALTER TABLE hasil_cutting MODIFY COLUMN status_perbandingan_agregat TEXT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Kembalikan ke VARCHAR(255) - perhatikan bahwa data mungkin terpotong jika terlalu panjang
        DB::statement('ALTER TABLE hasil_cutting MODIFY COLUMN status_perbandingan_agregat VARCHAR(255) NULL');
    }
}
