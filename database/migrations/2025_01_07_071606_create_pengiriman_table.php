<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePengirimanTable extends Migration
{
    
     public function up()
    {
        Schema::create('pengiriman', function (Blueprint $table) {
            $table->id('id_pengiriman');
            $table->unsignedBigInteger('id_spk');
            $table->date('tanggal_pengiriman');
            $table->integer('total_barang_dikirim');
            $table->integer('sisa_barang');
            $table->decimal('total_bayar', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('id_spk')->references('id_spk')->on('spk_cmt')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pengiriman');
    }
}