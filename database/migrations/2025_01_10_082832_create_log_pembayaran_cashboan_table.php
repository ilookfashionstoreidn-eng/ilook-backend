<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogPembayaranCashboanTable extends Migration
{
    
    public function up()
    {
        Schema::create('log_pembayaran_cashboan', function (Blueprint $table) {
            $table->id('id_log_pembayaran');
            $table->unsignedBigInteger('id_cashboan'); 
            $table->decimal('jumlah_dibayar', 10, 2);
            $table->date('tanggal_bayar');
            $table->text('catatan')->nullable();
            $table->timestamps();

            // Relasii FK
            $table->foreign('id_cashboan')->references('id_cashboan')->on('cashboan')->onDelete('cascade');
        });
    }

    
    public function down()
    {
        Schema::dropIfExists('log_pembayaran_cashboan');
    }
}
