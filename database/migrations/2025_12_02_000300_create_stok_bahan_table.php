<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('stok_bahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_bahan_id')->constrained('pembelian_bahan')->onDelete('cascade');
            $table->foreignId('pembelian_bahan_warna_id')->constrained('pembelian_bahan_warna')->onDelete('cascade');
            $table->foreignId('pembelian_bahan_rol_id')->constrained('pembelian_bahan_rol')->onDelete('cascade');
            $table->foreignId('gudang_id')->constrained('gudang')->onDelete('cascade');
            $table->foreignId('pabrik_id')->constrained('pabrik')->onDelete('cascade');
            $table->string('barcode')->unique();
            $table->decimal('berat', 12, 3)->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stok_bahan');
    }
};

