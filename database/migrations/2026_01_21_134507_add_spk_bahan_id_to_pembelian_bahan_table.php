<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSpkBahanIdToPembelianBahanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table->unsignedBigInteger('spk_bahan_id')
                  ->nullable()
                  ->after('id');

            $table->foreign('spk_bahan_id')
                  ->references('id')
                  ->on('spk_bahan')
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
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table->dropForeign(['spk_bahan_id']);
            $table->dropColumn('spk_bahan_id');
        });
    }
}
