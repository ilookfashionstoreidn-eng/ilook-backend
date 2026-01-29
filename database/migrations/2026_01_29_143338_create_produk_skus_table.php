<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProdukSkusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('produk_sku', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')
                ->constrained('produk')
                ->cascadeOnDelete();

            $table->string('warna');
            $table->string('ukuran');
            $table->string('sku')->unique();

            $table->timestamps();

            // cegah kombinasi dobel
            $table->unique(['produk_id', 'warna', 'ukuran']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('produk_sku');
    }
}
