<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductListsTable extends Migration
{
    public function up()
    {
        Schema::create('product_lists', function (Blueprint $table) {
            $table->id();
            $table->string('product')->index();
            $table->string('sku_name')->nullable()->index();
            $table->string('product_group')->nullable()->index();
            $table->string('product_size')->nullable();
            $table->string('product_source')->nullable()->index();
            $table->string('product_colour')->nullable();
            $table->json('materials')->nullable();
            $table->unsignedSmallInteger('material_count')->default(0);
            $table->decimal('estimasi_cutting', 12, 2)->nullable();
            $table->decimal('estimasi_combi', 12, 2)->nullable();
            $table->string('id_s')->nullable();
            $table->string('id_m')->nullable();
            $table->string('id_l')->nullable();
            $table->string('id_xl')->nullable();
            $table->decimal('pj_dress', 10, 2)->nullable();
            $table->decimal('pj_celana', 10, 2)->nullable();
            $table->decimal('pj_baju', 10, 2)->nullable();
            $table->decimal('price_cmt', 14, 2)->nullable();
            $table->decimal('price_cutting', 14, 2)->nullable();
            $table->text('notes_spk')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_lists');
    }
}
