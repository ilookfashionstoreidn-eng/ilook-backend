<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToOrderTablesForPerformance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Index untuk order_items table
        Schema::table('order_items', function (Blueprint $table) {
            // Index untuk join dengan order (sudah ada foreign key, tapi index eksplisit lebih baik)
            if (!$this->hasIndex('order_items', 'order_items_order_id_index')) {
                $table->index('order_id', 'order_items_order_id_index');
            }
            
            // Index untuk lookup SKU (sering digunakan untuk validasi)
            if (!$this->hasIndex('order_items', 'order_items_sku_index')) {
                $table->index('sku', 'order_items_sku_index');
            }
        });

        // Index untuk order_item_serials table
        Schema::table('order_item_serials', function (Blueprint $table) {
            // Index untuk delete dan query berdasarkan order_item_id
            if (!$this->hasIndex('order_item_serials', 'order_item_serials_order_item_id_index')) {
                $table->index('order_item_id', 'order_item_serials_order_item_id_index');
            }
            
            // Index untuk lookup serial number (jika perlu search by serial)
            if (!$this->hasIndex('order_item_serials', 'order_item_serials_serial_number_index')) {
                $table->index('serial_number', 'order_item_serials_serial_number_index');
            }
        });

        // Index untuk order table (tracking_number sudah unique, tapi pastikan index ada)
        Schema::table('order', function (Blueprint $table) {
            // Index untuk status (jika perlu filter by status)
            if (!$this->hasIndex('order', 'order_status_index')) {
                $table->index('status', 'order_status_index');
            }
            
            // Index untuk is_packed (jika kolom ini ada)
            if (Schema::hasColumn('order', 'is_packed')) {
                if (!$this->hasIndex('order', 'order_is_packed_index')) {
                    $table->index('is_packed', 'order_is_packed_index');
                }
            }
        });

        // Index untuk skus table (sku sudah unique, tapi pastikan index optimal)
        if (Schema::hasTable('skus')) {
            Schema::table('skus', function (Blueprint $table) {
                // sku sudah unique, tapi pastikan index ada untuk performa
                // Biasanya unique constraint sudah membuat index otomatis
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_id_index');
            $table->dropIndex('order_items_sku_index');
        });

        Schema::table('order_item_serials', function (Blueprint $table) {
            $table->dropIndex('order_item_serials_order_item_id_index');
            $table->dropIndex('order_item_serials_serial_number_index');
        });

        Schema::table('order', function (Blueprint $table) {
            $table->dropIndex('order_status_index');
            if (Schema::hasColumn('order', 'is_packed')) {
                $table->dropIndex('order_is_packed_index');
            }
        });
    }

    /**
     * Check if index exists
     */
    private function hasIndex($table, $indexName)
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
        
        return $result[0]->count > 0;
    }
}
