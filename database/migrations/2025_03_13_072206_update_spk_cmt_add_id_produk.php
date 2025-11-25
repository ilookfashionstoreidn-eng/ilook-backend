<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSpkCmtAddIdProduk extends Migration
{
    public function up()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            $table->unsignedBigInteger('id_produk')->after('tgl_spk')->nullable();
            $table->foreign('id_produk')->references('id')->on('produk')->onDelete('cascade');

            
            if (Schema::hasColumn('spk_cmt', 'nama_produk')) {
                $table->dropColumn('nama_produk');
            }
        });
    }

    public function down()
    {
        Schema::table('spk_cmt', function (Blueprint $table) {
            
            $table->dropForeign(['id_produk']);
            $table->dropColumn('id_produk');

        
            $table->string('nama_produk')->nullable();
        });
    }
};
