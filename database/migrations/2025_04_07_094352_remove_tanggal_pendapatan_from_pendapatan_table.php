<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveTanggalPendapatanFromPendapatanTable extends Migration
{
    public function up()
    {
        Schema::table('pendapatan', function (Blueprint $table) {
            $table->dropColumn('tanggal_pendapatan');
        });
    }

    public function down()
    {
        Schema::table('pendapatan', function (Blueprint $table) {
            $table->dateTime('tanggal_pendapatan')->nullable();
        });
    }
};
