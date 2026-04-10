<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToPackingLogTables extends Migration
{
    public function up()
    {
        Schema::table('order_logs', function (Blueprint $table) {
            if (!$this->hasIndex('order_logs', 'idx_order_logs_created_id')) {
                $table->index(['created_at', 'id'], 'idx_order_logs_created_id');
            }

            if (!$this->hasIndex('order_logs', 'idx_order_logs_action_created')) {
                $table->index(['action', 'created_at'], 'idx_order_logs_action_created');
            }

            if (!$this->hasIndex('order_logs', 'idx_order_logs_order_created')) {
                $table->index(['order_id', 'created_at'], 'idx_order_logs_order_created');
            }

            if (!$this->hasIndex('order_logs', 'idx_order_logs_performed_by')) {
                $table->index('performed_by', 'idx_order_logs_performed_by');
            }
        });

        Schema::table('no_data_ginee_logs', function (Blueprint $table) {
            if (!$this->hasIndex('no_data_ginee_logs', 'idx_ndg_logs_created_id')) {
                $table->index(['created_at', 'id'], 'idx_ndg_logs_created_id');
            }

            if (!$this->hasIndex('no_data_ginee_logs', 'idx_ndg_logs_order_created')) {
                $table->index(['order_id', 'created_at'], 'idx_ndg_logs_order_created');
            }
        });
    }

    public function down()
    {
        Schema::table('order_logs', function (Blueprint $table) {
            if ($this->hasIndex('order_logs', 'idx_order_logs_created_id')) {
                $table->dropIndex('idx_order_logs_created_id');
            }

            if ($this->hasIndex('order_logs', 'idx_order_logs_action_created')) {
                $table->dropIndex('idx_order_logs_action_created');
            }

            if ($this->hasIndex('order_logs', 'idx_order_logs_order_created')) {
                $table->dropIndex('idx_order_logs_order_created');
            }

            if ($this->hasIndex('order_logs', 'idx_order_logs_performed_by')) {
                $table->dropIndex('idx_order_logs_performed_by');
            }
        });

        Schema::table('no_data_ginee_logs', function (Blueprint $table) {
            if ($this->hasIndex('no_data_ginee_logs', 'idx_ndg_logs_created_id')) {
                $table->dropIndex('idx_ndg_logs_created_id');
            }

            if ($this->hasIndex('no_data_ginee_logs', 'idx_ndg_logs_order_created')) {
                $table->dropIndex('idx_ndg_logs_order_created');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT COUNT(*) as count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$databaseName, $table, $indexName]
        );

        return (int) ($result[0]->count ?? 0) > 0;
    }
}
