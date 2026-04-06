<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
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
        if (!$this->hasIndex('spk_cutting', 'idx_spk_cutting_tanggal_batas_kirim')) {
            Schema::table('spk_cutting', function (Blueprint $table) {
                $table->index('tanggal_batas_kirim', 'idx_spk_cutting_tanggal_batas_kirim');
            });
        }

        if (!$this->hasIndex('spk_cutting_distribusi', 'idx_spk_cutting_distribusi_kode_seri_id')) {
            Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
                $table->index(['kode_seri', 'id'], 'idx_spk_cutting_distribusi_kode_seri_id');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('spk_cutting', 'idx_spk_cutting_tanggal_batas_kirim')) {
            Schema::table('spk_cutting', function (Blueprint $table) {
                $table->dropIndex('idx_spk_cutting_tanggal_batas_kirim');
            });
        }

        if ($this->hasIndex('spk_cutting_distribusi', 'idx_spk_cutting_distribusi_kode_seri_id')) {
            Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
                $table->dropIndex('idx_spk_cutting_distribusi_kode_seri_id');
            });
        }
    }
};
