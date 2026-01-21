<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSpkBahanWarnaIdToPembelianBahanWarnaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pembelian_bahan_warna', function (Blueprint $table) {
            $table->unsignedBigInteger('spk_bahan_warna_id')
                  ->nullable()
                  ->after('pembelian_bahan_id');

            $table->foreign('spk_bahan_warna_id')
                  ->references('id')
                  ->on('spk_bahan_warna')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pembelian_bahan_warna', function (Blueprint $table) {
            $table->dropForeign(['spk_bahan_warna_id']);
            $table->dropColumn('spk_bahan_warna_id');
        });
    }
}
