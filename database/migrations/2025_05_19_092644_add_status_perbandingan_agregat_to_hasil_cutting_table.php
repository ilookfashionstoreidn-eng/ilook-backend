<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusPerbandinganAgregatToHasilCuttingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hasil_cutting', function (Blueprint $table) {
             $table->string('status_perbandingan_agregat')->nullable();
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
           $table->dropColumn('status_perbandingan_agregat');
        });
    }
}
