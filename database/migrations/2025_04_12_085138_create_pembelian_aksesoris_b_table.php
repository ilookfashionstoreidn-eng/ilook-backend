<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePembelianAksesorisBTable extends Migration
{
    
    public function up()
    {
        Schema::create('pembelian_aksesoris_b', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_a_id')->constrained('pembelian_aksesoris_a', 'id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('jumlah_terverifikasi')->unsigned();
            $table->enum('status_verifikasi',['pending','valid','invalid'])->default('pending');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pembelian_aksesoris_b');
    }
}
