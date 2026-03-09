<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQcScanLolosReportIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('qc_scan_lolos', function (Blueprint $table) {
            $table->index('created_at', 'idx_qc_scan_lolos_created_at');
            $table->index('sku', 'idx_qc_scan_lolos_sku');
            $table->index(['sku', 'created_at'], 'idx_qc_scan_lolos_sku_created_at');
            $table->index(['nomor_seri', 'sku', 'created_at'], 'idx_qc_scan_lolos_seri_sku_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('qc_scan_lolos', function (Blueprint $table) {
            $table->dropIndex('idx_qc_scan_lolos_created_at');
            $table->dropIndex('idx_qc_scan_lolos_sku');
            $table->dropIndex('idx_qc_scan_lolos_sku_created_at');
            $table->dropIndex('idx_qc_scan_lolos_seri_sku_created_at');
        });
    }
}

