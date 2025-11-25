<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashboanTable extends Migration
{
    public function up()
    {
        Schema::create('cashboan', function (Blueprint $table) {
            $table->id('id_cashboan');
            $table->unsignedBigInteger('id_spk');
            $table->decimal('jumlah_cashboan', 10, 2); // Jumlah cashboan
            $table->enum('status_pembayaran', ['belum lunas', 'lunas', 'dibayar sebagian']); // Status pembayaran
            $table->date('tanggal_jatuh_tempo'); // Tanggal jatuh tempo
            $table->date('tanggal_cashboan'); // Tanggal cashboan (tanggal pencatatan cashboan)
            $table->timestamps(); // Tanggal buat dan update

            $table->foreign('id_spk')->references('id_spk')->on('spk_cmt')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cashboan');
    }
}
