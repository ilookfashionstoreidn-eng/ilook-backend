<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePetugasDTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('petugas_d', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('petugas_c_id')->constrained('petugas_c')->onDelete('cascade'); // Relasi ke petugas_c
            $table->foreignId('stok_aksesoris_id')->constrained('stok_aksesoris')->onDelete('cascade'); // Relasi ke stok_aksesoris
            $table->enum('status', ['valid', 'invalid'])->default('invalid'); // Status verifikasi
            $table->integer('jumlah_di-scan')->default(0); // Jumlah yang discan oleh Petugas D
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
        Schema::dropIfExists('petugas_d');
    }
}
