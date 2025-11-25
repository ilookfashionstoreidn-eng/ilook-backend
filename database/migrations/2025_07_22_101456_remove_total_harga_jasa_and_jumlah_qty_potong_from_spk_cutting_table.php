<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTotalHargaJasaAndJumlahQtyPotongFromSpkCuttingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            $table->dropColumn(['total_harga_jasa', 'jumlah_qty_potong']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('spk_cutting', function (Blueprint $table) {
            $table->decimal('total_harga_jasa', 15,2 )->nullable();
            $table->integer('jumlah_qty_potong')->nullable();
        });
    }
}
