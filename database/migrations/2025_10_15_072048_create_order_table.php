<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();       // Nomor order dari marketplace
            $table->string('tracking_number')->unique();    // Nomor resi untuk scan barcode
            $table->string('platform')->nullable();         // Shopee / TikTok / dll
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0); // Total harga
            $table->string('status')->default('ready_to_pack'); // ready_to_pack | packed | shipped
            $table->timestamp('order_date')->nullable();    // Tanggal order
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
        Schema::dropIfExists('order');
    }
}
