<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesToHasilCuttingAndProdukTables extends Migration
{
    /**
     * Cek apakah index sudah ada berdasarkan nama index.
     */
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

    public function up()
    {
        if (!$this->hasIndex('hasil_cutting', 'idx_hasil_cutting_created_at')) {
            Schema::table('hasil_cutting', function (Blueprint $table) {
                $table->index('created_at', 'idx_hasil_cutting_created_at');
            });
        }

        if (!$this->hasIndex('produk', 'idx_produk_nama_produk')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->index('nama_produk', 'idx_produk_nama_produk');
            });
        }
    }

    public function down()
    {
        if ($this->hasIndex('hasil_cutting', 'idx_hasil_cutting_created_at')) {
            Schema::table('hasil_cutting', function (Blueprint $table) {
                $table->dropIndex('idx_hasil_cutting_created_at');
            });
        }

        if ($this->hasIndex('produk', 'idx_produk_nama_produk')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->dropIndex('idx_produk_nama_produk');
            });
        }
    }
}

