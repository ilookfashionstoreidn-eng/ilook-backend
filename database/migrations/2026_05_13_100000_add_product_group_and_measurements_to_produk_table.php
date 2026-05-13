<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductGroupAndMeasurementsToProdukTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('produk')) {
            return;
        }

        Schema::table('produk', function (Blueprint $table) {
            if (!Schema::hasColumn('produk', 'product_group')) {
                $table->string('product_group')->nullable()->after('jenis_produk');
            }

            if (!Schema::hasColumn('produk', 'ld_s')) {
                $table->string('ld_s')->nullable()->after('product_group');
            }

            if (!Schema::hasColumn('produk', 'ld_m')) {
                $table->string('ld_m')->nullable()->after('ld_s');
            }

            if (!Schema::hasColumn('produk', 'ld_l')) {
                $table->string('ld_l')->nullable()->after('ld_m');
            }

            if (!Schema::hasColumn('produk', 'ld_xl')) {
                $table->string('ld_xl')->nullable()->after('ld_l');
            }

            if (!Schema::hasColumn('produk', 'pj_dress')) {
                $table->decimal('pj_dress', 10, 2)->nullable()->after('ld_xl');
            }

            if (!Schema::hasColumn('produk', 'pj_celana')) {
                $table->decimal('pj_celana', 10, 2)->nullable()->after('pj_dress');
            }

            if (!Schema::hasColumn('produk', 'pj_baju')) {
                $table->decimal('pj_baju', 10, 2)->nullable()->after('pj_celana');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('produk')) {
            return;
        }

        Schema::table('produk', function (Blueprint $table) {
            foreach (['product_group', 'ld_s', 'ld_m', 'ld_l', 'ld_xl', 'pj_dress', 'pj_celana', 'pj_baju'] as $column) {
                if (Schema::hasColumn('produk', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
