<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFulltextIndexesForHasilJasaSearch extends Migration
{
    private function hasIndex(string $tableName, string $indexName): bool
    {
        $result = DB::select(
            "SELECT 1
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?
             LIMIT 1",
            [$tableName, $indexName]
        );

        return !empty($result);
    }

    public function up(): void
    {
        if (!$this->hasIndex('tukang_jasa', 'ft_tukang_jasa_nama')) {
            Schema::table('tukang_jasa', function (Blueprint $table) {
                $table->fullText('nama', 'ft_tukang_jasa_nama');
            });
        }

        if (!$this->hasIndex('produk', 'ft_produk_nama_produk')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->fullText('nama_produk', 'ft_produk_nama_produk');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('tukang_jasa', 'ft_tukang_jasa_nama')) {
            Schema::table('tukang_jasa', function (Blueprint $table) {
                $table->dropIndex('ft_tukang_jasa_nama');
            });
        }

        if ($this->hasIndex('produk', 'ft_produk_nama_produk')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->dropIndex('ft_produk_nama_produk');
            });
        }
    }
}
