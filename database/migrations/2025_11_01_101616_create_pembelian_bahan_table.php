<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePembelianBahanTable extends Migration
{
    
    public function up()
    {
        Schema::create('pembelian_bahan', function (Blueprint $table) {
            $table->id();
            $table->string('keterangan');
            
            $table->foreignId('gudang_id')->constrained('gudang')->onDelete('cascade');
            $table->foreignId('pabrik_id')->constrained('pabrik')->onDelete('cascade');
            $table->date('tanggal_kirim');
            $table->string('no_surat_jalan')->nullable();
            $table->string('foto_surat_jalan')->nullable();
            
            $table->string('nama_bahan');
            $table->integer('gramasi');
            $table->string('satuan'); 
            $table->integer('lebar_kain');

            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('pembelian_bahan');
    }
}
