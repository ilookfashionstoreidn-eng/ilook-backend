<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStokGudangProdukTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('stok_gudang_produk', function (Blueprint $table) {
            $table->id();

            // 1 SKU = 1 baris stok
            $table->unsignedBigInteger('sku_id')->unique();

            // saldo stok berjalan
            $table->integer('qty')->default(0);

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
        Schema::dropIfExists('stok_gudang_produk');
    }
}
