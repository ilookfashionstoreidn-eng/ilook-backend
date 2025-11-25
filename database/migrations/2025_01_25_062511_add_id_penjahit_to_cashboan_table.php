<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdPenjahitToCashboanTable extends Migration
{
    public function up()
    {
        Schema::table('cashboan', function (Blueprint $table) {
            $table->unsignedBigInteger('id_penjahit')->nullable(); // Tambahkan kolom
            $table->foreign('id_penjahit')->references('id_penjahit')->on('penjahit_cmt')->onDelete('cascade'); // Set FK
        });
    }

    
    public function down()
    {
        Schema::table('cashboan', function (Blueprint $table) {
            $table->dropForeign(['id_penjahit']);
            $table->dropColumn('id_penjahit');
        });
    }
}
