<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductListsTableTwo extends Migration
{
    public function up()
    {
        if (Schema::hasTable('product_lists')) {
            return;
        }

        Schema::create('product_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_list_image_id')->nullable()->constrained('product_list_images')->nullOnDelete();
            $table->string('product');
            $table->string('sku_name')->unique();
            $table->string('product_group')->nullable();
            $table->string('product_size')->nullable();
            $table->string('product_source')->nullable();
            $table->string('product_colour')->nullable();
            $table->json('materials')->nullable();
            $table->unsignedInteger('material_count')->default(0);
            $table->integer('estimasi_cutting')->nullable();
            $table->integer('estimasi_combi')->nullable();
            $table->string('id_s')->nullable();
            $table->string('id_m')->nullable();
            $table->string('id_l')->nullable();
            $table->string('id_xl')->nullable();
            $table->string('ukuran')->nullable();
            $table->decimal('pj_dress', 12, 2)->nullable();
            $table->string('pj_celana')->nullable();
            $table->decimal('pj_baju', 12, 2)->nullable();
            $table->decimal('price_cmt', 15, 2)->nullable();
            $table->decimal('price_cutting', 15, 2)->nullable();
            $table->text('notes_spk')->nullable();
            $table->timestamps();

            $table->index('product');
            $table->index('product_group');
            $table->index('product_source');
            $table->index('product_colour');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_lists');
    }
}
