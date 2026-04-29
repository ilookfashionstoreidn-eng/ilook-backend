<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSkuNameToProductListsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('product_lists') && !Schema::hasColumn('product_lists', 'sku_name')) {
            Schema::table('product_lists', function (Blueprint $table) {
                $table->string('sku_name')->nullable()->index()->after('product');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('product_lists') && Schema::hasColumn('product_lists', 'sku_name')) {
            Schema::table('product_lists', function (Blueprint $table) {
                $table->dropColumn('sku_name');
            });
        }
    }
}
