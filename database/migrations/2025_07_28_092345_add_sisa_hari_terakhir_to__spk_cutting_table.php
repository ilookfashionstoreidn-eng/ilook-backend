<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSisaHariTerakhirToSpkCuttingTable extends Migration
{
   
    public function up()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            $table->integer('sisa_hari_terakhir')->nullable();
            $table->integer('waktu_pengerjaan_terakhir')->nullable();
        });
    }

  
    public function down()
    {
        Schema::table('_spk_cutting', function (Blueprint $table) {
             $table->dropColumn(['sisa_hari_terakhir', 'hwaktu_pengerjaan_terakhir']);
        });
    }
}
