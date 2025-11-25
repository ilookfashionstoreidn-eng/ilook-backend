<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderItemsTable extends Migration
{
   
    public function up()
    {
        Schema::create('order_items', function (Blueprint $table) {
             $table->id();
            $table->foreignId('order_id')->constrained('order')->onDelete('cascade');
            $table->string('sku');
            $table->string('product_name');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 15, 2)->default(0); // harga per item
            $table->timestamps();
        });
    }

    
    public function down()
    {
        Schema::dropIfExists('order_items');
    }
}
