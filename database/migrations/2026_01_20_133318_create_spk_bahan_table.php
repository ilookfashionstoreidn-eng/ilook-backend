<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkBahanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('spk_bahan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pabrik_id');
            $table->unsignedBigInteger('bahan_id');
            $table->integer('jumlah');
            $table->string('jenis_pembayaran');
            $table->date('tanggal_pembayaran');
            $table->string('status');
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
        Schema::dropIfExists('spk_bahan');
    }
}
