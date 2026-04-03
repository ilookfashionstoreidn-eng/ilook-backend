<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesForHasilJasaSearchAndSort extends Migration
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
        if (!$this->hasIndex('hasil_jasa', 'idx_hasil_jasa_tanggal')) {
            Schema::table('hasil_jasa', function (Blueprint $table) {
                $table->index('tanggal', 'idx_hasil_jasa_tanggal');
            });
        }

        if (!$this->hasIndex('hasil_jasa', 'idx_hasil_jasa_total_pendapatan')) {
            Schema::table('hasil_jasa', function (Blueprint $table) {
                $table->index('total_pendapatan', 'idx_hasil_jasa_total_pendapatan');
            });
        }

        if (!$this->hasIndex('hasil_jasa', 'idx_hasil_jasa_spk_tanggal')) {
            Schema::table('hasil_jasa', function (Blueprint $table) {
                $table->index(['spk_jasa_id', 'tanggal'], 'idx_hasil_jasa_spk_tanggal');
            });
        }

        if (!$this->hasIndex('spk_cutting_distribusi', 'idx_spk_cutting_distribusi_kode_seri')) {
            Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
                $table->index('kode_seri', 'idx_spk_cutting_distribusi_kode_seri');
            });
        }

        if (!$this->hasIndex('tukang_jasa', 'idx_tukang_jasa_nama')) {
            Schema::table('tukang_jasa', function (Blueprint $table) {
                $table->index('nama', 'idx_tukang_jasa_nama');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('hasil_jasa', 'idx_hasil_jasa_tanggal')) {
            Schema::table('hasil_jasa', function (Blueprint $table) {
                $table->dropIndex('idx_hasil_jasa_tanggal');
            });
        }

        if ($this->hasIndex('hasil_jasa', 'idx_hasil_jasa_total_pendapatan')) {
            Schema::table('hasil_jasa', function (Blueprint $table) {
                $table->dropIndex('idx_hasil_jasa_total_pendapatan');
            });
        }

        if ($this->hasIndex('hasil_jasa', 'idx_hasil_jasa_spk_tanggal')) {
            Schema::table('hasil_jasa', function (Blueprint $table) {
                $table->dropIndex('idx_hasil_jasa_spk_tanggal');
            });
        }

        if ($this->hasIndex('spk_cutting_distribusi', 'idx_spk_cutting_distribusi_kode_seri')) {
            Schema::table('spk_cutting_distribusi', function (Blueprint $table) {
                $table->dropIndex('idx_spk_cutting_distribusi_kode_seri');
            });
        }

        if ($this->hasIndex('tukang_jasa', 'idx_tukang_jasa_nama')) {
            Schema::table('tukang_jasa', function (Blueprint $table) {
                $table->dropIndex('idx_tukang_jasa_nama');
            });
        }
    }
}
