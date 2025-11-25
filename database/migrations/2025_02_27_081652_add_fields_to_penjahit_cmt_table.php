<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToPenjahitCmtTable extends Migration
{
    public function up()
    {
        Schema::table('penjahit_cmt', function (Blueprint $table) {
            $table->string('ktp')->nullable(); 
            $table->string('kategori_penjahit')->nullable();
            $table->integer('jumlah_tim')->nullable();
            $table->json('mesin')->nullable(); 
            $table->string('no_rekening')->nullable();
            $table->string('bank')->nullable();
        });
    }

    public function down()
    {
        Schema::table('penjahit_cmt', function (Blueprint $table) {
            $table->dropColumn(['ktp','kategori_penjahit','jumlah_tim', 'mesin', 'no_rekening', 'bank']);
        });
    }
}
