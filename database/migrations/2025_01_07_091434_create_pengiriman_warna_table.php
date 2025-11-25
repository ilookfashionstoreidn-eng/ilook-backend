<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePengirimanWarnaTable extends Migration
{
    public function up()
    {
        Schema::create('pengiriman_warna', function (Blueprint $table) {
            $table->id('id_pengiriman_warna');
            $table->unsignedBigInteger('id_pengiriman'); // Pastikan tipe datanya cocok
            $table->string('warna', 50);
            $table->integer('jumlah_dikirim');
            $table->timestamps();

            $table->foreign('id_pengiriman')->references('id_pengiriman')->on('pengiriman')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pengiriman_warna');
    }
}
