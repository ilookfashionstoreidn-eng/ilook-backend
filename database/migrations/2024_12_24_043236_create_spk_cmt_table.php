<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCmtTable extends Migration
{
    public function up()
    {
        Schema::create('spk_cmt', function (Blueprint $table) {
            $table->id('id_spk'); // Primary Key
            $table->date('tgl_spk'); // Tanggal SPK
            $table->string('nama_produk', 100); // Nama Produk
            $table->integer('jumlah_produk'); // Total Barang yang Dipesan
            $table->date('deadline'); // Deadline SPK
            $table->unsignedBigInteger('id_penjahit'); // Relasi ke tabel penjahit
            $table->text('keterangan')->nullable(); // Catatan Tambahan
            $table->string('status')->default('Pending');
            $table->timestamps(); // Created_at dan Updated_at
            $table->foreign('id_penjahit')->references('id_penjahit')->on('penjahit_cmt')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('spk_cmt');
    }
    
}
