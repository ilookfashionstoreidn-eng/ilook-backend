<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBarcodeDownloadedToPembelianAksesorisBTable extends Migration
{
    public function up()
{
    Schema::table('pembelian_aksesoris_b', function (Blueprint $table) {
        $table->boolean('barcode_downloaded')->default(false);
    });
}

    public function down()
    {
        Schema::table('pembelian_aksesoris_b', function (Blueprint $table) {
            $table->dropColumn('barcode_downloaded');
        });
    }
}
