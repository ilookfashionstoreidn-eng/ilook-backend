<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTanggalPotongToHasilCuttingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->date('tanggal_potong')->nullable()->after('spk_cutting_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
            $table->dropColumn('tanggal_potong');
        });
    }
}
