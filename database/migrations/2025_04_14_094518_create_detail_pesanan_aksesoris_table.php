<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetailPesananAksesorisTable extends Migration
{
    public function up()
    {
        Schema::create('detail_pesanan_aksesoris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petugas_c_id')->constrained('petugas_c')->onDelete('cascade');
            $table->foreignId('aksesoris_id')->constrained('aksesoris')->onDelete('cascade');
            $table->integer('jumlah_dipesan');
            $table->decimal('total_harga', 12, 2)->nullable(); // Optional: bisa disimpan atau dihitung saat butuh
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
        Schema::dropIfExists('detail_pesanan_aksesoris');
    }
}
