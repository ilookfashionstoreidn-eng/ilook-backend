<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemovePeriodeAwalAndPeriodeAkhirFromPendapatanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pendapatan', function (Blueprint $table) {
            $table->dropColumn('periode_awal');
            $table->dropColumn('periode_akhir');
        });
    }

 
    public function down()
    {
        Schema::table('pendapatan', function (Blueprint $table) {
            $table->date('periode_awal')->nullable();
            $table->date('periode_akhir')->nullable();
        });
    }
}
