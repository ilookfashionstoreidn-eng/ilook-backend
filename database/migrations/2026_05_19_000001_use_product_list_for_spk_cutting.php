<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            if (!Schema::hasColumn('spk_cutting', 'product_list_id')) {
                $table->foreignId('product_list_id')
                    ->nullable()
                    ->after('produk_id')
                    ->constrained('product_lists')
                    ->nullOnDelete();
            }
        });

        Schema::table('spk_cutting_skus', function (Blueprint $table) {
            if (!Schema::hasColumn('spk_cutting_skus', 'product_list_id')) {
                $table->foreignId('product_list_id')
                    ->nullable()
                    ->after('produk_sku_id')
                    ->constrained('product_lists')
                    ->cascadeOnDelete();
            }
        });

        $this->dropForeignIfExists('spk_cutting', 'spk_cutting_produk_id_foreign');
        $this->dropForeignIfExists('spk_cutting_skus', 'spk_cutting_skus_produk_sku_id_foreign');
        $this->addIndexIfMissing('spk_cutting_skus', ['spk_cutting_id'], 'spk_cutting_skus_spk_cutting_id_index');
        $this->dropIndexIfExists('spk_cutting_skus', 'spk_cutting_skus_spk_cutting_id_produk_sku_id_unique');

        DB::statement('ALTER TABLE spk_cutting MODIFY produk_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE spk_cutting_skus MODIFY produk_sku_id BIGINT UNSIGNED NULL');

        if (!$this->indexExists('spk_cutting_skus', 'spk_cutting_skus_spk_product_list_unique')) {
            Schema::table('spk_cutting_skus', function (Blueprint $table) {
                $table->unique(['spk_cutting_id', 'product_list_id'], 'spk_cutting_skus_spk_product_list_unique');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('spk_cutting_skus', 'spk_cutting_skus_spk_product_list_unique');

        Schema::table('spk_cutting_skus', function (Blueprint $table) {
            if (Schema::hasColumn('spk_cutting_skus', 'product_list_id')) {
                $table->dropConstrainedForeignId('product_list_id');
            }
        });

        Schema::table('spk_cutting', function (Blueprint $table) {
            if (Schema::hasColumn('spk_cutting', 'product_list_id')) {
                $table->dropConstrainedForeignId('product_list_id');
            }
        });
    }

    private function dropForeignIfExists(string $table, string $foreign): void
    {
        $schema = DB::getDatabaseName();
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $schema)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreign)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$foreign}");
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (!$this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE {$table} DROP INDEX {$index}");
    }

    private function addIndexIfMissing(string $table, array $columns, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index) {
            $table->index($columns, $index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $schema = DB::getDatabaseName();
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
