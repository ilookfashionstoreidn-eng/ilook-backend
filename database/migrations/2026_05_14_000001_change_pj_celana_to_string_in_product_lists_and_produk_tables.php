<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangePjCelanaToStringInProductListsAndProdukTables extends Migration
{
    public function up()
    {
        Schema::table('product_lists', function (Blueprint $table) {
            $table->string('pj_celana', 255)->nullable()->change();
        });

        if (Schema::hasTable('produk') && Schema::hasColumn('produk', 'pj_celana')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->string('pj_celana', 255)->nullable()->change();
            });
        }
    }

    public function down()
    {
        Schema::table('product_lists', function (Blueprint $table) {
            $table->decimal('pj_celana', 10, 2)->nullable()->change();
        });

        if (Schema::hasTable('produk') && Schema::hasColumn('produk', 'pj_celana')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->decimal('pj_celana', 10, 2)->nullable()->change();
            });
        }
    }
}
