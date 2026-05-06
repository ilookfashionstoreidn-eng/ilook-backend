<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToGudangProdukWorkspaceTables extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gudang_produk_activity_logs')) {
            Schema::table('gudang_produk_activity_logs', function (Blueprint $table) {
                if (!$this->indexExists('gudang_produk_activity_logs', 'idx_gudang_activity_sku_from_slot_type')) {
                    $table->index(
                        ['sku_id', 'from_slot_id', 'type'],
                        'idx_gudang_activity_sku_from_slot_type'
                    );
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gudang_produk_activity_logs')) {
            Schema::table('gudang_produk_activity_logs', function (Blueprint $table) {
                if ($this->indexExists('gudang_produk_activity_logs', 'idx_gudang_activity_sku_from_slot_type')) {
                    $table->dropIndex('idx_gudang_activity_sku_from_slot_type');
                }
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
}
