<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogPembayaranHutangTable extends Migration
{
  
    public function up()
    {
        Schema::create('log_pembayaran_hutang', function (Blueprint $table) {
            $table->id('id_log_hutang');
            $table->unsignedBigInteger('id_hutang'); 
            $table->decimal('jumlah_dibayar', 10, 2);
            $table->date('tanggal_bayar');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('id_hutang')->references('id_hutang')->on('hutang')->onDelete('cascade');
        });
    }

    
    public function down()
    {
        Schema::dropIfExists('log_pembayaran_hutang');
    }
}
