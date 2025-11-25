<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHutangTable extends Migration
{
    public function up()
    {
        Schema::create('hutang', function (Blueprint $table) {
            $table->id('id_hutang');
            $table->unsignedBigInteger('id_spk');
            $table->decimal('jumlah_hutang', 10, 2);
            $table->enum('status_pembayaran', ['belum lunas', 'lunas', 'dibayar sebagian']); // Status pembayaran
            $table->date('tanggal_jatuh_tempo');
            $table->date('tanggal_hutang');
            $table->timestamps();

            $table->foreign('id_spk')->references('id_spk')->on('spk_cmt')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('hutang');
    }
}
