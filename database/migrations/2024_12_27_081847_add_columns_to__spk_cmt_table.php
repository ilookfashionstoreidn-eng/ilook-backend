<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToSpkCmtTable extends Migration
{
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->string('nomor_seri')->nullable(); // Nomor Seri Produk
            $table->string('gambar_produk')->nullable(); // Gambar Produk
            $table->date('tanggal_ambil')->nullable(); // Tanggal Ambil
            $table->text('catatan')->nullable(); // Catatan
            $table->string('markeran')->nullable(); // Markeran Produk
            $table->text('aksesoris')->nullable(); // Aksesoris Produk
            $table->string('handtag')->nullable(); // Handtag Produk
            $table->string('merek')->nullable(); // Merek Produk
        });
    }

    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_seri',
                'gambar_produk',
                'tanggal_ambil',
                'catatan',
                'markeran',
                'aksesoris',
                'handtag',
                'merek'
            ]);
        });
    }
}
