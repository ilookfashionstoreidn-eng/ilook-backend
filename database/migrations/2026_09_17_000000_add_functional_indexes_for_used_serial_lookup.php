<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * getUsedSerialMessage() (OrderController) runs on every single barcode scan
 * during packing and matches on `WHERE UPPER(TRIM(serial_number)) IN (...)`.
 * order_item_serials.serial_number and order_packing_result_serials.
 * serial_number already have plain indexes, but wrapping the column in
 * UPPER(TRIM()) makes those indexes unusable — MySQL falls back to a full
 * table scan on every scan, which is the packing-delay source.
 *
 * A functional index on the exact same expression lets MySQL use it without
 * changing the query or the matching behavior at all — only supported on
 * MySQL 8.0.13+ / MariaDB 10.3.7+, so this skips (and logs) rather than
 * failing the whole deploy on an older server.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->supportsFunctionalIndexes()) {
            Log::warning('Skipping functional indexes for used-serial lookup: DB engine does not support them.');
            return;
        }

        $this->addIndexIfMissing(
            'order_item_serials',
            'idx_order_item_serials_upper_trim_serial',
            'CREATE INDEX idx_order_item_serials_upper_trim_serial ON order_item_serials ((UPPER(TRIM(serial_number))))'
        );

        $this->addIndexIfMissing(
            'order_packing_result_serials',
            'idx_order_packing_result_serials_upper_trim_serial',
            'CREATE INDEX idx_order_packing_result_serials_upper_trim_serial ON order_packing_result_serials ((UPPER(TRIM(serial_number))))'
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('order_item_serials', 'idx_order_item_serials_upper_trim_serial');
        $this->dropIndexIfExists('order_packing_result_serials', 'idx_order_packing_result_serials_upper_trim_serial');
    }

    private function supportsFunctionalIndexes(): bool
    {
        try {
            $version = DB::selectOne('select version() as v')->v ?? '';
        } catch (\Throwable $e) {
            return false;
        }

        if (stripos($version, 'MariaDB') !== false) {
            // e.g. "10.6.12-MariaDB-..."
            return version_compare(preg_replace('/-.*/', '', $version), '10.3.7', '>=');
        }

        return version_compare($version, '8.0.13', '>=');
    }

    private function addIndexIfMissing(string $table, string $indexName, string $createSql): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        try {
            DB::statement($createSql);
        } catch (\Throwable $e) {
            Log::warning("Could not create functional index {$indexName} on {$table}: " . $e->getMessage());
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        try {
            DB::statement("DROP INDEX {$indexName} ON {$table}");
        } catch (\Throwable $e) {
            Log::warning("Could not drop index {$indexName} on {$table}: " . $e->getMessage());
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
};
