<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJenisHargaJasaAndKategoriProdukToSpkCmt extends Migration
{
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->string('jenis_harga_jasa')->default('per_barang');

            $table->string('kategori_produk')->nullable()->after('jenis_harga_jasa');
        });
    }

    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropColumn(['jenis_harga_jasa', 'kategori_produk']);
        });
    }
}
