<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProdukKomponenTable extends Migration
{
    
    public function up()
    {
        Schema::create('produk_komponen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->onDelete('cascade');
            $table->string('jenis_komponen');
            $table->string('nama_bahan')->nullable();
            $table->decimal('harga_bahan', 15, 2)->default(0);
            $table->decimal('jumlah_bahan', 12, 3)->default(0);
            $table->string('satuan_bahan')->nullable();
            $table->decimal('total_harga_bahan', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    
    public function down()
    {
        Schema::dropIfExists('produk_komponen');
    }
}
