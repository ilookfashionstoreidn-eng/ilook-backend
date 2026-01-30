<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProdukSkuIdToHasilCuttingBahanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
            $table->foreignId('produk_sku_id')
                ->nullable()
                ->after('spk_cutting_bagian_id')
                ->constrained('produk_sku')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hasil_cutting_bahan', function (Blueprint $table) {
            $table->dropForeign(['produk_sku_id']);
            $table->dropColumn('produk_sku_id');
        });
    }
}
