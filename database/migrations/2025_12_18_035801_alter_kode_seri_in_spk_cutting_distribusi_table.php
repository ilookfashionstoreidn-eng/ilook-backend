<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterKodeSeriInSpkCuttingDistribusiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
            $table->string('kode_seri', 20)->change(); // ubah panjang kolom menjadi 10
        });
    }

    public function down()
    {
        Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
            $table->string('kode_seri', 5)->change(); // kembalikan ke 5 jika rollback
        });
    }
}
