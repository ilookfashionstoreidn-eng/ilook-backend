<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePenjahitCmtTable extends Migration
{
    public function up()
    {
        Schema::create('penjahit_cmt', function (Blueprint $table) {
            $table->id('id_penjahit');
            $table->string('nama_penjahit', 100);
            $table->string('kontak', 100);
            $table->text('alamat');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('penjahit_cmt');
    }
}
