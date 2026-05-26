<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductListImagesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('product_list_images')) {
            return;
        }

        Schema::create('product_list_images', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_list_images');
    }
}
