<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStokAksesorisTable extends Migration
{
    
    public function up()
    {
        Schema::create('stok_aksesoris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_aksesoris_b_id')->constrained('pembelian_aksesoris_b')->onDelete('cascade');
            $table->foreignId('aksesoris_id')->constrained('aksesoris')->onDelete('cascade');
            $table->string('barcode')->unique();
            $table->enum('status', ['tersedia', 'terpakai'])->default('tersedia');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stok_aksesoris');
    }
}
