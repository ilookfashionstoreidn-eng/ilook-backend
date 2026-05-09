<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('bahan', function (Blueprint $table) {
            if (!Schema::hasColumn('bahan', 'pabrik_bahan')) {
                $table->string('pabrik_bahan')->nullable()->after('group_bahan');
            }

            if (!Schema::hasColumn('bahan', 'stok_bahan')) {
                $table->decimal('stok_bahan', 15, 2)->default(0)->after('warna_bahan');
            }
        });

        if ($this->indexExists('bahan', 'bahan_nama_bahan_unique')) {
            Schema::table('bahan', function (Blueprint $table) {
                $table->dropUnique('bahan_nama_bahan_unique');
            });
        }

        if (!$this->indexExists('bahan', 'idx_bahan_import_lookup')) {
            Schema::table('bahan', function (Blueprint $table) {
                $table->index(['nama_bahan', 'warna_bahan', 'pabrik_bahan'], 'idx_bahan_import_lookup');
            });
        }
    }

    public function down()
    {
        if ($this->indexExists('bahan', 'idx_bahan_import_lookup')) {
            Schema::table('bahan', function (Blueprint $table) {
                $table->dropIndex('idx_bahan_import_lookup');
            });
        }

        Schema::table('bahan', function (Blueprint $table) {
            if (Schema::hasColumn('bahan', 'stok_bahan')) {
                $table->dropColumn('stok_bahan');
            }

            if (Schema::hasColumn('bahan', 'pabrik_bahan')) {
                $table->dropColumn('pabrik_bahan');
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
