<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCuttingSkusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('spk_cutting_skus', function (Blueprint $table) {
            $table->id();

            $table->foreignId('spk_cutting_id')
                ->constrained('spk_cutting')
                ->cascadeOnDelete();

            $table->foreignId('produk_sku_id')
                ->constrained('produk_sku')
                ->cascadeOnDelete();

            $table->timestamps();

            // biar 1 SKU ga dobel di 1 SPK
            $table->unique(['spk_cutting_id', 'produk_sku_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('spk_cutting_skus');
    }
}
