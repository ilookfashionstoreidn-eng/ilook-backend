<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderMonitorIndexes extends Migration
{
    public function up()
    {
        Schema::table('order', function (Blueprint $table) {
            if (!$this->hasIndex('order', 'idx_order_created_id')) {
                $table->index(['created_at', 'id'], 'idx_order_created_id');
            }

            if (Schema::hasColumn('order', 'order_date') && !$this->hasIndex('order', 'idx_order_order_date_id')) {
                $table->index(['order_date', 'id'], 'idx_order_order_date_id');
            }

            if (Schema::hasColumn('order', 'status') && !$this->hasIndex('order', 'idx_order_status_created')) {
                $table->index(['status', 'created_at'], 'idx_order_status_created');
            }

            if (Schema::hasColumn('order', 'is_packed') && !$this->hasIndex('order', 'idx_order_packed_created')) {
                $table->index(['is_packed', 'created_at'], 'idx_order_packed_created');
            }

            if (
                Schema::hasColumn('order', 'label_print_status') &&
                Schema::hasColumn('order', 'label_print_time') &&
                !$this->hasIndex('order', 'idx_order_label_print')
            ) {
                $table->index(['label_print_status', 'label_print_time'], 'idx_order_label_print');
            }
        });
    }

    public function down()
    {
        Schema::table('order', function (Blueprint $table) {
            if ($this->hasIndex('order', 'idx_order_created_id')) {
                $table->dropIndex('idx_order_created_id');
            }

            if ($this->hasIndex('order', 'idx_order_order_date_id')) {
                $table->dropIndex('idx_order_order_date_id');
            }

            if ($this->hasIndex('order', 'idx_order_status_created')) {
                $table->dropIndex('idx_order_status_created');
            }

            if ($this->hasIndex('order', 'idx_order_packed_created')) {
                $table->dropIndex('idx_order_packed_created');
            }

            if ($this->hasIndex('order', 'idx_order_label_print')) {
                $table->dropIndex('idx_order_label_print');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        $result = $connection->select(
            "SELECT COUNT(*) as count
             FROM information_schema.statistics
             WHERE table_schema = ?
             AND table_name = ?
             AND index_name = ?",
            [$databaseName, $table, $indexName]
        );

        return (int) ($result[0]->count ?? 0) > 0;
    }
}
