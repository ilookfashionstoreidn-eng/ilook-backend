<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('bahan', function (Blueprint $table) {
            foreach ([
                ['nama_bahan', 'idx_bahan_nama_bahan'],
                ['group_bahan', 'idx_bahan_group_bahan'],
                ['pabrik_bahan', 'idx_bahan_pabrik_bahan'],
                ['satuan', 'idx_bahan_satuan'],
                ['warna_bahan', 'idx_bahan_warna_bahan'],
            ] as [$column, $indexName]) {
                if (Schema::hasColumn('bahan', $column) && !$this->indexExists('bahan', $indexName)) {
                    $table->index($column, $indexName);
                }
            }
        });
    }

    public function down()
    {
        Schema::table('bahan', function (Blueprint $table) {
            foreach ([
                'idx_bahan_warna_bahan',
                'idx_bahan_satuan',
                'idx_bahan_pabrik_bahan',
                'idx_bahan_group_bahan',
                'idx_bahan_nama_bahan',
            ] as $indexName) {
                if ($this->indexExists('bahan', $indexName)) {
                    $table->dropIndex($indexName);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])) > 0;
        } catch (\Throwable $error) {
            return false;
        }
    }
};
