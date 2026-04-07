<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGudangProdukWorkspaceStockTables extends Migration
{
    public function up(): void
    {
        Schema::create('gudang_produk_stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layout_id')
                ->constrained('gudang_produk_layouts')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('sku_id');
            $table->string('slot_id');
            $table->integer('qty')->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['slot_id', 'sku_id']);
            $table->index(['layout_id', 'slot_id']);
        });

        Schema::create('gudang_produk_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['placement', 'mutation']);
            $table->unsignedBigInteger('sku_id');
            $table->string('from_slot_id')->nullable();
            $table->string('to_slot_id')->nullable();
            $table->integer('qty');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index('sku_id');
            $table->index('from_slot_id');
            $table->index('to_slot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang_produk_activity_logs');
        Schema::dropIfExists('gudang_produk_stock_entries');
    }
}
