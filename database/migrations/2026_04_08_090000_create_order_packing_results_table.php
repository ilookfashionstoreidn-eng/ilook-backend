<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_packing_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('order')->onDelete('cascade');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('actual_sku_id')->nullable()->constrained('skus')->nullOnDelete();
            $table->string('line_type', 30);
            $table->string('status', 30);
            $table->string('original_sku')->nullable();
            $table->string('original_product_name')->nullable();
            $table->text('original_image')->nullable();
            $table->string('actual_sku');
            $table->string('actual_product_name')->nullable();
            $table->text('actual_image')->nullable();
            $table->unsignedInteger('ordered_qty')->default(0);
            $table->unsignedInteger('scanned_qty')->default(0);
            $table->timestamps();

            $table->index(['order_id', 'status'], 'idx_order_packing_results_order_status');
            $table->index(['order_id', 'order_item_id'], 'idx_order_packing_results_order_item');
            $table->index('actual_sku', 'idx_order_packing_results_actual_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_packing_results');
    }
};
