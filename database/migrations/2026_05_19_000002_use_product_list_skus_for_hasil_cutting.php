<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
            if (!Schema::hasColumn('hasil_cutting_bahan', 'product_list_id')) {
                $table->foreignId('product_list_id')
                    ->nullable()
                    ->after('produk_sku_id')
                    ->constrained('product_lists')
                    ->nullOnDelete();
            }
        });

        Schema::table('spk_cutting_distribusi_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('spk_cutting_distribusi_detail', 'product_list_id')) {
                $table->foreignId('product_list_id')
                    ->nullable()
                    ->after('produk_sku_id')
                    ->constrained('product_lists')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('spk_cutting_distribusi_detail', function (Blueprint $table) {
            if (Schema::hasColumn('spk_cutting_distribusi_detail', 'product_list_id')) {
                $table->dropConstrainedForeignId('product_list_id');
            }
        });

        Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
            if (Schema::hasColumn('hasil_cutting_bahan', 'product_list_id')) {
                $table->dropConstrainedForeignId('product_list_id');
            }
        });
    }
};
