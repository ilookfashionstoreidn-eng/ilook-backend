<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('spk_bahan', ['pabrik_id'], 'idx_spk_bahan_pabrik_id');
        $this->addIndexIfMissing('spk_bahan', ['bahan_id'], 'idx_spk_bahan_bahan_id');
        $this->addIndexIfMissing('spk_bahan', ['status'], 'idx_spk_bahan_status');
        $this->addIndexIfMissing('spk_bahan', ['tanggal_pembayaran'], 'idx_spk_bahan_tanggal_pembayaran');
        $this->addIndexIfMissing('spk_bahan', ['jenis_pembayaran'], 'idx_spk_bahan_jenis_pembayaran');

        // id sudah primary key, otomatis terindeks.
        $this->addIndexIfMissing('spk_bahan_warna', ['spk_bahan_id'], 'idx_spk_bahan_warna_spk_bahan_id');
        $this->addIndexIfMissing('spk_bahan_warna', ['warna'], 'idx_spk_bahan_warna_warna');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('spk_bahan', 'idx_spk_bahan_pabrik_id');
        $this->dropIndexIfExists('spk_bahan', 'idx_spk_bahan_bahan_id');
        $this->dropIndexIfExists('spk_bahan', 'idx_spk_bahan_status');
        $this->dropIndexIfExists('spk_bahan', 'idx_spk_bahan_tanggal_pembayaran');
        $this->dropIndexIfExists('spk_bahan', 'idx_spk_bahan_jenis_pembayaran');

        $this->dropIndexIfExists('spk_bahan_warna', 'idx_spk_bahan_warna_spk_bahan_id');
        $this->dropIndexIfExists('spk_bahan_warna', 'idx_spk_bahan_warna_warna');
    }

    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $row = DB::table('information_schema.statistics')
            ->select('index_name')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->first();

        return $row !== null;
    }
};
