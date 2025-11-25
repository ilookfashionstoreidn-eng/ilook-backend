<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSisaBarangPerWarnaToPengirimanWarna extends Migration
{
    public function up()
    {
        Schema::table('pengiriman_warna', function (Blueprint $table) {
            $table->integer('sisa_barang_per_warna')->default(0)->after('jumlah_dikirim');
        });
    }
    
    public function down()
    {
        Schema::table('pengiriman_warna', function (Blueprint $table) {
            $table->dropColumn('sisa_barang_per_warna');
        });
    }
    
}
