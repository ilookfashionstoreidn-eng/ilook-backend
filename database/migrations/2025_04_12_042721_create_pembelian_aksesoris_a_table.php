<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePembelianAksesorisATable extends Migration
{
    
    public function up()
    {
        Schema::create('pembelian_aksesoris_a', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('aksesoris_id')->constrained('aksesoris')->onDelete('cascade');
            $table->integer('jumlah')->unsigned();
            $table->decimal('harga_satuan', 10,2);
            $table->date('tanggal_pembelian');
            $table->string('bukti_pembelian')->nullable();

            $table->timestamps();
            
        });
    }

    
    public function down()
    {
        Schema::dropIfExists('pembelian_aksesoris_a');
    }
}
