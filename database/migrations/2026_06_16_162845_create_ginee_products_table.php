<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGineeProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('ginee_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('product_name');
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamp('last_synced_at')->nullable();
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
        Schema::dropIfExists('ginee_products');
    }
}
