<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGudangProdukDetailVerifikasi extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('gudang_produk_detail_verifikasi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gudang_produk_detail_id')
                ->constrained('gudang_produk_detail')
                ->cascadeOnDelete();

            $table->integer('qty_verifikasi');
            $table->unsignedBigInteger('created_by');
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
        Schema::dropIfExists('gudang_produk_detail_verifikasi');
    }
}
