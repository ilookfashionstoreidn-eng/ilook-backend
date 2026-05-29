<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpkCuttingBahanSkusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spk_cutting_bahan_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_cutting_bahan_id')->constrained('spk_cutting_bahan')->onDelete('cascade');
            $table->foreignId('sku_id')->constrained('product_lists')->onDelete('cascade');
            $table->decimal('qty', 10, 2);
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
        Schema::dropIfExists('spk_cutting_bahan_skus');
    }
}
