<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePetugasCTable extends Migration
{
    
    public function up()
    {
        Schema::create('petugas_c', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('aksesoris_id')->constrained('aksesoris')->onDelete('cascade');
            $table->foreignId('penjahit_id')->constrained('penjahit_cmt', 'id_penjahit')->onDelete('cascade');
            $table->integer('jumlah_dipesan');
            $table->enum('status', ['pending', 'diproses', 'selesai'])->default('pending');
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
        Schema::dropIfExists('petugas_c');
    }
}
