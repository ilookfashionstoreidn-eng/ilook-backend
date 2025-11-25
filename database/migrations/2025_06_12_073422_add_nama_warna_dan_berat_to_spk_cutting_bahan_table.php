<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNamaWarnaDanBeratToSpkCuttingBahanTable extends Migration
{
   
    public function up()
    {
        Schema::table('spk_cutting_bahan', function (Blueprint $table) {
            $table->string('nama_warna')->nullable();
            $table->float('berat')->nullable();
        });
    }

    
    public function down()
    {
        Schema::table('spk_cutting_bahan', function (Blueprint $table) {
              $table->dropColumn(['nama_warna', 'berat']);
        });
    }
}
