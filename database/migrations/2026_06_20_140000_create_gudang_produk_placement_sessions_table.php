<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGudangProdukPlacementSessionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('gudang_produk_placement_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seri_id');
            $table->unsignedBigInteger('sku_id');
            $table->json('barcodes'); // array of { key, barcode, skuCode, serialCode }
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'done', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('seri_id');
            $table->index('sku_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang_produk_placement_sessions');
    }
}
