<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OptimizeProductListsForLargeData extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('product_lists')) {
            return;
        }

        if (!Schema::hasColumn('product_lists', 'material_count')) {
            Schema::table('product_lists', function (Blueprint $table) {
                $table->unsignedSmallInteger('material_count')->default(0)->after('materials');
            });

            try {
                DB::statement('UPDATE product_lists SET material_count = COALESCE(JSON_LENGTH(materials), 0)');
            } catch (\Throwable $e) {
                DB::table('product_lists')
                    ->select('id', 'materials')
                    ->orderBy('id')
                    ->chunkById(1000, function ($rows) {
                        foreach ($rows as $row) {
                            $materials = json_decode($row->materials ?: '[]', true);
                            DB::table('product_lists')
                                ->where('id', $row->id)
                                ->update(['material_count' => is_array($materials) ? count($materials) : 0]);
                        }
                    });
            }
        }

        Schema::table('product_lists', function (Blueprint $table) {
            if (!$this->indexExists('product_lists', 'idx_product_lists_product_colour')) {
                $table->index('product_colour', 'idx_product_lists_product_colour');
            }

            if (!$this->indexExists('product_lists', 'idx_product_lists_product_size')) {
                $table->index('product_size', 'idx_product_lists_product_size');
            }

            if (!$this->indexExists('product_lists', 'idx_product_lists_ukuran')) {
                $table->index('ukuran', 'idx_product_lists_ukuran');
            }

            if (!$this->indexExists('product_lists', 'idx_product_lists_created_id')) {
                $table->index(['created_at', 'id'], 'idx_product_lists_created_id');
            }

            if (!$this->indexExists('product_lists', 'idx_product_lists_group_id')) {
                $table->index(['product_group', 'id'], 'idx_product_lists_group_id');
            }

            if (!$this->indexExists('product_lists', 'idx_product_lists_source_id')) {
                $table->index(['product_source', 'id'], 'idx_product_lists_source_id');
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
                'idx_product_lists_product_colour',
                'idx_product_lists_product_size',
                'idx_product_lists_ukuran',
                'idx_product_lists_created_id',
                'idx_product_lists_group_id',
                'idx_product_lists_source_id',
            ] as $indexName) {
                if ($this->indexExists('product_lists', $indexName)) {
                    $table->dropIndex($indexName);
                }
            }

            if (Schema::hasColumn('product_lists', 'material_count')) {
                $table->dropColumn('material_count');
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
