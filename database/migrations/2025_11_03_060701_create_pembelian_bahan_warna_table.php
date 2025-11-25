<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePembelianBahanWarnaTable extends Migration
{
    public function up()
    {
        Schema::create('pembelian_bahan_warna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_bahan_id')->constrained('pembelian_bahan')->onDelete('cascade');
            $table->string('warna');
            $table->integer('jumlah_rol');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pembelian_bahan_warna');
    }
}
