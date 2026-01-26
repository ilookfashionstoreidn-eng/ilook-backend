<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTanggalJatuhTempoToPendapatanPabrikTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pendapatan_pabrik', function (Blueprint $table) {
            $table->date('tanggal_jatuh_tempo')->nullable()->after('tanggal_bayar');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pendapatan_pabrik', function (Blueprint $table) {
            $table->dropColumn('tanggal_jatuh_tempo');
        });
    }
}
