<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('no_data_ginee_logs', function (Blueprint $table) {
            $table->string('scan_mode', 50)
                ->default('tracking_only')
                ->after('scanner_name');
            $table->string('reconciliation_status', 50)
                ->nullable()
                ->after('scan_mode');
            $table->timestamp('reconciled_at')
                ->nullable()
                ->after('notes');

            $table->index(['scan_mode', 'reconciliation_status'], 'idx_ndg_logs_mode_status');
        });

        Schema::create('no_data_ginee_log_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('no_data_ginee_log_id')
                ->constrained('no_data_ginee_logs')
                ->onDelete('cascade');
            $table->unsignedInteger('scan_index');
            $table->string('actual_sku');
            $table->foreignId('actual_sku_id')->nullable()->constrained('skus')->nullOnDelete();
            $table->string('actual_product_name')->nullable();
            $table->text('actual_image')->nullable();
            $table->string('serial_number');
            $table->string('resolved_status', 30)->nullable();
            $table->foreignId('resolved_order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('resolved_original_sku')->nullable();
            $table->string('resolved_original_product_name')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['no_data_ginee_log_id', 'scan_index'], 'idx_ndg_scan_log_index');
            $table->index('serial_number', 'idx_ndg_scan_serial');
            $table->index('resolved_status', 'idx_ndg_scan_resolved_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('no_data_ginee_log_scans');

        Schema::table('no_data_ginee_logs', function (Blueprint $table) {
            $table->dropIndex('idx_ndg_logs_mode_status');
            $table->dropColumn([
                'scan_mode',
                'reconciliation_status',
                'reconciled_at',
            ]);
        });
    }
};
