<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSizeIdsAndPricesToProductListsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('product_lists')) {
            return;
        }

        Schema::table('product_lists', function (Blueprint $table) {
            if (!Schema::hasColumn('product_lists', 'id_s')) {
                $table->string('id_s')->nullable()->after('estimasi_combi');
            }

            if (!Schema::hasColumn('product_lists', 'id_m')) {
                $table->string('id_m')->nullable()->after('id_s');
            }

            if (!Schema::hasColumn('product_lists', 'id_l')) {
                $table->string('id_l')->nullable()->after('id_m');
            }

            if (!Schema::hasColumn('product_lists', 'id_xl')) {
                $table->string('id_xl')->nullable()->after('id_l');
            }

            if (!Schema::hasColumn('product_lists', 'price_cmt')) {
                $table->decimal('price_cmt', 14, 2)->nullable()->after('pj_baju');
            }

            if (!Schema::hasColumn('product_lists', 'price_cutting')) {
                $table->decimal('price_cutting', 14, 2)->nullable()->after('price_cmt');
            }
        });

        Schema::table('product_lists', function (Blueprint $table) {
            foreach ([
                'id_s' => 'idx_product_lists_id_s',
                'id_m' => 'idx_product_lists_id_m',
                'id_l' => 'idx_product_lists_id_l',
                'id_xl' => 'idx_product_lists_id_xl',
            ] as $column => $indexName) {
                if (Schema::hasColumn('product_lists', $column) && !$this->indexExists('product_lists', $indexName)) {
                    $table->index($column, $indexName);
                }
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('product_lists')) {
            return;
        }

        Schema::table('product_lists', function (Blueprint $table) {
            foreach ([
                'idx_product_lists_id_s',
                'idx_product_lists_id_m',
                'idx_product_lists_id_l',
                'idx_product_lists_id_xl',
            ] as $indexName) {
                if ($this->indexExists('product_lists', $indexName)) {
                    $table->dropIndex($indexName);
                }
            }

            foreach (['id_s', 'id_m', 'id_l', 'id_xl', 'price_cmt', 'price_cutting'] as $column) {
                if (Schema::hasColumn('product_lists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
}
