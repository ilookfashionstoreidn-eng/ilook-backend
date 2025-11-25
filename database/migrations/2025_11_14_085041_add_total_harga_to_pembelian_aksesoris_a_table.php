<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalHargaToPembelianAksesorisATable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pembelian_aksesoris_a', function (Blueprint $table) {
            $table->bigInteger('total_harga')->nullable()->after('harga_satuan');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pembelian_aksesoris_a', function (Blueprint $table) {
            $table->dropColumn('total_harga');
        });
    }
}
