<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGramasiLebarKainInPembelianBahanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table->decimal('gramasi', 10, 2)->change();
            $table->decimal('lebar_kain', 10, 2)->change();
        });
    }

    public function down()
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table->integer('gramasi')->change();
            $table->integer('lebar_kain')->change();
        });
    }
}
