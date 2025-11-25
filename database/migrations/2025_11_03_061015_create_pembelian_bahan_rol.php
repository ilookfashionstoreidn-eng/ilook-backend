<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePembelianBahanRol extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pembelian_bahan_rol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_bahan_warna_id')->constrained('pembelian_bahan_warna')->onDelete('cascade');
            $table->decimal('berat', 10,2);
            $table->string('barcode')->unique();
            $table->string('status')->default('tersedia');
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
        Schema::dropIfExists('pembelian_bahan_rol');
    }
}
