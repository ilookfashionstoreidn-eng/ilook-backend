<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGudangProdukDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('gudang_produk_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gudang_produk_id')
                ->constrained('gudang_produk')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('sku_id');
            $table->integer('qty_acuan');
            $table->timestamps();

            // mencegah 1 transaksi punya SKU yang sama
            $table->unique(['gudang_produk_id', 'sku_id']);
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gudang_produk_detail');
    }
}
