<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_lists', function (Blueprint $table) {
            if (!Schema::hasColumn('product_lists', 'product_accecories')) {
                $table->string('product_accecories')->nullable()->after('material_count');
            }

            if (!Schema::hasColumn('product_lists', 'product_accecories_colour')) {
                $table->string('product_accecories_colour')->nullable()->after('product_accecories');
            }

            if (!Schema::hasColumn('product_lists', 'berat_panjang')) {
                $table->decimal('berat_panjang', 12, 2)->nullable()->after('estimasi_combi');
            }

            if (!Schema::hasColumn('product_lists', 'satuan_berat_panjang')) {
                $table->string('satuan_berat_panjang')->nullable()->after('berat_panjang');
            }

            if (!Schema::hasColumn('product_lists', 'berat_panjang_combi')) {
                $table->decimal('berat_panjang_combi', 12, 2)->nullable()->after('satuan_berat_panjang');
            }

            if (!Schema::hasColumn('product_lists', 'satuan_berat_panjang_combi')) {
                $table->string('satuan_berat_panjang_combi')->nullable()->after('berat_panjang_combi');
            }

            if (!Schema::hasColumn('product_lists', 'LD')) {
                $table->decimal('LD', 12, 2)->nullable()->after('satuan_berat_panjang_combi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_lists', function (Blueprint $table) {
            foreach ([
                'LD',
                'satuan_berat_panjang_combi',
                'berat_panjang_combi',
                'satuan_berat_panjang',
                'berat_panjang',
                'product_accecories_colour',
                'product_accecories',
            ] as $column) {
                if (Schema::hasColumn('product_lists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
