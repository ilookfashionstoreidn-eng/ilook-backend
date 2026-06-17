<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ginee_products', function (Blueprint $table) {
            $table->text('image_url')->nullable()->change();
            $table->text('product_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ginee_products', function (Blueprint $table) {
            $table->string('image_url', 255)->nullable()->change();
            $table->string('product_name', 255)->nullable()->change();
        });
    }
};
