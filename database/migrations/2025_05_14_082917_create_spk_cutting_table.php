<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCuttingTable extends Migration
{
    
    public function up()
    {
        Schema::create('spk_cutting', function (Blueprint $table) {
            $table->id();
            $table->string('id_spk_cutting');
            $table->foreignId('produk_id')->constrained('produk');
            $table->date('tanggal_batas_kirim');
            $table->text('keterangan')->nullable();
            $table->decimal('harga_jasa', 10, 2);
            $table->enum('satuan_harga', ['Lusin', 'Pcs']); 
            $table->decimal('harga_per_pcs', 10, 2); 

            $table->timestamps();
        });
    }

   
    public function down()
    {
        Schema::dropIfExists('spk_cutting');
    }
}
