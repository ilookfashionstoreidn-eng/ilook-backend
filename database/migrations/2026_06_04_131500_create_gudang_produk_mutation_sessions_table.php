<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGudangProdukMutationSessionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('gudang_produk_mutation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layout_id')
                ->constrained('gudang_produk_layouts')
                ->cascadeOnDelete();
            $table->string('from_slot_id');
            $table->unsignedBigInteger('sku_id');
            $table->json('barcodes'); // array of { key, barcode, skuCode, serialCode }
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'done', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('sku_id');
            $table->index('from_slot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang_produk_mutation_sessions');
    }
}
