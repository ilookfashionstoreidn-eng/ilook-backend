<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHargaPerBarangAndTotalHargaToSpkCmtTable extends Migration
{
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->decimal('harga_per_barang', 15, 2)->nullable()->after('jumlah_produk');
            $table->decimal('total_harga', 15, 2)->nullable()->after('harga_per_barang');            
        });
    }

    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropColumn(['harga_per_barang', 'total_harga']);
        });
    }
}
