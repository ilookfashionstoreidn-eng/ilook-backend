<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveSkuFromOrderItemSerialsTable extends Migration
{
    public function up()
    {
        Schema::table('order_item_serials', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
    }

    public function down()
    {
        Schema::table('order_item_serials', function (Blueprint $table) {
            $table->string('sku')->nullable();
        });
    }
}
