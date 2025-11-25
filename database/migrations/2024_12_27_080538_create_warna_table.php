<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWarnaTable extends Migration
{
    public function up()
    {
        Schema::create('warna', function (Blueprint $table) {
            $table->id('id_warna'); // Primary Key
            $table->string('nama_warna', 50); // Nama warna (contoh: Merah, Biru)
            $table->integer('qty')->default(0); // Jumlah produk untuk warna tertentu
            $table->unsignedBigInteger('id_spk'); // Foreign Key ke tabel SPK CMT
            $table->timestamps(); // Kolom created_at dan updated_at

             // Definisi Foreign Key
            $table->foreign('id_spk')
            ->references('id_spk') // Mengacu ke kolom id_spk di spk_cmt
            ->on('spk_cmt')
            ->onDelete('cascade'); // Hapus warna jika SPK dihapus
        });
            }

    public function down()
    {
        Schema::dropIfExists('warna');
    }
}
