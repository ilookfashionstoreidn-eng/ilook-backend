<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProdukSkuIdToSpkCuttingDistribusiDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cutting_distribusi_detail', function (Blueprint $table) {
            $table->foreignId('produk_sku_id')
                ->nullable()
                ->after('jumlah_produk')
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
        Schema::table('spk_cutting_distribusi_detail', function (Blueprint $table) {
            $table->dropForeign(['produk_sku_id']);
            $table->dropColumn('produk_sku_id');
        });
    }
}
